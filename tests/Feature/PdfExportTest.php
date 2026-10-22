<?php

namespace Tests\Feature;

use App\Jobs\GenerateCardsPdfJob;
use App\Models\Card;
use App\Models\Game;
use App\Models\PdfExport;
use App\Models\Prize;
use App\Services\CardGenerator;
use App\Services\CardPrintService;
use App\Services\PdfService;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create([
            'name' => 'Teste Fila PDF',
            'number_min' => 1,
            'number_max' => 75,
            'use_letters' => true,
            'card_format' => '75',
            'status' => Game::STATUS_SETUP,
        ]);

        $this->game->prizes()->create([
            'name' => 'Linha',
            'rule' => Prize::RULE_ONE_LINE,
            'sort_order' => 1,
        ]);
    }

    private function createCards(int $quantity): void
    {
        $generator = app(CardGenerator::class);

        for ($i = 0; $i < $quantity; $i++) {
            $generator->createCard($this->game);
        }
    }

    public function test_small_batches_are_generated_inline(): void
    {
        Storage::fake('local');
        $this->createCards(1);

        $this->get("/games/{$this->game->id}/cards/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseCount('pdf_exports', 0);
    }

    public function test_large_batches_go_to_the_queue(): void
    {
        Queue::fake();
        $count = CardPrintService::INLINE_MAX_CARDS + 1;
        $this->createCards($count);

        $this->get("/games/{$this->game->id}/cards/pdf")
            ->assertRedirect(route('games.cards.index', $this->game));

        Queue::assertPushed(GenerateCardsPdfJob::class);

        $export = PdfExport::first();
        $this->assertNotNull($export);
        $this->assertSame(PdfExport::STATUS_PENDING, $export->status);
        $this->assertSame($count, $export->cards_count);
        // Endereço do evento capturado na hora do pedido — o QR do PDF
        // aponta para ele (o job não tem contexto de requisição).
        $this->assertNotEmpty($export->base_url);
    }

    public function test_job_generates_issues_cards_and_stores_the_pdf(): void
    {
        Storage::fake('local');
        $count = CardPrintService::INLINE_MAX_CARDS + 1;
        $this->createCards($count);

        $export = $this->game->pdfExports()->create([
            'status' => PdfExport::STATUS_PENDING,
            'cards_count' => $count,
            'filters' => ['qr' => '1'],
            'base_url' => 'http://192.168.0.10:8000',
        ]);

        (new GenerateCardsPdfJob($export))
            ->handle(app(CardPrintService::class), app(PdfService::class));

        $export->refresh();

        $this->assertSame(PdfExport::STATUS_COMPLETED, $export->status);
        $this->assertSame($count, $export->cards_count);
        $this->assertStringEndsWith('.zip', (string) $export->file_name);
        $this->assertStringStartsWith('pdf-exports/cartelas-', (string) $export->file_path);
        Storage::disk('local')->assertExists($export->file_path);

        // ZIP com um PDF por bloco de renderização
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($export->file_path)));
        $this->assertSame((int) ceil($count / GenerateCardsPdfJob::RENDER_CHUNK_SIZE), $zip->numFiles);
        $this->assertSame('%PDF', substr((string) $zip->getFromIndex(0), 0, 4));
        $zip->close();

        // Primeira emissão congelou os prêmios nas cartelas
        $this->assertSame($count, Card::whereNotNull('printed_at')->where('game_id', $this->game->id)->count());

        // O download entrega o arquivo gerado
        $this->get(route('games.pdf-exports.download', [$this->game, $export]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/zip');
    }

    public function test_qr_url_uses_the_captured_base_url_outside_requests(): void
    {
        $this->createCards(1);
        $card = Card::first();

        $this->assertSame(
            'http://192.168.0.10:8000/games/'.$this->game->id.'/card/'.$card->code,
            app(QrCodeService::class)->cardUrl($this->game, $card, 'http://192.168.0.10:8000'),
        );
    }

    public function test_job_marks_export_failed_when_no_cards_match(): void
    {
        Storage::fake('local');

        $export = $this->game->pdfExports()->create([
            'status' => PdfExport::STATUS_PENDING,
            'filters' => ['batch' => 999],
        ]);

        (new GenerateCardsPdfJob($export))
            ->handle(app(CardPrintService::class), app(PdfService::class));

        $export->refresh();

        $this->assertSame(PdfExport::STATUS_FAILED, $export->status);
        $this->assertNotNull($export->error);
        $this->assertNull($export->file_path);
    }

    public function test_destroy_removes_the_export_and_its_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pdf-exports/exemplo.pdf', 'conteudo');

        $export = $this->game->pdfExports()->create([
            'status' => PdfExport::STATUS_COMPLETED,
            'cards_count' => 1,
            'file_path' => 'pdf-exports/exemplo.pdf',
            'file_name' => 'exemplo.pdf',
        ]);

        $this->delete(route('games.pdf-exports.destroy', [$this->game, $export]))
            ->assertRedirect();

        $this->assertDatabaseMissing('pdf_exports', ['id' => $export->id]);
        Storage::disk('local')->assertMissing('pdf-exports/exemplo.pdf');
    }
}

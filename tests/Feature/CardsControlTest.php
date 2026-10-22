<?php

namespace Tests\Feature;

use App\Jobs\GenerateCardsJob;
use App\Models\Card;
use App\Models\Game;
use App\Models\Prize;
use App\Services\CardGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardsControlTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->game = Game::create([
            'name' => 'Teste Cartelas',
            'organization_name' => 'Paróquia Teste',
            'number_min' => 1,
            'number_max' => 75,
            'use_letters' => true,
            'card_format' => '75',
            'status' => Game::STATUS_SETUP,
            'card_price' => 5.00,
        ]);

        $this->game->prizes()->create([
            'name' => 'Linha',
            'rule' => Prize::RULE_ONE_LINE,
            'sort_order' => 1,
            'estimated_value' => 'R$ 100,00',
        ]);
    }

    public function test_generated_cards_are_available_and_batched(): void
    {
        $this->post("/games/{$this->game->id}/cards/generate", ['quantity' => 5]);

        $this->assertSame(5, $this->game->cards()->count());
        $this->assertSame(1, $this->game->cards()->max('batch'));

        // Segundo lote incrementa
        $this->post("/games/{$this->game->id}/cards/generate", ['quantity' => 3]);
        $this->assertSame(3, $this->game->cards()->where('batch', 2)->count());
    }

    public function test_large_generations_go_to_the_queue(): void
    {
        Queue::fake();

        $this->post("/games/{$this->game->id}/cards/generate", ['quantity' => 500])
            ->assertRedirect()
            ->assertSessionHas('generating_batch', 1)
            ->assertSessionHas('generating_quantity', 500);

        Queue::assertPushed(GenerateCardsJob::class, fn ($job) => $job->quantity === 500 && $job->batch === 1);

        // Nada é gerado dentro da requisição HTTP
        $this->assertSame(0, $this->game->cards()->count());
    }

    public function test_queue_job_generates_all_cards_in_batch(): void
    {
        (new GenerateCardsJob($this->game, 3, 7))->handle(app(CardGenerator::class));

        $cards = $this->game->cards()->where('batch', 7)->orderBy('code')->get();

        $this->assertCount(3, $cards);
        $this->assertSame($cards->pluck('code')->unique()->count(), 3);
        $this->assertSame(Card::STATUS_AVAILABLE, $cards->first()->status);
    }

    public function test_generation_status_counts_cards_of_the_batch(): void
    {
        $generator = app(CardGenerator::class);
        for ($i = 0; $i < 2; $i++) {
            $generator->createCard($this->game, batch: 4);
        }
        $generator->createCard($this->game, batch: 5);

        $this->getJson("/games/{$this->game->id}/cards/generation-status?batch=4")
            ->assertOk()
            ->assertJson(['generated' => 2]);
    }

    public function test_custom_letters_word_replaces_bingo_on_cards_and_state(): void
    {
        $this->game->update(['letters_word' => 'festa']);

        // Palavra normalizada para maiúsculas e letras mapeadas por faixa
        $this->assertSame('FESTA', $this->game->lettersWord());
        $this->assertSame(['F', 'E', 'S', 'T', 'A'], $this->game->letters());
        $this->assertSame('F', $this->game->letterFor(1));
        $this->assertSame('A', $this->game->letterFor(75));

        // Estado (painel de números) entrega as letras do tema
        $state = $this->getJson("/games/{$this->game->id}/state")->json();
        $this->assertSame(['F', 'E', 'S', 'T', 'A'], $state['letters']);

        // Cartela impressa usa as letras do tema no cabeçalho da grade
        $card = app(CardGenerator::class)->createCard($this->game);
        $html = $this->get("/games/{$this->game->id}/cards/print?card={$card->code}&per_page=1")->content();

        foreach (['F', 'E', 'S', 'T', 'A'] as $letter) {
            $this->assertStringContainsString(">{$letter}</th>", $html);
        }
        $this->assertStringNotContainsString('>B</th>', $html);
    }

    public function test_default_word_is_bingo_when_not_customized(): void
    {
        $this->assertSame('BINGO', $this->game->lettersWord());
        $this->assertSame(['B', 'I', 'N', 'G', 'O'], $this->game->letters());
        $this->assertSame('N', $this->game->letterFor(40));
    }

    public function test_letters_word_must_have_exactly_five_letters(): void
    {
        $this->post('/games', [
            'name' => 'Tema inválido',
            'card_format' => '75',
            'number_min' => 1,
            'number_max' => 75,
            'letters_word' => 'FEST',
        ])->assertSessionHasErrors('letters_word');

        $this->post('/games', [
            'name' => 'Tema válido',
            'card_format' => '75',
            'number_min' => 1,
            'number_max' => 75,
            'letters_word' => 'BAILE',
        ])->assertRedirect();

        $this->assertDatabaseHas('games', ['name' => 'Tema válido', 'letters_word' => 'BAILE']);
    }

    public function test_no_fully_duplicated_cards_in_game(): void
    {
        $generator = app(CardGenerator::class);
        for ($i = 0; $i < 30; $i++) {
            $generator->createCard($this->game);
        }

        $signatures = $this->game->cards()->get()
            ->map(fn ($card) => md5(json_encode($card->grid)));

        $this->assertSame($signatures->count(), $signatures->unique()->count());
    }

    public function test_buyer_registration_marks_card_sold_and_searchable(): void
    {
        $card = app(CardGenerator::class)->createCard($this->game);
        $this->assertSame(Card::STATUS_AVAILABLE, $card->status);

        $this->post("/games/{$this->game->id}/cards/{$card->id}/buyer", [
            'buyer_name' => 'Maria Souza',
            'buyer_phone' => '(11) 98888-7777',
        ]);

        $card->refresh();
        $this->assertSame(Card::STATUS_SOLD, $card->status);
        $this->assertSame('Maria Souza', $card->buyer_name);

        // Pesquisa por telefone
        $this->get("/games/{$this->game->id}/cards?q=98888")
            ->assertOk()
            ->assertSee('Maria Souza');

        // Registro administrativo no histórico
        $this->assertDatabaseHas('game_events', ['type' => 'buyer_updated']);
    }

    public function test_cancelled_card_exits_round_and_can_be_restored(): void
    {
        $card = app(CardGenerator::class)->createCard($this->game);

        $this->post("/games/{$this->game->id}/cards/{$card->id}/cancel");
        $this->assertSame(Card::STATUS_CANCELLED, $card->refresh()->status);

        $this->post("/games/{$this->game->id}/cards/{$card->id}/cancel", ['restore' => 1]);
        $this->assertSame(Card::STATUS_AVAILABLE, $card->refresh()->status);
    }

    public function test_game_start_puts_cards_in_play(): void
    {
        $sold = app(CardGenerator::class)->createCard($this->game);
        $sold->update(['status' => Card::STATUS_SOLD, 'buyer_name' => 'João']);
        $available = app(CardGenerator::class)->createCard($this->game);
        $cancelled = app(CardGenerator::class)->createCard($this->game);
        $cancelled->update(['status' => Card::STATUS_CANCELLED]);

        $this->post("/games/{$this->game->id}/start");

        $this->assertSame(Card::STATUS_IN_PLAY, $sold->refresh()->status);
        $this->assertSame(Card::STATUS_IN_PLAY, $available->refresh()->status);
        $this->assertSame(Card::STATUS_CANCELLED, $cancelled->refresh()->status);
    }

    public function test_na_boa_summary_counts_cards_within_threshold(): void
    {
        $card = app(CardGenerator::class)->createCard($this->game);
        $other = app(CardGenerator::class)->createCard($this->game);

        $this->post("/games/{$this->game->id}/start");

        // Completa 4 números da primeira linha da cartela: faltará 1 → na boa
        $row = collect($card->grid[0])->filter()->values();
        foreach ($row->take(4) as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $state = $this->getJson("/games/{$this->game->id}/state")->assertOk()->json();

        $this->assertSame(2, $state['cards_summary']['total_in_play']);
        $this->assertSame(1, $state['cards_summary']['na_boa']);
        $this->assertSame(1, $state['cards_summary']['far']);
        $this->assertSame($card->code, $state['cards_summary']['na_boa_cards'][0]['code']);
        $this->assertSame(1, $state['cards_summary']['na_boa_cards'][0]['missing']);

        // Aumenta o limite: continua 1 na boa; com threshold 0, nenhuma
        $this->postJson("/games/{$this->game->id}/na-boa-threshold", ['threshold' => 0])->assertOk();
        $state = $this->getJson("/games/{$this->game->id}/state")->json();
        $this->assertSame(0, $state['cards_summary']['na_boa']);
    }

    public function test_printing_freezes_prize_snapshot_on_first_issue(): void
    {
        $card = app(CardGenerator::class)->createCard($this->game);

        // Primeira emissão congela os prêmios
        $this->get("/games/{$this->game->id}/cards/print?card={$card->code}")->assertOk();
        $snapshot = $card->refresh()->prize_snapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame('Linha', $snapshot[0]['name']);

        // Recua a emissão para simular tempo decorrido antes da alteração
        $card->update(['printed_at' => now()->subHour()]);

        // Altera o prêmio depois da emissão
        $this->game->prizes()->first()->update(['name' => 'Prêmio Renomeado']);

        // Reimpressão mantém o snapshot original (cartelas emitidas não mudam em silêncio)
        $this->get("/games/{$this->game->id}/cards/print?card={$card->code}")->assertOk();
        $this->assertSame('Linha', $card->refresh()->prize_snapshot[0]['name']);

        // PDF também usa o snapshot
        $this->get("/games/{$this->game->id}/cards/pdf?card={$card->code}")->assertOk();
        $this->assertSame('Linha', $card->refresh()->prize_snapshot[0]['name']);

        // Página de cartelas continua OK com cartelas já emitidas (comparação de datas)
        $this->get("/games/{$this->game->id}/cards")->assertOk()->assertSee('prêmios foram alterados', false);
    }

    public function test_pdf_endpoint_returns_pdf(): void
    {
        app(CardGenerator::class)->createCard($this->game);

        $response = $this->get("/games/{$this->game->id}/cards/pdf?per_page=2");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_winner_card_is_marked_and_excluded_from_na_boa(): void
    {
        $card = app(CardGenerator::class)->createCard($this->game);
        $this->post("/games/{$this->game->id}/start");

        $prize = $this->game->prizes->first();
        $row = collect($card->grid[0])->filter()->values();
        foreach ($row as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $prize->id,
        ])->assertOk();

        $this->assertSame(Card::STATUS_WINNER, $card->refresh()->status);

        $state = $this->getJson("/games/{$this->game->id}/state")->json();
        $this->assertSame(1, $state['cards_summary']['winners']);
        $this->assertSame(0, $state['cards_summary']['total_in_play']);
    }
}

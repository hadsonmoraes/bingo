<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\PdfExport;
use App\Models\Prize;
use App\Services\CardGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rotas aninhadas usam scoped bindings: os registros filhos de outra
 * partida nem chegam ao controller — respondem 404 sem revelar que o
 * registro existe em outro evento.
 */
class ScopedRoutesTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Game $otherGame;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $makeGame = function (string $name): Game {
            return Game::create([
                'name' => $name,
                'number_min' => 1,
                'number_max' => 75,
                'use_letters' => true,
                'card_format' => '75',
                'status' => Game::STATUS_IN_PROGRESS,
            ]);
        };

        $this->game = $makeGame('Partida A');
        $this->otherGame = $makeGame('Partida B');
    }

    public function test_buyer_update_on_card_from_another_game_returns_404(): void
    {
        $card = app(CardGenerator::class)->createCard($this->otherGame);

        $this->post("/games/{$this->game->id}/cards/{$card->id}/buyer", ['buyer_name' => 'Visitante'])
            ->assertNotFound();

        $this->assertDatabaseMissing('cards', ['id' => $card->id, 'buyer_name' => 'Visitante']);
    }

    public function test_cancel_on_card_from_another_game_returns_404(): void
    {
        $card = app(CardGenerator::class)->createCard($this->otherGame);

        $this->post("/games/{$this->game->id}/cards/{$card->id}/cancel")
            ->assertNotFound();

        $this->assertSame(Card::STATUS_AVAILABLE, $card->refresh()->status);
    }

    public function test_update_on_prize_from_another_game_returns_404(): void
    {
        $prize = $this->otherGame->prizes()->create([
            'name' => 'Prêmio da Partida B',
            'rule' => Prize::RULE_ONE_LINE,
            'sort_order' => 1,
        ]);

        $this->put("/games/{$this->game->id}/prizes/{$prize->id}", [
            'name' => 'Alterado indevidamente',
            'rule' => Prize::RULE_ONE_LINE,
        ])->assertNotFound();

        $this->assertSame('Prêmio da Partida B', $prize->refresh()->name);
    }

    public function test_destroy_on_prize_from_another_game_returns_404(): void
    {
        $prize = $this->otherGame->prizes()->create([
            'name' => 'Prêmio da Partida B',
            'rule' => Prize::RULE_ONE_LINE,
            'sort_order' => 1,
        ]);

        $this->delete("/games/{$this->game->id}/prizes/{$prize->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('prizes', ['id' => $prize->id]);
    }

    public function test_undo_on_winner_from_another_game_returns_404(): void
    {
        $card = app(CardGenerator::class)->createCard($this->otherGame);
        $prize = $this->otherGame->prizes()->create([
            'name' => 'Prêmio da Partida B',
            'rule' => Prize::RULE_ONE_LINE,
            'sort_order' => 1,
        ]);

        $winner = $this->otherGame->winners()->create([
            'prize_id' => $prize->id,
            'card_id' => $card->id,
            'winner_name' => 'Fulano',
            'validated_at' => now(),
        ]);

        $this->postJson("/games/{$this->game->id}/winners/{$winner->id}/undo", ['reason' => 'correção de teste'])
            ->assertNotFound();

        $this->assertDatabaseHas('winners', ['id' => $winner->id]);
    }

    public function test_edit_on_card_template_from_another_game_returns_404(): void
    {
        $template = $this->otherGame->cardTemplates()->create([
            'name' => 'Modelo da Partida B',
            'art_path' => 'card-templates/b.svg',
            'art_mime' => 'image/svg+xml',
            'is_default' => true,
        ]);

        $this->get("/games/{$this->game->id}/card-templates/{$template->id}/edit")
            ->assertNotFound();

        $this->assertSame('Modelo da Partida B', $template->refresh()->name);
    }

    public function test_download_and_destroy_on_pdf_export_from_another_game_return_404(): void
    {
        $export = $this->otherGame->pdfExports()->create([
            'status' => PdfExport::STATUS_COMPLETED,
            'cards_count' => 1,
            'file_path' => 'pdf-exports/outro-jogo.zip',
            'file_name' => 'outro-jogo.zip',
        ]);

        $this->get("/games/{$this->game->id}/pdf-exports/{$export->id}/download")
            ->assertNotFound();

        $this->delete("/games/{$this->game->id}/pdf-exports/{$export->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('pdf_exports', ['id' => $export->id]);
    }
}

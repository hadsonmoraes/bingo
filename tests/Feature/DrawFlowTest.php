<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CardBlock;
use App\Models\Game;
use App\Models\Prize;
use App\Services\CardGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrawFlowTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create([
            'name' => 'Teste',
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

        $this->card = app(CardGenerator::class)->createCard($this->game);
    }

    private function startGame(): void
    {
        $this->post("/games/{$this->game->id}/start");
    }

    public function test_dashboard_lists_games(): void
    {
        $this->get('/')->assertOk()->assertSee('Teste');
    }

    public function test_cannot_register_draw_before_start(): void
    {
        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5])
            ->assertStatus(422)
            ->assertJsonPath('code', 'error');

        $this->assertDatabaseCount('draws', 0);
    }

    public function test_register_draw_saves_number_letter_and_event(): void
    {
        $this->startGame();

        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5])
            ->assertOk()
            ->assertJsonPath('state.last.number', 5)
            ->assertJsonPath('state.last.letter', 'B');

        $this->assertDatabaseHas('draws', ['game_id' => $this->game->id, 'number' => 5, 'letter' => 'B', 'sequence' => 1]);
        $this->assertDatabaseHas('game_events', ['type' => 'draw_registered']);
    }

    public function test_rejects_duplicate_draw(): void
    {
        $this->startGame();

        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5])->assertOk();
        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5])
            ->assertStatus(422)
            ->assertJsonPath('code', 'duplicate');

        $this->assertDatabaseCount('draws', 1);
    }

    public function test_rejects_out_of_range_draw(): void
    {
        $this->startGame();

        $this->postJson("/games/{$this->game->id}/draws", ['number' => 99])
            ->assertStatus(422)
            ->assertJsonPath('code', 'error');

        $this->postJson("/games/{$this->game->id}/draws", ['number' => 0])
            ->assertStatus(422);
    }

    public function test_undo_requires_reason_and_logs_correction(): void
    {
        $this->startGame();
        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5]);

        $this->postJson("/games/{$this->game->id}/draws/undo", ['reason' => ''])
            ->assertStatus(422);

        $this->postJson("/games/{$this->game->id}/draws/undo", ['reason' => 'pedra identificada incorretamente'])
            ->assertOk();

        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseHas('game_events', [
            'type' => 'draw_reverted',
            'data' => json_encode(['number' => 5, 'letter' => 'B', 'sequence' => 1, 'reason' => 'pedra identificada incorretamente']),
        ]);
    }

    public function test_undo_removes_the_last_registered_draw_not_the_first(): void
    {
        $this->startGame();

        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5]);
        $this->postJson("/games/{$this->game->id}/draws", ['number' => 23]);

        $this->postJson("/games/{$this->game->id}/draws/undo", ['reason' => 'pedra identificada incorretamente'])
            ->assertOk()
            ->assertJsonPath('message', 'Registro da pedra 23 desfeito.');

        // A primeira pedra permanece; a última foi removida
        $this->assertDatabaseHas('draws', ['number' => 5]);
        $this->assertDatabaseMissing('draws', ['number' => 23]);

        $state = $this->getJson("/games/{$this->game->id}/state")->json();
        $this->assertSame($state['last']['number'] === 23, false);
        $this->assertSame(5, $state['last']['number']);
    }

    public function test_pause_and_resume(): void
    {
        $this->startGame();

        $this->postJson("/games/{$this->game->id}/pause")->assertOk();
        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5])->assertStatus(422);

        $this->postJson("/games/{$this->game->id}/resume")->assertOk();
        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5])->assertOk();
    }

    public function test_game_is_recovered_from_database(): void
    {
        // Cada resultado é salvo imediatamente: a partida pode ser
        // recuperada após qualquer interrupção (ex.: navegador fechado).
        $this->startGame();
        $this->postJson("/games/{$this->game->id}/draws", ['number' => 5]);
        $this->postJson("/games/{$this->game->id}/draws", ['number' => 23]);

        $state = $this->getJson("/games/{$this->game->id}/state")->assertOk()->json();

        $this->assertSame([5, 23], $state['drawn']);
        $this->assertSame(23, $state['last']['number']);
        $this->assertSame('I', $state['last']['letter']);
        $this->assertSame(73, $state['remaining_count']);
    }

    public function test_winner_validation_uses_only_current_round_draws(): void
    {
        $this->startGame();

        $prize = $this->game->prizes->first();
        $row = collect($this->card->grid[0])->filter()->values();

        // Completa a primeira linha da cartela
        foreach ($row as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $this->card->code,
            'prize_id' => $prize->id,
            'winner_name' => 'Maria',
        ])->assertOk();

        $this->assertDatabaseCount('winners', 1);

        // Prêmio já tem ganhador
        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $this->card->code,
            'prize_id' => $prize->id,
        ])->assertStatus(422);
    }

    public function test_winner_validation_rejects_card_that_does_not_win(): void
    {
        $this->startGame();

        $prize = $this->game->prizes->first();

        $this->postJson("/games/{$this->game->id}/draws", ['number' => 1])->assertOk();

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $this->card->code,
            'prize_id' => $prize->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('winners', 0);
    }

    public function test_winner_on_unsold_card_warns_about_missing_buyer(): void
    {
        $this->startGame();

        // Cartela sem comprador cadastrado (pode ter sido vendida à mão)
        $this->assertFalse((bool) $this->card->buyer_name);

        $prize = $this->game->prizes->first();
        $row = collect($this->card->grid[0])->filter()->values();
        foreach ($row as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $response = $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $this->card->code,
            'prize_id' => $prize->id,
        ])->assertOk();

        // A validação funciona, mas avisa que não há comprador cadastrado
        $this->assertStringContainsString('cartela sem comprador cadastrado', $response->json('message'));
    }

    public function test_winner_validation_can_be_reverted_letting_the_game_continue(): void
    {
        $this->startGame();

        $prize = $this->game->prizes->first();
        $block = $this->card->blocks()->first();
        $row = collect($block->grid[0])->filter()->values();
        foreach ($row as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $this->card->code,
            'prize_id' => $prize->id,
        ])->assertOk();

        // Prêmio travado para nova validação
        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $this->card->code,
            'prize_id' => $prize->id,
        ])->assertStatus(422);

        $winnerId = $this->game->winners()->first()->id;

        // Desfazer exige motivo
        $this->postJson("/games/{$this->game->id}/winners/{$winnerId}/undo", ['reason' => ''])
            ->assertStatus(422);

        // Desfaz: cartela não foi vendida
        $this->postJson("/games/{$this->game->id}/winners/{$winnerId}/undo", [
            'reason' => 'cartela não foi vendida',
        ])->assertOk();

        // Ganhador removido; cartela e bloco voltam ao jogo; prêmio livre
        $this->assertDatabaseCount('winners', 0);
        $this->assertSame(Card::STATUS_IN_PLAY, $this->card->refresh()->status);
        $this->assertSame(CardBlock::STATUS_ACTIVE, $block->refresh()->status);
        $this->assertDatabaseHas('game_events', [
            'type' => 'winner_reverted',
        ]);

        // O jogo continua: a mesma cartela pode validar o prêmio de novo
        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $this->card->code,
            'prize_id' => $prize->id,
        ])->assertOk();

        $this->assertSame(Card::STATUS_WINNER, $this->card->refresh()->status);
        $this->assertSame(1, $this->game->winners()->count());
    }

    public function test_reverted_winner_card_returns_to_sold_status_when_game_is_over(): void
    {
        $this->startGame();

        $this->card->update(['buyer_name' => 'Maria']);
        $prize = $this->game->prizes->first();
        $row = collect($this->card->grid[0])->filter()->values();
        foreach ($row as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $this->card->code,
            'prize_id' => $prize->id,
        ])->assertOk();

        $this->post("/games/{$this->game->id}/finish");

        $winnerId = $this->game->winners()->first()->id;
        $this->postJson("/games/{$this->game->id}/winners/{$winnerId}/undo", [
            'reason' => 'correção administrativa',
        ])->assertOk();

        // Fora da partida ativa, cartela com comprador volta a "vendida"
        $this->assertSame(Card::STATUS_SOLD, $this->card->refresh()->status);
    }
}

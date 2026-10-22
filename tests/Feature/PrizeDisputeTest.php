<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prize;
use App\Services\CardGenerator;
use App\Services\GameStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrizeDisputeTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private GameStateService $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create([
            'name' => 'Disputa de prêmios',
            'number_min' => 1,
            'number_max' => 75,
            'use_letters' => true,
            'card_format' => '75',
            'status' => Game::STATUS_SETUP,
        ]);

        // 1 = prêmio principal; 2 e 3 = prêmios menores
        $this->game->prizes()->create(['name' => 'Principal', 'rule' => Prize::RULE_FULL_CARD, 'sort_order' => 1]);
        $this->game->prizes()->create(['name' => 'Médio', 'rule' => Prize::RULE_TWO_LINES, 'sort_order' => 2]);
        $this->game->prizes()->create(['name' => 'Pequeno', 'rule' => Prize::RULE_ONE_LINE, 'sort_order' => 3]);

        $this->state = app(GameStateService::class);
    }

    public function test_dispute_starts_from_last_prize_towards_the_main_one(): void
    {
        $all = $this->state->all($this->game->refresh());

        $this->assertSame('Pequeno', $all['current_prize']['name']);
        $this->assertSame(1, $all['current_prize']['position']);
        $this->assertSame(3, $all['current_prize']['total']);
        $this->assertSame('Médio', $all['next_prize']['name']);
    }

    public function test_advance_moves_dispute_pointer_to_next_prize(): void
    {
        $this->postJson("/games/{$this->game->id}/prizes/advance")
            ->assertOk()
            ->assertJsonPath('state.current_prize.name', 'Médio');

        $this->assertDatabaseHas('games', [
            'id' => $this->game->id,
            'current_prize_id' => $this->game->prizes()->where('name', 'Médio')->first()->id,
        ]);

        // Avança de novo: chega ao prêmio principal, sem próximo
        $this->postJson("/games/{$this->game->id}/prizes/advance")
            ->assertOk()
            ->assertJsonPath('state.current_prize.name', 'Principal')
            ->assertJsonPath('state.next_prize', null);

        // Último prêmio da disputa: não há para onde avançar
        $this->postJson("/games/{$this->game->id}/prizes/advance")
            ->assertStatus(422)
            ->assertJsonPath('message', '"Principal" é o último prêmio da disputa.');
    }

    /**
     * Quando o ponteiro manual aponta um trecho já todo ganho, o estado
     * exibido no telão recua ao primeiro prêmio em disputa e o "próximo"
     * segue a mesma sequência usada pelo botão "Avançar prêmio".
     */
    public function test_state_next_prize_follows_dispute_when_pointer_segment_is_won(): void
    {
        $card = app(CardGenerator::class)->createCard($this->game);
        $main = $this->game->prizes()->where('name', 'Principal')->first();

        // Principal já tem ganhador; ponteiro avançou até ele
        $this->game->winners()->create([
            'prize_id' => $main->id,
            'card_id' => $card->id,
            'winner_name' => 'Fulano',
            'validated_at' => now(),
        ]);
        $this->game->update(['current_prize_id' => $main->id]);

        $all = $this->state->all($this->game->refresh());

        // Recua para o primeiro prêmio ainda em disputa
        $this->assertSame('Pequeno', $all['current_prize']['name']);
        // Próximo da disputa — o mesmo que "Avançar prêmio" usaria
        $this->assertSame('Médio', $all['next_prize']['name']);
        $this->assertSame('Médio', $this->state->nextPrize($this->game)->name);
    }

    public function test_validating_current_prize_moves_dispute_to_next_one(): void
    {
        $small = $this->game->prizes()->where('name', 'Pequeno')->first();
        $card = app(CardGenerator::class)->createCard($this->game);
        $block = $card->blocks()->where('prize_id', $small->id)->first();

        $this->postJson("/games/{$this->game->id}/start");

        $row = collect($block->grid[0])->filter()->values();
        foreach ($row as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $small->id,
        ])->assertOk();

        // Com o prêmio menor ganho, a disputa avança automaticamente
        $all = $this->state->all($this->game->refresh());
        $this->assertSame('Médio', $all['current_prize']['name']);
    }

    public function test_undoing_winner_restores_prize_as_current_dispute(): void
    {
        $small = $this->game->prizes()->where('name', 'Pequeno')->first();
        $card = app(CardGenerator::class)->createCard($this->game);
        $block = $card->blocks()->where('prize_id', $small->id)->first();

        $this->postJson("/games/{$this->game->id}/start");

        $row = collect($block->grid[0])->filter()->values();
        foreach ($row as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $small->id,
        ])->assertOk();

        $winnerId = $this->game->winners()->first()->id;

        // Ponteiro avança manualmente, ficando à frente do prêmio ganho
        $this->postJson("/games/{$this->game->id}/prizes/advance")->assertOk();

        $this->postJson("/games/{$this->game->id}/winners/{$winnerId}/undo", ['reason' => 'Validação por engano'])
            ->assertOk()
            ->assertJsonPath('state.current_prize.name', 'Pequeno');
    }

    public function test_prize_allows_tie_between_different_cards(): void
    {
        $small = $this->game->prizes()->where('name', 'Pequeno')->first();
        $cardA = app(CardGenerator::class)->createCard($this->game);
        $cardB = app(CardGenerator::class)->createCard($this->game);

        $this->postJson("/games/{$this->game->id}/start");

        // Completa a linha do bloco "Pequeno" das duas cartelas
        $numbers = collect([
            collect($cardA->blocks()->where('prize_id', $small->id)->first()->grid[0])->filter()->values(),
            collect($cardB->blocks()->where('prize_id', $small->id)->first()->grid[0])->filter()->values(),
        ])->flatten()->unique();

        foreach ($numbers as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $first = $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $cardA->code,
            'prize_id' => $small->id,
        ])->assertOk();

        $this->assertStringContainsString('TEMOS UM GANHADOR', $first->json('message'));

        // Empate: outra cartela pode vencer o mesmo prêmio
        $second = $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $cardB->code,
            'prize_id' => $small->id,
        ])->assertOk();

        $this->assertStringContainsString('EMPATE REGISTRADO', $second->json('message'));
        $this->assertDatabaseCount('winners', 2);
    }

    public function test_same_card_cannot_win_same_prize_twice(): void
    {
        $small = $this->game->prizes()->where('name', 'Pequeno')->first();
        $card = app(CardGenerator::class)->createCard($this->game);
        $block = $card->blocks()->where('prize_id', $small->id)->first();

        $this->postJson("/games/{$this->game->id}/start");

        $row = collect($block->grid[0])->filter()->values();
        foreach ($row as $number) {
            $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
        }

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $small->id,
        ])->assertOk();

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $small->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Esta cartela já foi validada para este prêmio.');

        $this->assertDatabaseCount('winners', 1);
    }

    public function test_advance_without_prizes_fails_gracefully(): void
    {
        $game = Game::create([
            'name' => 'Sem prêmios',
            'number_min' => 1,
            'number_max' => 75,
            'use_letters' => false,
            'card_format' => '75',
            'status' => Game::STATUS_SETUP,
        ]);

        $this->postJson("/games/{$game->id}/prizes/advance")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não há prêmios em disputa para avançar.');
    }
}

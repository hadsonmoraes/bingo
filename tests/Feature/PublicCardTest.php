<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\Prize;
use App\Services\CardGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCardTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Card $card;

    private Prize $prize;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create([
            'name' => 'Teste QR',
            'number_min' => 1,
            'number_max' => 75,
            'use_letters' => true,
            'card_format' => '75',
            'status' => Game::STATUS_SETUP,
        ]);

        $this->prize = $this->game->prizes()->create([
            'name' => 'Linha',
            'rule' => Prize::RULE_ONE_LINE,
            'sort_order' => 1,
        ]);

        $this->card = app(CardGenerator::class)->createCard($this->game);
        $this->card->update(['buyer_name' => 'Fulano da Silva']);
    }

    public function test_public_card_page_shows_card_numbers(): void
    {
        $number = $this->card->effectiveBlocks()->first()->grid[0][0];

        $this->get("/games/{$this->game->id}/card/{$this->card->code}")
            ->assertOk()
            ->assertSee('Teste QR')
            ->assertSee($this->card->code)
            ->assertSee((string) $number)
            // Dados pessoais do comprador nunca aparecem na página pública
            ->assertDontSee('Fulano da Silva');
    }

    public function test_public_card_page_404_for_unknown_code(): void
    {
        $this->get("/games/{$this->game->id}/card/C-9999")->assertNotFound();
    }

    public function test_state_endpoint_reports_drawn_numbers_and_block_missing(): void
    {
        $grid = $this->card->effectiveBlocks()->first()->grid;

        $this->post("/games/{$this->game->id}/start");

        foreach ($grid[0] as $number) {
            if ($number !== null) {
                $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
            }
        }

        $response = $this->getJson("/games/{$this->game->id}/card/{$this->card->code}/state")
            ->assertOk()
            ->assertJsonPath('drawn_count', count(array_filter($grid[0])))
            ->assertJsonPath('blocks.0.missing', 0)
            ->assertJsonPath('last.number', (int) $grid[0][4])
            ->assertJsonPath('current_prize.name', 'Linha');

        $this->assertStringNotContainsString('Fulano da Silva', $response->getContent());
    }

    public function test_winner_validation_accepts_the_full_qr_url_as_card_code(): void
    {
        $grid = $this->card->effectiveBlocks()->first()->grid;

        $this->post("/games/{$this->game->id}/start");

        foreach ($grid[0] as $number) {
            if ($number !== null) {
                $this->postJson("/games/{$this->game->id}/draws", ['number' => $number])->assertOk();
            }
        }

        $qrUrl = url('/games/'.$this->game->id.'/card/'.$this->card->code);

        $this->postJson("/games/{$this->game->id}/winners/validate", [
            'card_code' => $qrUrl,
            'prize_id' => $this->prize->id,
        ])
            ->assertOk()
            ->assertJsonPath('winner.card_code', $this->card->code)
            ->assertJsonPath('winner.name', 'Fulano da Silva');

        $this->assertDatabaseHas('winners', ['card_id' => $this->card->id, 'prize_id' => $this->prize->id]);
    }

    public function test_print_preview_includes_qr_code_by_default_and_can_be_disabled(): void
    {
        $this->get("/games/{$this->game->id}/cards/print")
            ->assertOk()
            ->assertSee('QR Code para acompanhar a cartela')
            ->assertSee('data:image/png;base64');

        $this->get("/games/{$this->game->id}/cards/print?qr=0")
            ->assertOk()
            ->assertDontSee('QR Code para acompanhar a cartela')
            ->assertDontSee('data:image/png;base64');
    }
}

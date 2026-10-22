<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Game;
use App\Models\Prize;
use App\Services\CardGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardCheckTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::create([
            'name' => 'Teste Conferência',
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

        $this->card = app(CardGenerator::class)->createCard($this->game);
    }

    private function check(string $code)
    {
        return $this->postJson("/games/{$this->game->id}/cards/check", ['code' => $code]);
    }

    public function test_check_page_renders_with_scanner_input_and_link_back_to_draw(): void
    {
        $this->get(route('games.check', $this->game))
            ->assertOk()
            ->assertSee('Conferir cartela')
            ->assertSee($this->game->name)
            ->assertSee(route('games.draw', $this->game));
    }

    public function test_returns_card_in_play_when_buyer_is_not_registered(): void
    {
        $this->check($this->card->code)
            ->assertOk()
            ->assertJsonPath('card.code', $this->card->code)
            ->assertJsonPath('card.sold', false)
            ->assertJsonPath('card.status', Card::STATUS_LABELS[Card::STATUS_AVAILABLE]);
    }

    public function test_accepts_the_code_scanned_from_the_stub_barcode(): void
    {
        // O canhoto imprime o mesmo conteúdo em Code128 — a leitura do
        // scanner chega como o código puro, em minúsculas ou não.
        $this->check(strtolower($this->card->code))
            ->assertOk()
            ->assertJsonPath('card.code', $this->card->code);
    }

    public function test_accepts_the_full_url_encoded_in_the_header_qr_code(): void
    {
        $url = url("/games/{$this->game->id}/card/{$this->card->code}");

        $this->check($url)
            ->assertOk()
            ->assertJsonPath('card.code', $this->card->code);
    }

    public function test_returns_sold_card_with_buyer_data(): void
    {
        $this->card->forceFill([
            'buyer_name' => 'Maria da Silva',
            'buyer_phone' => '(11) 99999-0000',
        ])->save();

        $this->check($this->card->code)
            ->assertOk()
            ->assertJsonPath('card.sold', true)
            ->assertJsonPath('card.buyer_name', 'Maria da Silva')
            ->assertJsonPath('card.buyer_phone', '(11) 99999-0000');
    }

    public function test_returns_404_when_the_code_belongs_to_another_game(): void
    {
        $other = Game::create([
            'name' => 'Outra Partida',
            'number_min' => 1,
            'number_max' => 75,
            'use_letters' => false,
            'card_format' => '75',
            'status' => Game::STATUS_SETUP,
        ]);
        $other->prizes()->create(['name' => 'Linha', 'rule' => Prize::RULE_ONE_LINE, 'sort_order' => 1]);
        app(CardGenerator::class)->createCard($other);
        $foreignCard = app(CardGenerator::class)->createCard($other);

        $this->check($foreignCard->code)
            ->assertNotFound()
            ->assertJsonPath('message', 'Cartela não encontrada nesta partida.');
    }

    public function test_returns_404_for_an_unknown_code(): void
    {
        $this->check('C-9999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Cartela não encontrada nesta partida.');
    }

    public function test_returns_422_when_code_is_missing(): void
    {
        $this->postJson("/games/{$this->game->id}/cards/check", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }
}

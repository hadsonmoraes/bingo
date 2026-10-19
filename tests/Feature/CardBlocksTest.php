<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\CardBlock;
use App\Models\Game;
use App\Models\Prize;
use App\Services\CardGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CardBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function createGame(int $prizesCount, string $format = '75'): Game
    {
        $game = Game::create([
            'name' => 'Bingo dos Blocos',
            'organization_name' => 'Instituição Teste',
            'number_min' => $format === '90' ? 1 : 1,
            'number_max' => $format === '90' ? 90 : 75,
            'use_letters' => $format === '75',
            'card_format' => $format,
            'status' => Game::STATUS_SETUP,
        ]);

        $rules = [Prize::RULE_ONE_LINE, Prize::RULE_TWO_LINES, Prize::RULE_FULL_CARD, Prize::RULE_FOUR_CORNERS];
        for ($i = 1; $i <= $prizesCount; $i++) {
            $game->prizes()->create([
                'name' => "Prêmio {$i}",
                'rule' => $rules[($i - 1) % count($rules)],
                'sort_order' => $i,
                'estimated_value' => "R$ {$i}00,00",
            ]);
        }

        return $game->refresh();
    }

    /** 1, 2 e 3. Cada prêmio gera um bloco numérico correspondente. */
    public function test_each_prize_generates_one_block(): void
    {
        foreach ([1, 2, 4] as $prizesCount) {
            $game = $this->createGame($prizesCount);
            $card = app(CardGenerator::class)->createCard($game);

            $this->assertCount(
                $prizesCount,
                $card->blocks,
                "Com {$prizesCount} prêmio(s) a cartela deve ter {$prizesCount} bloco(s)."
            );
            $this->assertSame($prizesCount, $card->effectiveBlocks()->count());
        }
    }

    /** 4. Todos os prêmios aparecem fora das grades numéricas. */
    public function test_prizes_are_outside_numeric_grids(): void
    {
        $game = $this->createGame(3);
        $card = app(CardGenerator::class)->createCard($game);

        $response = $this->get("/games/{$game->id}/cards/print?card={$card->code}&per_page=1");
        $response->assertOk();
        $html = $response->content();

        // A área de prêmios existe e lista todos os prêmios
        $this->assertStringContainsString('PRÊMIOS DO BINGO', $html);
        foreach (range(1, 3) as $i) {
            $this->assertStringContainsString("Prêmio {$i}", $html);
        }

        // Nenhum nome de prêmio aparece dentro das tabelas das grades
        preg_match_all('/<table class="card-grid">.*?<\/table>/s', $html, $grids);
        $this->assertCount(3, $grids[0]);
        foreach ($grids[0] as $gridHtml) {
            $this->assertStringNotContainsString('Prêmio 1', $gridHtml);
            $this->assertStringNotContainsString('Prêmio 2', $gridHtml);
            $this->assertStringNotContainsString('Prêmio 3', $gridHtml);
        }
    }

    /** 5. Cada bloco está associado ao prêmio correto. */
    public function test_blocks_are_associated_to_correct_prizes(): void
    {
        $game = $this->createGame(4);
        $card = app(CardGenerator::class)->createCard($game);
        $prizes = $game->prizes()->orderBy('sort_order')->get();

        $blocks = $card->blocks()->orderBy('position')->get();
        foreach ($blocks as $index => $block) {
            $this->assertSame($prizes[$index]->id, $block->prize_id);
            $this->assertSame($prizes[$index]->name, $block->prize_name);
            $this->assertSame($index + 1, $block->position);
            $this->assertSame('B'.($index + 1), $block->label());
        }

        // Relação inversa: o prêmio conhece o bloco correspondente
        $this->assertSame($blocks[2]->id, $prizes[2]->blocks()->where('card_id', $card->id)->first()->id);
    }

    /** 6. Os números seguem as regras do formato. */
    public function test_block_numbers_follow_format_rules(): void
    {
        // 75 bolas: 5x5, colunas B-I-N-G-O nas faixas corretas, centro livre
        $game75 = $this->createGame(1, '75');
        $card75 = app(CardGenerator::class)->createCard($game75);
        $grid = $card75->blocks()->first()->grid;

        $this->assertNull($grid[2][2]);
        foreach (range(0, 4) as $col) {
            foreach (range(0, 4) as $row) {
                $value = $grid[$row][$col];
                if ($row === 2 && $col === 2) {
                    continue;
                }
                $this->assertGreaterThanOrEqual($col * 15 + 1, $value);
                $this->assertLessThanOrEqual($col * 15 + 15, $value);
            }
        }

        // 90 bolas: 3 linhas x 9 colunas, 15 números, 5 por linha
        $game90 = $this->createGame(1, '90');
        $card90 = app(CardGenerator::class)->createCard($game90);
        $grid90 = $card90->blocks()->first()->grid;

        $this->assertCount(3, $grid90);
        $this->assertCount(9, $grid90[0]);
        $total = 0;
        foreach ($grid90 as $row) {
            $rowNumbers = collect($row)->filter()->count();
            $this->assertSame(5, $rowNumbers, 'Cada linha do bingo de 90 bolas deve ter 5 números.');
            $total += $rowNumbers;
        }
        $this->assertSame(15, $total);
    }

    /** 7. A arte pode ser exportada e importada sem modificar os dados do jogo. */
    public function test_art_export_and_import_do_not_modify_game_data(): void
    {
        Storage::fake('public');
        $game = $this->createGame(2);
        $card = app(CardGenerator::class)->createCard($game);

        $gridBefore = $card->grid;
        $blocksBefore = $card->blocks()->orderBy('position')->get()->map(fn ($b) => $b->grid)->all();
        $prizeLinksBefore = $card->blocks()->orderBy('position')->pluck('prize_id')->all();

        // Exporta a arte (SVG vetorial A4) da cartela e o modelo-base
        $this->get("/games/{$game->id}/cards/art?card={$card->code}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
        $this->get("/games/{$game->id}/cards/art/model")->assertOk();

        // Importa uma arte personalizada como modelo
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm"><rect width="210" height="297" fill="#fff"/></svg>';
        $this->post("/games/{$game->id}/card-templates", [
            'name' => 'Arte Teste',
            'art' => UploadedFile::fake()->createWithContent('arte.svg', $svg, 'image/svg+xml'),
        ])->assertRedirect();

        $this->assertDatabaseCount('card_templates', 1);

        // Dados do jogo continuam intactos
        $card->refresh();
        $this->assertSame($gridBefore, $card->grid);
        $this->assertSame($blocksBefore, $card->blocks()->orderBy('position')->get()->map(fn ($b) => $b->grid)->all());
        $this->assertSame($prizeLinksBefore, $card->blocks()->orderBy('position')->pluck('prize_id')->all());
        $this->assertSame(2, $card->effectiveBlocks()->count());
    }

    /** 8. O PDF mantém as dimensões A4. */
    public function test_pdf_keeps_a4_dimensions(): void
    {
        $game = $this->createGame(2);
        app(CardGenerator::class)->createCard($game);

        $response = $this->get("/games/{$game->id}/cards/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        // A4 = 595.28 x 841.89 pontos
        $content = $response->streamedContent();
        $this->assertStringContainsString('595.28', $content);
        $this->assertStringContainsString('841.89', $content);
    }

    /** 9. A pré-visualização corresponde ao documento impresso (mesmos dados). */
    public function test_preview_matches_printed_document(): void
    {
        $game = $this->createGame(3);
        $card = app(CardGenerator::class)->createCard($game);

        $preview = $this->get("/games/{$game->id}/cards/print?card={$card->code}&per_page=1");
        $preview->assertOk();

        // A prévia contém o código e TODOS os números de TODOS os blocos
        $html = $preview->content();
        $this->assertStringContainsString($card->code, $html);
        foreach ($card->blocks as $block) {
            $this->assertStringContainsString($block->label(), $html);
            foreach ($block->grid as $row) {
                foreach ($row as $number) {
                    if ($number !== null) {
                        $this->assertStringContainsString('>'.$number.'</td>', $html);
                    }
                }
            }
        }

        // O PDF usa exatamente a mesma cartela e blocos (mesma fonte de dados)
        $this->get("/games/{$game->id}/cards/pdf?card={$card->code}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** 10. Cartelas físicas e blocos na boa são contabilizados separadamente. */
    public function test_physical_cards_and_blocks_are_counted_separately(): void
    {
        $game = $this->createGame(4);
        $card = app(CardGenerator::class)->createCard($game);
        $this->post("/games/{$game->id}/start");

        // Uma cartela física com 4 blocos = 1 cartela, 4 oportunidades
        $state = $this->getJson("/games/{$game->id}/state")->assertOk()->json();
        $this->assertSame(1, $state['cards_summary']['total_in_play']);
        $this->assertSame(4, $state['cards_summary']['blocks_in_play']);

        // Completa a primeira linha do bloco 1 (regra: uma linha)
        $row = collect($card->blocks()->where('position', 1)->first()->grid[0])->filter()->values();
        foreach ($row->take(4) as $number) {
            $this->postJson("/games/{$game->id}/draws", ['number' => $number])->assertOk();
        }

        $state = $this->getJson("/games/{$game->id}/state")->json();
        $summary = $state['cards_summary'];
        $this->assertSame(1, $summary['total_in_play']);
        $this->assertSame(4, $summary['blocks_in_play']);
        $this->assertSame(1, $summary['na_boa'], 'Apenas a cartela física do bloco na boa.');
        $this->assertSame(1, $summary['na_boa_blocks'], 'Apenas um bloco está na boa.');

        $block = $summary['na_boa_blocks_list'][0];
        $this->assertSame($card->code, $block['code']);
        $this->assertSame('B1', $block['block']);
        $this->assertSame('Prêmio 1', $block['prize']);
        $this->assertSame(1, $block['missing']);
    }

    /** 11. A validação dos ganhadores usa os números realmente sorteados. */
    public function test_winner_validation_uses_actually_drawn_numbers_per_block(): void
    {
        $game = $this->createGame(2);
        $card = app(CardGenerator::class)->createCard($game);
        $this->post("/games/{$game->id}/start");

        $prize1 = $game->prizes()->where('sort_order', 1)->first();
        $prize2 = $game->prizes()->where('sort_order', 2)->first();
        $block1 = $card->blocks()->where('position', 1)->first();
        $block2 = $card->blocks()->where('position', 2)->first();

        // Sem pedras suficientes: bloco 1 não vence
        $row = collect($block1->grid[0])->filter()->values();
        $this->postJson("/games/{$game->id}/draws", ['number' => $row[0]])->assertOk();
        $this->postJson("/games/{$game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $prize1->id,
        ])->assertStatus(422)->assertJsonPath('type', 'error');
        $this->assertDatabaseCount('winners', 0);

        // Registra os números restantes da primeira linha do bloco 1
        foreach ($row->skip(1) as $number) {
            $this->postJson("/games/{$game->id}/draws", ['number' => $number])->assertOk();
        }

        // Bloco 1 vence o prêmio 1; o bloco 2 (números diferentes) não vence
        $response = $this->postJson("/games/{$game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $prize1->id,
        ])->assertOk();
        $this->assertStringContainsString('TEMOS UM GANHADOR!', $response->json('message'));

        $this->postJson("/games/{$game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $prize2->id,
        ])->assertStatus(422);

        $winner = $game->winners()->first();
        $this->assertSame($block1->id, $winner->card_block_id);
        $this->assertSame(CardBlock::STATUS_WINNER, $block1->refresh()->status);
        $this->assertSame(CardBlock::STATUS_ACTIVE, $block2->refresh()->status);
        $this->assertSame(Card::STATUS_WINNER, $card->refresh()->status);
    }

    /** 12. O sistema preserva dados e modelos existentes (cartelas legadas). */
    public function test_legacy_cards_are_preserved_when_blocks_are_created(): void
    {
        $game = $this->createGame(2);
        $card = app(CardGenerator::class)->createCard($game);
        $originalGrid = $card->grid;

        // Simula cartela emitida antes do recurso de blocos
        $card->blocks()->delete();
        $card->refresh();

        $updated = app(CardGenerator::class)->syncBlocks($game);
        $this->assertSame(1, $updated);

        $blocks = $card->blocks()->orderBy('position')->get();
        $this->assertCount(2, $blocks);

        // O bloco 1 preserva a grade original emitida — nada muda em silêncio
        $this->assertSame($originalGrid, $blocks[0]->grid);
        $this->assertNotSame($originalGrid, $blocks[1]->grid);

        // Snapshot de prêmios congelado na emissão continua intacto
        $this->get("/games/{$game->id}/cards/print?card={$card->code}")->assertOk();
        $snapshot = $card->refresh()->prize_snapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame('Prêmio 1', $snapshot[0]['name']);
    }

    /** Bloco sem prêmio correspondente não valida prêmio novo (gera novo lote). */
    public function test_prize_without_block_requires_new_batch(): void
    {
        $game = $this->createGame(1);
        $card = app(CardGenerator::class)->createCard($game);

        // Prêmio adicionado depois da emissão do lote
        $newPrize = $game->prizes()->create([
            'name' => 'Prêmio Novo',
            'rule' => Prize::RULE_FULL_CARD,
            'sort_order' => 2,
        ]);

        $this->post("/games/{$game->id}/start");
        foreach (collect($card->blocks()->first()->grid[0])->filter()->values() as $number) {
            $this->postJson("/games/{$game->id}/draws", ['number' => $number])->assertOk();
        }

        $this->postJson("/games/{$game->id}/winners/validate", [
            'card_code' => $card->code,
            'prize_id' => $newPrize->id,
        ])->assertStatus(422)->assertJsonPath('type', 'error');
    }
}

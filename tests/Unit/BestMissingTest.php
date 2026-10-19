<?php

namespace Tests\Unit;

use App\Models\Prize;
use App\Services\WinRuleValidator;
use PHPUnit\Framework\TestCase;

class BestMissingTest extends TestCase
{
    private WinRuleValidator $validator;

    /**
     * Cartela 5x5 com casa livre no centro (formato 75 bolas).
     */
    private function card(): array
    {
        return [
            [1, 16, 31, 46, 61],
            [2, 17, 32, 47, 62],
            [3, 18, null, 48, 63],
            [4, 19, 34, 49, 64],
            [5, 20, 35, 50, 65],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new WinRuleValidator;
    }

    public function test_full_card_counts_all_unmarked(): void
    {
        $drawn = [1, 16, 31, 46, 61, 2, 17];

        $missing = $this->validator->bestMissing($this->card(), $drawn, Prize::RULE_FULL_CARD);

        $this->assertSame(17, count($missing)); // 24 números - 7 sorteados (casa livre não conta)
    }

    public function test_one_line_uses_best_row(): void
    {
        // Sorteou 4 da primeira linha: falta 1 (melhor configuração)
        $drawn = [1, 16, 31, 46, 2, 17];

        $missing = $this->validator->bestMissing($this->card(), $drawn, Prize::RULE_ONE_LINE);

        $this->assertSame([61], $missing);
    }

    public function test_one_line_prefers_free_center_row(): void
    {
        // Linha central tem casa livre: faltam 3, mas com 2 sorteados faltam apenas 2
        $drawn = [3, 18];

        $missing = $this->validator->bestMissing($this->card(), $drawn, Prize::RULE_ONE_LINE);

        $this->assertSame([48, 63], array_values($missing));
    }

    public function test_two_lines_sums_best_pair_without_double_counting(): void
    {
        // Duas melhores linhas completas menos o que já saiu
        $drawn = [1, 16, 31, 46, 61, 3, 18];

        $missing = $this->validator->bestMissing($this->card(), $drawn, Prize::RULE_TWO_LINES);

        // Linha 1 completa; linha central: faltam 48 e 63
        $this->assertSame([48, 63], array_values($missing));
    }

    public function test_two_lines_missing_count_reflects_best_pair(): void
    {
        $drawn = [1, 2, 3, 4, 5];

        // Melhores linhas: central falta 3 (18,48,63), primeira e segunda faltam 4 → 3+4=7
        $this->assertSame(7, $this->validator->missingCount($this->card(), $drawn, Prize::RULE_TWO_LINES));
    }

    public function test_four_corners(): void
    {
        $drawn = [1, 61, 65];

        $this->assertSame([5], $this->validator->bestMissing($this->card(), $drawn, Prize::RULE_FOUR_CORNERS));
    }

    public function test_custom_pattern_counts_only_selected_cells(): void
    {
        $pattern = [
            [false, false, true, false, false],
            [false, false, true, false, false],
            [false, false, true, false, false],
            [false, false, true, false, false],
            [false, false, true, false, false],
        ];

        $drawn = [31, 32, 35];

        $missing = $this->validator->bestMissing($this->card(), $drawn, Prize::RULE_CUSTOM_PATTERN, $pattern);

        $this->assertSame([34], $missing); // casa livre no meio conta automaticamente
    }

    public function test_complete_card_missing_zero(): void
    {
        $drawn = [1, 16, 31, 46, 61];

        $this->assertSame([], $this->validator->bestMissing($this->card(), $drawn, Prize::RULE_ONE_LINE));
        $this->assertSame(0, $this->validator->missingCount($this->card(), $drawn, Prize::RULE_ONE_LINE));
    }
}

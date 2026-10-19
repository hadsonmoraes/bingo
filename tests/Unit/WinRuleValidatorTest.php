<?php

namespace Tests\Unit;

use App\Services\WinRuleValidator;
use PHPUnit\Framework\TestCase;

class WinRuleValidatorTest extends TestCase
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

    public function test_full_card_requires_all_numbers(): void
    {
        $all = [1, 2, 3, 4, 5, 16, 17, 18, 19, 20, 31, 32, 34, 35, 46, 47, 48, 49, 50, 61, 62, 63, 64, 65];

        $this->assertTrue($this->validator->validate($this->card(), $all, 'full_card'));
        $this->assertFalse($this->validator->validate($this->card(), array_diff($all, [65]), 'full_card'));
    }

    public function test_one_line(): void
    {
        $drawn = [1, 16, 31, 46, 61];

        $this->assertTrue($this->validator->validate($this->card(), $drawn, 'one_line'));
        $this->assertFalse($this->validator->validate($this->card(), [1, 16, 31, 46], 'one_line'));
    }

    public function test_two_lines_requires_two_complete_rows(): void
    {
        $drawn = [1, 16, 31, 46, 61, 2, 17, 32, 47, 62];

        $this->assertTrue($this->validator->validate($this->card(), $drawn, 'two_lines'));
        $this->assertFalse($this->validator->validate($this->card(), [1, 16, 31, 46, 61], 'two_lines'));
    }

    public function test_two_lines_with_center_row_counts_free_space(): void
    {
        // Linha do meio completa graças à casa livre
        $this->assertTrue($this->validator->validate($this->card(), [3, 18, 48, 63, 1, 16, 31, 46, 61], 'two_lines'));
    }

    public function test_four_corners(): void
    {
        $this->assertTrue($this->validator->validate($this->card(), [1, 61, 5, 65], 'four_corners'));
        $this->assertFalse($this->validator->validate($this->card(), [1, 61, 5], 'four_corners'));
    }

    public function test_custom_pattern(): void
    {
        $pattern = [
            [false, false, true, false, false],
            [false, false, true, false, false],
            [false, false, true, false, false],
            [false, false, true, false, false],
            [false, false, true, false, false],
        ];

        // Coluna do meio: 31, 32, livre, 34, 35
        $this->assertTrue($this->validator->validate($this->card(), [31, 32, 34, 35], 'custom_pattern', $pattern));
        $this->assertFalse($this->validator->validate($this->card(), [31, 32, 34], 'custom_pattern', $pattern));
    }

    public function test_empty_pattern_is_invalid(): void
    {
        $this->assertFalse($this->validator->validate($this->card(), [1], 'custom_pattern', null));
    }

    public function test_unknown_rule_is_invalid(): void
    {
        $this->assertFalse($this->validator->validate($this->card(), [1], 'nonexistent'));
    }
}

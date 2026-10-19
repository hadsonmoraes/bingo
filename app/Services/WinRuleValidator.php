<?php

namespace App\Services;

use App\Models\Prize;

class WinRuleValidator
{
    /**
     * Valida se uma cartela cumpre a regra de um prêmio considerando
     * exclusivamente os números registrados na rodada informada.
     *
     * @param  array  $grid  grade da cartela (linhas x colunas, null = casa livre)
     * @param  array<int, int>  $drawnNumbers  números sorteados na rodada
     */
    public function validate(array $grid, array $drawnNumbers, string $rule, ?array $pattern = null): bool
    {
        $drawn = array_map('intval', $drawnNumbers);

        return match ($rule) {
            Prize::RULE_FULL_CARD => $this->checkFullCard($grid, $drawn),
            Prize::RULE_ONE_LINE => $this->countCompleteLines($grid, $drawn) >= 1,
            Prize::RULE_TWO_LINES => $this->countCompleteLines($grid, $drawn) >= 2,
            Prize::RULE_FOUR_CORNERS => $this->checkFourCorners($grid, $drawn),
            Prize::RULE_CUSTOM_PATTERN => $this->checkPattern($grid, $drawn, $pattern),
            default => false,
        };
    }

    /**
     * Retorna os números que faltam para completar a vitória usando a
     * configuração válida que exige menos pedras adicionais.
     *
     * @return array<int, int> números ainda não sorteados na melhor configuração
     */
    public function bestMissing(array $grid, array $drawnNumbers, string $rule, ?array $pattern = null): array
    {
        $drawn = array_map('intval', $drawnNumbers);

        return match ($rule) {
            Prize::RULE_FULL_CARD => $this->missingFullCard($grid, $drawn),
            Prize::RULE_ONE_LINE => $this->missingBestLines($grid, $drawn, 1),
            Prize::RULE_TWO_LINES => $this->missingBestLines($grid, $drawn, 2),
            Prize::RULE_FOUR_CORNERS => $this->missingFourCorners($grid, $drawn),
            Prize::RULE_CUSTOM_PATTERN => $this->missingPattern($grid, $drawn, $pattern),
            default => [],
        };
    }

    /**
     * Quantos números faltam para a melhor configuração da regra.
     */
    public function missingCount(array $grid, array $drawnNumbers, string $rule, ?array $pattern = null): int
    {
        return count($this->bestMissing($grid, $drawnNumbers, $rule, $pattern));
    }

    private function unmarkedNumber(mixed $cell, array $drawn): ?int
    {
        if ($cell === null) {
            return null; // casa livre
        }

        return in_array((int) $cell, $drawn, true) ? null : (int) $cell;
    }

    private function missingFullCard(array $grid, array $drawn): array
    {
        $missing = [];
        foreach ($grid as $row) {
            foreach ($row as $cell) {
                $number = $this->unmarkedNumber($cell, $drawn);
                if ($number !== null) {
                    $missing[] = $number;
                }
            }
        }

        return $missing;
    }

    /**
     * Melhor combinação de N linhas: considera cada forma possível de
     * completar a condição e devolve a que exige menos números.
     */
    private function missingBestLines(array $grid, array $drawn, int $linesNeeded): array
    {
        $candidates = [];

        foreach ($grid as $row) {
            $missing = [];
            $hasNumbers = false;

            foreach ($row as $cell) {
                if ($cell !== null) {
                    $hasNumbers = true;
                }
                $number = $this->unmarkedNumber($cell, $drawn);
                if ($number !== null) {
                    $missing[] = $number;
                }
            }

            // Linhas vazias (sem nenhum número) não são configurações válidas
            if ($hasNumbers) {
                $candidates[] = $missing;
            }
        }

        usort($candidates, fn ($a, $b) => count($a) <=> count($b));

        $selected = array_slice($candidates, 0, $linesNeeded);
        if (count($selected) < $linesNeeded) {
            return [];
        }

        $merged = [];
        foreach ($selected as $missing) {
            foreach ($missing as $number) {
                if (! in_array($number, $merged, true)) {
                    $merged[] = $number;
                }
            }
        }

        return $merged;
    }

    private function missingFourCorners(array $grid, array $drawn): array
    {
        $rows = count($grid);
        $cols = count($grid[0] ?? []);
        if ($rows < 2 || $cols < 2) {
            return [];
        }

        $corners = [
            $grid[0][0],
            $grid[0][$cols - 1],
            $grid[$rows - 1][0],
            $grid[$rows - 1][$cols - 1],
        ];

        $missing = [];
        foreach ($corners as $corner) {
            $number = $this->unmarkedNumber($corner, $drawn);
            if ($number !== null) {
                $missing[] = $number;
            }
        }

        return $missing;
    }

    private function missingPattern(array $grid, array $drawn, ?array $pattern): array
    {
        if (empty($pattern)) {
            return [];
        }

        $missing = [];
        foreach ($pattern as $rowIndex => $row) {
            foreach ($row as $colIndex => $selected) {
                if (! $selected) {
                    continue;
                }
                $cell = $grid[$rowIndex][$colIndex] ?? null;
                $number = $this->unmarkedNumber($cell, $drawn);
                if ($number !== null) {
                    $missing[] = $number;
                }
            }
        }

        return $missing;
    }

    private function isMarked(mixed $cell, array $drawn): bool
    {
        if ($cell === null) {
            return true; // casa livre
        }

        return in_array((int) $cell, $drawn, true);
    }

    private function checkFullCard(array $grid, array $drawn): bool
    {
        foreach ($grid as $row) {
            foreach ($row as $cell) {
                if (! $this->isMarked($cell, $drawn)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function countCompleteLines(array $grid, array $drawn): int
    {
        $complete = 0;

        foreach ($grid as $row) {
            $allMarked = true;
            $hasNumbers = false;

            foreach ($row as $cell) {
                if ($cell !== null) {
                    $hasNumbers = true;
                }
                if (! $this->isMarked($cell, $drawn)) {
                    $allMarked = false;
                }
            }

            if ($hasNumbers && $allMarked) {
                $complete++;
            }
        }

        return $complete;
    }

    private function checkFourCorners(array $grid, array $drawn): bool
    {
        $rows = count($grid);
        $cols = count($grid[0] ?? []);
        if ($rows < 2 || $cols < 2) {
            return false;
        }

        $corners = [
            $grid[0][0],
            $grid[0][$cols - 1],
            $grid[$rows - 1][0],
            $grid[$rows - 1][$cols - 1],
        ];

        foreach ($corners as $corner) {
            if (! $this->isMarked($corner, $drawn)) {
                return false;
            }
        }

        return true;
    }

    private function checkPattern(array $grid, array $drawn, ?array $pattern): bool
    {
        if (empty($pattern)) {
            return false;
        }

        foreach ($pattern as $rowIndex => $row) {
            foreach ($row as $colIndex => $selected) {
                if (! $selected) {
                    continue;
                }
                $cell = $grid[$rowIndex][$colIndex] ?? null;
                if ($cell !== null && ! in_array((int) $cell, $drawn, true)) {
                    return false;
                }
            }
        }

        return true;
    }
}

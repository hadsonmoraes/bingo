<?php

namespace App\Services;

use App\Models\Card;
use App\Models\CardBlock;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class CardGenerator
{
    /**
     * Gera uma nova cartela para a partida e retorna o model persistido.
     * Para cada prêmio cadastrado no evento é criado um bloco numérico
     * independente, válido e identificado — 1 prêmio = 1 bloco.
     * Os números são gerados aleatoriamente apenas no momento da criação
     * da cartela — nunca durante o sorteio das pedras.
     */
    public function createCard(Game $game, ?string $holder = null, int $batch = 1): Card
    {
        return DB::transaction(function () use ($game, $holder, $batch) {
            $prizes = $game->prizes()->orderBy('sort_order')->orderBy('id')->get();

            // Combinações já existentes no evento (grades das cartelas e de todos os blocos)
            $existingSignatures = $this->existingSignatures($game);

            $blocks = [];
            foreach ($prizes as $index => $prize) {
                $blocks[] = [
                    'prize_id' => $prize->id,
                    'prize_name' => $prize->name,
                    'prize_rule_label' => $prize->ruleLabel(),
                    'rule' => $prize->rule,
                    'pattern' => $prize->pattern,
                    'position' => $index + 1,
                    'grid' => $this->generateUniqueGrid($game, $existingSignatures),
                    'status' => CardBlock::STATUS_ACTIVE,
                ];
            }

            $code = $this->nextCode($game);

            $card = $game->cards()->create([
                'code' => $code,
                // Mantém a primeira grade na coluna legada (compatibilidade)
                'grid' => $blocks[0]['grid'] ?? $this->generateUniqueGrid($game, $existingSignatures),
                'holder' => $holder,
                'batch' => $batch,
                'status' => Card::STATUS_AVAILABLE,
            ]);

            foreach ($blocks as $block) {
                $card->blocks()->create($block);
            }

            return $card;
        });
    }

    /**
     * Cria os blocos de cartelas emitidas antes do recurso de blocos.
     * O primeiro bloco preserva a grade original já impressa; os
     * prêmios restantes recebem blocos novos e válidos.
     */
    public function syncBlocks(Game $game): int
    {
        $prizes = $game->prizes()->orderBy('sort_order')->orderBy('id')->get();
        if ($prizes->isEmpty()) {
            return 0;
        }

        $updated = 0;

        $game->cards()->whereDoesntHave('blocks')
            ->chunkById(100, function ($cards) use ($game, $prizes, &$updated) {
                foreach ($cards as $card) {
                    $existingSignatures = $this->existingSignatures($game);

                    foreach ($prizes as $index => $prize) {
                        $block = [
                            'prize_id' => $prize->id,
                            'prize_name' => $prize->name,
                            'prize_rule_label' => $prize->ruleLabel(),
                            'rule' => $prize->rule,
                            'pattern' => $prize->pattern,
                            'position' => $index + 1,
                            'status' => CardBlock::STATUS_ACTIVE,
                        ];

                        if ($index === 0) {
                            // Preserva a grade emitida no bloco 1
                            $block['grid'] = $card->grid;
                        } else {
                            $block['grid'] = $this->generateUniqueGrid($game, $existingSignatures);
                        }

                        $card->blocks()->create($block);
                    }

                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Assinaturas de todas as combinações já existentes no evento.
     */
    private function existingSignatures(Game $game)
    {
        $signatures = $game->cards()->get(['grid'])
            ->map(fn ($card) => $this->signature($card->grid));

        $cardIds = $game->cards()->pluck('id');
        if ($cardIds->isNotEmpty()) {
            $signatures = $signatures->merge(
                CardBlock::whereIn('card_id', $cardIds)
                    ->get(['grid'])
                    ->map(fn ($block) => $this->signature($block->grid))
            );
        }

        return $signatures->flip();
    }

    /**
     * Gera grades até obter uma combinação inédita no evento.
     */
    private function generateUniqueGrid(Game $game, $existingSignatures): array
    {
        $grid = $this->generateGrid($game);
        for ($attempt = 0; $attempt < 50; $attempt++) {
            if (! $existingSignatures->has($this->signature($grid))) {
                break;
            }
            $grid = $this->generateGrid($game);
        }

        $existingSignatures[$this->signature($grid)] = true;

        return $grid;
    }

    /**
     * Assinatura da grade para detectar cartelas integralmente duplicadas.
     */
    private function signature(array $grid): string
    {
        return md5(json_encode($grid));
    }

    public function generateGrid(Game $game): array
    {
        return match ($game->card_format) {
            '90' => $this->generate90($game),
            'custom' => $this->generateCustom($game),
            default => $this->generate75($game),
        };
    }

    /**
     * Cartela 75 bolas: 5 colunas (B-I-N-G-O) x 5 linhas, centro livre.
     */
    private function generate75(Game $game): array
    {
        $grid = array_fill(0, 5, array_fill(0, 5, null));

        for ($col = 0; $col < 5; $col++) {
            $start = $game->number_min + $col * 15;
            $pool = range($start, $start + 14);
            shuffle($pool);
            $numbers = array_slice($pool, 0, 5);
            sort($numbers);

            foreach ($numbers as $row => $number) {
                $grid[$row][$col] = $number;
            }
        }

        $grid[2][2] = null; // casa livre

        return $grid;
    }

    /**
     * Cartela 90 bolas: 3 linhas x 9 colunas, 5 números por linha.
     */
    private function generate90(Game $game): array
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $grid = $this->tryGenerate90($game);

            if ($grid !== null) {
                return $grid;
            }
        }

        return $this->generate90Deterministic($game);
    }

    private function tryGenerate90(Game $game): ?array
    {
        // Quantidade de números por coluna (1 a 3, totalizando 15)
        $counts = array_fill(0, 9, 1);
        $extra = 6;
        while ($extra > 0) {
            $col = random_int(0, 8);
            if ($counts[$col] < 3) {
                $counts[$col]++;
                $extra--;
            }
        }

        $grid = array_fill(0, 3, array_fill(0, 9, null));
        $capacity = [5, 5, 5];

        for ($col = 0; $col < 9; $col++) {
            $start = max($col * 10 + 1, $game->number_min);
            $end = min($col * 10 + 10, $game->number_max);

            $pool = range($start, $end);
            shuffle($pool);
            $numbers = array_slice($pool, 0, $counts[$col]);
            sort($numbers);

            // Coloca os números da coluna nas linhas com mais capacidade restante,
            // sem repetir linha na mesma coluna (evita sobrescrever números)
            foreach ($numbers as $number) {
                arsort($capacity);
                $row = null;
                foreach (array_keys($capacity) as $candidate) {
                    if ($capacity[$candidate] > 0 && $grid[$candidate][$col] === null) {
                        $row = $candidate;
                        break;
                    }
                }

                if ($row === null) {
                    return null;
                }

                $grid[$row][$col] = $number;
                $capacity[$row]--;
            }
        }

        foreach ($capacity as $remaining) {
            if ($remaining !== 0) {
                return null;
            }
        }

        return $grid;
    }

    private function generate90Deterministic(Game $game): array
    {
        $grid = array_fill(0, 3, array_fill(0, 9, null));

        for ($col = 0; $col < 9; $col++) {
            $start = max($col * 10 + 1, $game->number_min);
            $end = min($col * 10 + 10, $game->number_max);
            $count = min(3, max(1, count(range($start, $end))));

            $pool = range($start, $end);
            shuffle($pool);
            $numbers = array_slice($pool, 0, $count);
            sort($numbers);

            foreach ($numbers as $row => $number) {
                $grid[$row % 3][$col] = $number;
            }
        }

        return $grid;
    }

    /**
     * Formato personalizado: 5x5 preenchido com números aleatórios do intervalo.
     */
    private function generateCustom(Game $game): array
    {
        $pool = range($game->number_min, $game->number_max);
        shuffle($pool);
        $numbers = array_slice($pool, 0, 25);

        $grid = [];
        for ($row = 0; $row < 5; $row++) {
            $grid[] = array_slice($numbers, $row * 5, 5);
        }

        return $grid;
    }

    private function nextCode(Game $game): string
    {
        $last = (int) ($game->cards()->orderByDesc('id')->value('id') ?? 0);

        do {
            $last++;
            $code = 'C-'.str_pad((string) $last, 4, '0', STR_PAD_LEFT);
        } while ($game->cards()->where('code', $code)->exists());

        return $code;
    }
}

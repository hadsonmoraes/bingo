<?php

namespace App\Services;

use App\Models\Card;
use App\Models\CardBlock;
use App\Models\Game;
use App\Models\Prize;
use Illuminate\Support\Collection;

class GameStateService
{
    /**
     * Prêmios na ordem de disputa: do último (prêmios menores) para o
     * primeiro (prêmio principal). É a ordem usada pela disputa da rodada.
     *
     * @return Collection<int, Prize>
     */
    public function prizeSequence(Game $game): Collection
    {
        // reorder() limpa o orderBy padrão da relação (sort_order ASC)
        return $game->prizes()->reorder()->orderByDesc('sort_order')->orderByDesc('id')->get();
    }

    /**
     * Prêmio em disputa como model, respeitando o ponteiro manual
     * ("Avançar prêmio") e a sequência do último para o primeiro.
     */
    public function currentPrize(Game $game): ?Prize
    {
        return $this->resolvePrizes($this->prizeSequence($game)->load('winner'), $game->current_prize_id)[0];
    }

    /**
     * Próximo prêmio sem ganhador na sequência de disputa após o atual.
     */
    public function nextPrize(Game $game): ?Prize
    {
        return $this->resolvePrizes($this->prizeSequence($game)->load('winner'), $game->current_prize_id)[1];
    }

    /**
     * Prêmio em disputa (atual) e o próximo sem ganhador na sequência,
     * respeitando o ponteiro manual do jogo. Quando o trecho a partir do
     * ponteiro já está todo ganho, recua para o primeiro prêmio ainda
     * em disputa — a mesma regra usada pela tela do operador, pelo
     * telão e pelo endpoint de polling.
     *
     * @param  Collection<int, Prize>  $sequence  com a relação "winner" carregada
     * @return array{0: ?Prize, 1: ?Prize}
     */
    private function resolvePrizes(Collection $sequence, ?int $pointerId): array
    {
        $started = $pointerId === null;
        $current = null;

        foreach ($sequence as $prize) {
            if (! $started) {
                if ($prize->id !== $pointerId) {
                    continue;
                }
                $started = true;
            }

            if ($prize->winner === null) {
                $current = $prize;
                break;
            }
        }

        if ($current === null) {
            // Ponteiro no fim da sequência com prêmios seguintes já
            // ganhos: recua para o primeiro prêmio ainda sem ganhador.
            foreach ($sequence as $prize) {
                if ($prize->winner === null) {
                    $current = $prize;
                    break;
                }
            }
        }

        if ($current === null) {
            return [null, null];
        }

        $reached = false;
        $next = null;
        foreach ($sequence as $prize) {
            if (! $reached) {
                if ($prize->id === $current->id) {
                    $reached = true;
                }

                continue;
            }

            if ($prize->winner === null) {
                $next = $prize;
                break;
            }
        }

        return [$current, $next];
    }

    /**
     * Monta o estado completo da partida — usado pela tela pública,
     * pela tela do operador e pelo endpoint de polling.
     */
    public function all(Game $game): array
    {
        $draws = $game->draws()->orderBy('sequence')->get();
        $drawnNumbers = $draws->pluck('number')->all();
        $last = $draws->last();

        // Prêmios com os ganhadores carregados de uma vez (evita uma
        // consulta por prêmio a cada polling do telão).
        $sequence = $this->prizeSequence($game)->load('winner.card');
        $total = $sequence->count();

        $prizes = $sequence->map(function (Prize $prize, int $index) use ($total) {
            $winner = $prize->winner;

            return [
                'id' => $prize->id,
                'name' => $prize->name,
                'description' => $prize->description,
                'estimated_value' => $prize->estimated_value,
                'rule' => $prize->rule,
                'rule_label' => $prize->ruleLabel(),
                'pattern' => $prize->pattern,
                'position' => $index + 1,
                'total' => $total,
                'winner' => $winner ? [
                    'name' => $winner->winner_name,
                    'card_code' => $winner->card?->code,
                    'at' => $winner->validated_at?->format('H:i:s'),
                ] : null,
            ];
        })->all();

        // Prêmio em disputa e o próximo: um único cálculo (o mesmo do
        // "Avançar prêmio") respeita o ponteiro manual e a sequência
        // automática do último para o primeiro prêmio.
        [$currentModel, $nextModel] = $this->resolvePrizes($sequence, $game->current_prize_id);

        $indexById = array_column($prizes, 'id');

        $currentPrize = $currentModel ? $prizes[array_search($currentModel->id, $indexById)] : null;
        $nextPrize = $nextModel ? $prizes[array_search($nextModel->id, $indexById)] : null;

        $winners = $game->winners()->with(['prize', 'card', 'block'])->get()->map(fn ($winner) => [
            'id' => $winner->id,
            'prize_name' => $winner->prize?->name,
            'card_code' => $winner->card?->code,
            'block' => $winner->block?->label(),
            'winner_name' => $winner->winner_name,
            'validated_at' => $winner->validated_at?->format('H:i:s'),
        ])->all();

        return [
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'organization_name' => $game->organization_name,
                'status' => $game->status,
                'number_min' => $game->number_min,
                'number_max' => $game->number_max,
                'use_letters' => $game->use_letters,
                'card_format' => $game->card_format,
                'na_boa_threshold' => $game->na_boa_threshold,
                'event_date' => $game->event_date?->format('d/m/Y'),
                'started_at' => $game->started_at?->format('d/m/Y H:i'),
                'finished_at' => $game->finished_at?->format('d/m/Y H:i'),
            ],
            'letters' => $game->letters(),
            'drawn' => $drawnNumbers,
            'total_numbers' => $game->totalNumbers(),
            'drawn_count' => count($drawnNumbers),
            'remaining_count' => $game->totalNumbers() - count($drawnNumbers),
            'last' => $last ? [
                'number' => $last->number,
                'letter' => $last->letter,
                'sequence' => $last->sequence,
                'at' => $last->created_at?->format('H:i:s'),
            ] : null,
            'history' => $draws->reverse()->map(fn ($draw) => [
                'number' => $draw->number,
                'letter' => $draw->letter,
                'sequence' => $draw->sequence,
                'at' => $draw->created_at?->format('H:i:s'),
            ])->values()->all(),
            'prizes' => $prizes,
            'current_prize' => $currentPrize,
            'next_prize' => $nextPrize ? [
                'id' => $nextPrize['id'],
                'name' => $nextPrize['name'],
                'rule_label' => $nextPrize['rule_label'],
            ] : null,
            'winners' => $winners,
            'cards_summary' => $this->cardsSummary($game, $drawnNumbers, $currentPrize),
        ];
    }

    /**
     * Painel de cartelas e blocos na boa: atualizado após cada pedra
     * registrada. Distingue cartelas físicas de blocos numéricos — uma
     * cartela com quatro blocos continua sendo UMA cartela física, mas
     * possui quatro oportunidades de vitória (uma por prêmio).
     * Um bloco está "na boa" quando falta no máximo o limite configurado
     * de números para completar a condição do seu prêmio.
     *
     * Dados pessoais (comprador/telefone) não entram no estado — eles
     * ficam restritos à tela de cartelas e nunca aparecem no telão.
     */
    private function cardsSummary(Game $game, array $drawnNumbers, ?array $currentPrize): array
    {
        $summary = [
            'total_in_play' => 0,
            'blocks_in_play' => 0,
            'na_boa' => 0,
            'na_boa_blocks' => 0,
            'winners' => 0,
            'winning_blocks' => 0,
            'far' => 0,
            'cancelled' => 0,
            'threshold' => $game->na_boa_threshold,
            'na_boa_cards' => [],
            'na_boa_blocks_list' => [],
        ];

        $statusCounts = $game->cards()
            ->select('status', \DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary['total_in_play'] = (int) ($statusCounts[Card::STATUS_IN_PLAY] ?? 0);
        $summary['winners'] = (int) ($statusCounts[Card::STATUS_WINNER] ?? 0);
        $summary['cancelled'] = (int) ($statusCounts[Card::STATUS_CANCELLED] ?? 0);

        if (! $currentPrize && $summary['total_in_play'] === 0) {
            return $summary;
        }

        $wonPrizeIds = $game->winners()->pluck('prize_id')->all();
        $validator = app(WinRuleValidator::class);

        $inPlay = $game->cards()
            ->with('blocks')
            ->where('status', Card::STATUS_IN_PLAY)
            ->orderBy('id')
            ->get();

        $naBoaCards = [];
        $naBoaBlocks = [];
        $winningBlocks = 0;
        $cardBestMissing = [];

        foreach ($inPlay as $card) {
            $cardNaBoa = false;
            $cardBestMissing[$card->id] = null;

            foreach ($card->effectiveBlocks() as $block) {
                $summary['blocks_in_play']++;

                if ($block->status === CardBlock::STATUS_WINNER) {
                    $winningBlocks++;

                    continue;
                }

                // Blocos de prêmios já ganhos não são mais oportunidades
                if ($block->prize_id !== null && in_array($block->prize_id, $wonPrizeIds)) {
                    continue;
                }

                $missing = $validator->bestMissing(
                    $block->grid,
                    $drawnNumbers,
                    $block->rule,
                    $block->pattern,
                );

                if (count($missing) <= $game->na_boa_threshold) {
                    $cardNaBoa = true;
                    $cardBestMissing[$card->id] = min($cardBestMissing[$card->id] ?? PHP_INT_MAX, count($missing));

                    $naBoaBlocks[] = [
                        'code' => $card->code,
                        'block' => $block->label(),
                        'position' => $block->position,
                        'prize' => $block->prize_name,
                        'missing' => count($missing),
                        'numbers' => array_values($missing),
                    ];
                }
            }

            if ($cardNaBoa) {
                $naBoaCards[] = [
                    'code' => $card->code,
                    'missing' => $cardBestMissing[$card->id],
                ];
            }
        }

        usort($naBoaBlocks, fn ($a, $b) => $a['missing'] <=> $b['missing']);
        usort($naBoaCards, fn ($a, $b) => $a['missing'] <=> $b['missing']);

        $summary['na_boa'] = count($naBoaCards);
        $summary['na_boa_blocks'] = count($naBoaBlocks);
        $summary['winning_blocks'] = $winningBlocks;
        $summary['far'] = $summary['total_in_play'] - count($naBoaCards);
        $summary['na_boa_cards'] = array_slice($naBoaCards, 0, 15);
        $summary['na_boa_blocks_list'] = array_slice($naBoaBlocks, 0, 30);

        return $summary;
    }
}

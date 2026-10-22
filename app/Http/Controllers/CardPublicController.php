<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Game;
use App\Services\GameStateService;
use App\Services\WinRuleValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Acompanhamento público de uma cartela — destino do QR Code impresso.
 * Mostra apenas os números da própria cartela e o andamento da rodada;
 * dados pessoais (comprador/telefone) nunca aparecem aqui.
 */
class CardPublicController extends Controller
{
    public function show(Game $game, string $code): View
    {
        $card = $game->cards()->where('code', $code)->firstOrFail();

        return view('cards.public', [
            'game' => $game,
            'card' => $card,
            'state' => $this->cardState($game, $card),
        ]);
    }

    /**
     * Estado leve para o polling da página — sem o resumo completo de
     * cartelas usado pelo telão.
     */
    public function state(Game $game, string $code): JsonResponse
    {
        $card = $game->cards()->where('code', $code)->firstOrFail();

        return response()->json($this->cardState($game, $card));
    }

    /**
     * @return array{
     *     status: string,
     *     drawn: array<int, int>,
     *     drawn_count: int,
     *     remaining_count: int,
     *     last: array{number: int, letter: ?string, at: ?string}|null,
     *     blocks: array<int, array{label: string, missing: int}>,
     *     current_prize: array{name: string, rule_label: string}|null,
     *     winners: array<int, array{prize_name: ?string, card_code: ?string, block: ?string, winner_name: ?string, at: ?string}>,
     * }
     */
    private function cardState(Game $game, Card $card): array
    {
        $draws = $game->draws()->orderBy('sequence')->get();
        $drawn = $draws->pluck('number')->all();
        $last = $draws->last();

        $validator = app(WinRuleValidator::class);

        $blocks = $card->effectiveBlocks()
            ->map(fn ($block) => [
                'label' => $block->label(),
                'missing' => $validator->missingCount($block->grid, $drawn, $block->rule, $block->pattern),
            ])
            ->values()
            ->all();

        $currentPrize = app(GameStateService::class)->currentPrize($game);

        $winners = $game->winners()
            ->with(['prize', 'card', 'block'])
            ->orderBy('validated_at')
            ->get()
            ->map(fn ($winner) => [
                'prize_name' => $winner->prize?->name,
                'card_code' => $winner->card?->code,
                'block' => $winner->block?->label(),
                'winner_name' => $winner->winner_name,
                'at' => $winner->validated_at?->format('H:i:s'),
            ])
            ->all();

        return [
            'status' => $game->status,
            'drawn' => $drawn,
            'drawn_count' => count($drawn),
            'remaining_count' => $game->totalNumbers() - count($drawn),
            'last' => $last === null ? null : [
                'number' => $last->number,
                'letter' => $last->letter,
                'at' => $last->created_at?->format('H:i:s'),
            ],
            'blocks' => $blocks,
            'current_prize' => $currentPrize === null ? null : [
                'name' => $currentPrize->name,
                'rule_label' => $currentPrize->ruleLabel(),
            ],
            'winners' => $winners,
        ];
    }
}

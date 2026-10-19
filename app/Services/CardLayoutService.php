<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Game;
use Illuminate\Support\Collection;

/**
 * Calcula o arranjo visual dos blocos numéricos numa folha A4,
 * mantendo a legibilidade: nunca reduz os números a um tamanho
 * ilegível para forçar tudo em uma única página.
 */
class CardLayoutService
{
    /** Máximo de blocos por linha dentro da área útil do A4. */
    public const MAX_BLOCKS_PER_ROW = 3;

    /** Máximo de blocos legíveis em uma mesma folha A4. */
    public const MAX_BLOCKS_PER_SHEET = 6;

    /**
     * @return array{
     *     blocks_per_row: int,
     *     blocks_per_sheet: int,
     *     cards_per_row: int,
     *     warning: bool,
     *     blocks_count: int,
     * }
     */
    public function layout(Game $game, int $blocksCount): array
    {
        // Cartelas de 90 bolas são largas (3x9): no máximo 2 por linha
        $maxPerRow = $game->card_format === '90' ? 2 : self::MAX_BLOCKS_PER_ROW;

        $blocksPerRow = max(1, min($maxPerRow, $blocksCount));
        $blocksPerSheet = min(
            self::MAX_BLOCKS_PER_SHEET,
            $maxPerRow * ($game->card_format === '90' ? 3 : 2),
        );

        return [
            'blocks_per_row' => $blocksPerRow,
            'blocks_per_sheet' => $blocksPerSheet,
            // Com vários blocos, uma cartela por folha; cartela de bloco
            // único mantém o comportamento de 1/2/4 por página.
            'cards_per_row' => $blocksCount > 1 ? 1 : null,
            'warning' => $blocksCount > $blocksPerSheet,
            'blocks_count' => $blocksCount,
        ];
    }

    /**
     * Divide os blocos de uma cartela em "partes de folha" quando ela
     * possui mais blocos do que cabe com legibilidade em um único A4.
     * Cada parte repete o código da cartela e o cabeçalho.
     *
     * @return array<int, array{card: Card, blocks: Collection}>
     */
    public function sheetParts(Card $card, ?int $blocksPerSheet = null): array
    {
        $blocks = $card->effectiveBlocks();
        $blocksPerSheet ??= $this->layout($card->game, $blocks->count())['blocks_per_sheet'];

        $parts = [];
        foreach ($blocks->chunk($blocksPerSheet) as $chunk) {
            $parts[] = ['card' => $card, 'blocks' => $chunk->values()];
        }

        return $parts;
    }
}

<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Game;
use Illuminate\Database\Eloquent\Collection;

/**
 * Monta os dados da emissão de cartelas (pré-visualização e PDF) a partir
 * dos filtros informados. Usado tanto pela geração síncrona (controller)
 * quanto pelo job da fila — os dois caminhos produzem exatamente o mesmo
 * documento.
 */
class CardPrintService
{
    /**
     * Acima desta quantidade de cartelas o PDF sai da requisição HTTP e
     * passa para a fila: gerar centenas de páginas dompdf num request
     * único pode estourar o tempo limite.
     */
    public const INLINE_MAX_CARDS = 25;

    /**
     * Cartelas que serão emitidas, conforme os filtros (lote/cartela).
     *
     * @param  array<string, mixed>  $query
     * @return Collection<int, Card>
     */
    public function cardsForFilters(Game $game, array $query): Collection
    {
        $cards = $game->cards()->orderBy('id');

        if (! empty($query['batch'])) {
            $cards->where('batch', (int) $query['batch']);
        }

        if (! empty($query['card'])) {
            $cards->where('code', trim((string) $query['card']));
        }

        return $cards->get();
    }

    /**
     * Primeira emissão: registra a data e congela os prêmios exibidos.
     *
     * @param  Collection<int, Card>  $cards
     */
    public function issue(Game $game, Collection $cards): void
    {
        foreach ($cards as $card) {
            if ($card->printed_at === null) {
                $card->forceFill([
                    'printed_at' => now(),
                    'prize_snapshot' => $card->prizesForPrint(),
                ])->save();
            }
        }
    }

    /**
     * Dados completos da emissão — mesmos usados pela pré-visualização e
     * pelo PDF. $baseUrl fixa o endereço embutido no QR Code quando não
     * há contexto de requisição (job da fila).
     *
     * @param  Collection<int, Card>  $cards
     * @return array<string, mixed>
     */
    public function data(Game $game, array $query, Collection $cards, ?string $baseUrl = null): array
    {
        $blocksCount = $cards->first()?->effectiveBlocks()->count() ?? 1;
        $layout = app(CardLayoutService::class)->layout($game, $blocksCount);

        // Modelo de arte personalizada aplicado à geração (padrão do
        // evento quando nenhum for informado). A arte nunca altera os
        // dados do jogo — apenas a aparência visual.
        $template = null;
        if (($query['template'] ?? null) === 'none') {
            $template = null;
        } elseif (! empty($query['template'])) {
            $template = $game->cardTemplates()->find((int) $query['template']);
        } elseif (! array_key_exists('template', $query)) {
            $template = $game->cardTemplates()->where('is_default', true)->first();
        }

        $templateWarnings = [];
        if ($template) {
            $templateWarnings = $template->missingAreas($blocksCount);
        }

        // Blocos divididos em mais de uma folha quando não couberem
        // de forma legível e o organizador autorizar a divisão.
        $splitBlocks = $this->boolean($query, 'split_blocks', false) && $layout['warning'];

        $parts = [];
        foreach ($cards as $card) {
            $cardBlocks = $card->effectiveBlocks();
            $chunks = $splitBlocks || $cardBlocks->count() > $layout['blocks_per_sheet']
                ? $cardBlocks->chunk($layout['blocks_per_sheet'])
                : [$cardBlocks];

            foreach ($chunks as $chunk) {
                $parts[] = ['card' => $card, 'blocks' => $chunk->values()];
            }
        }

        $showQr = $this->boolean($query, 'qr', true);

        return [
            'game' => $game,
            'cards' => $cards,
            'parts' => $parts,
            'layout' => $layout,
            'blocksCount' => $blocksCount,
            'template' => $template,
            'templateWarnings' => $templateWarnings,
            'showQr' => $showQr,
            'qrCodes' => $showQr
                ? app(QrCodeService::class)->cardsDataUri($game, $cards, $baseUrl)
                : [],
            'perPage' => $blocksCount > 1
                ? 1
                : (in_array((int) ($query['per_page'] ?? null), [1, 2, 4], true) ? (int) $query['per_page'] : 2),
            'stub' => $this->boolean($query, 'stub', true),
            'showPrizes' => $this->boolean($query, 'prizes', true),
            'showPrice' => $this->boolean($query, 'price', true),
            'showBuyer' => $this->boolean($query, 'buyer', true),
            'showDate' => $this->boolean($query, 'date', true),
        ];
    }

    private function boolean(array $query, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $query)) {
            return $default;
        }

        return filter_var($query[$key], FILTER_VALIDATE_BOOLEAN);
    }
}

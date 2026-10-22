<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Game;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gera a arte da cartela em formatos abertos para edição em programas
 * gráficos (Inkscape, Illustrator, CorelDRAW, Canva): SVG vetorial com
 * dimensões físicas A4 em milímetros. O PDF usa o mesmo layout via
 * dompdf. PSD não é gerado — não há geração confiável de camadas
 * editáveis neste ambiente.
 */
class ArtExportService
{
    public const A4_WIDTH_MM = 210;

    public const A4_HEIGHT_MM = 297;

    /**
     * SVG vetorial da cartela — números reais, texto editável.
     */
    public function svg(Game $game, ?Card $card = null): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($game, $card) {
                echo $this->buildSvg($game, $card);
            },
            $card ? "cartela-{$card->code}.svg" : 'modelo-base-cartela.svg',
            ['Content-Type' => 'image/svg+xml'],
        );
    }

    /**
     * Modelo-base em SVG com áreas reservadas nomeadas, pronto para
     * personalização externa e reimportação.
     */
    public function svgModelBase(Game $game): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($game) {
                echo $this->buildSvg($game, null, true);
            },
            "modelo-base-{$game->id}.svg",
            ['Content-Type' => 'image/svg+xml'],
        );
    }

    /**
     * Monta o SVG da cartela. Quando $modelBase é true, usa textos de
     * exemplo e identifica cada área reservada com um rótulo nomeado,
     * permitindo mapear as áreas na reimportação.
     */
    public function buildSvg(Game $game, ?Card $card, bool $modelBase = false): string
    {
        $code = $card?->code ?? 'C-0000';

        // Código do canhoto em vetorial, conforme o tipo escolhido nos
        // dados do evento: barras Code128 ou QR Code (mesmo destino do
        // QR do cabeçalho — a página pública da cartela).
        $stubCodeType = $game->stubCodeType();
        $barcodeRuns = [];
        $stubQrMatrix = [];
        if ($stubCodeType === 'qrcode') {
            $stubQrMatrix = app(QrCodeService::class)->matrix(
                app(QrCodeService::class)->cardUrl($game, $card ?? new Card(['code' => $code])),
            );
        } elseif ($stubCodeType === 'barcode') {
            $barcodeRuns = app(BarcodeService::class)->runs($code);
        }

        $blocks = $card
            ? $card->effectiveBlocks()->map(fn ($block) => [
                'label' => $block->label(),
                'prize_name' => $block->prize_name,
                'prize_rule_label' => $block->prize_rule_label,
                'grid' => $block->grid,
            ])->values()
            : $game->prizes()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($prize, $i) => [
                    'label' => 'B'.($i + 1),
                    'prize_name' => $prize->name,
                    'prize_rule_label' => $prize->ruleLabel(),
                    'grid' => $this->placeholderGrid($game),
                ])->values();

        $prizes = $card?->prizesForPrint()
            ?? $game->prizes()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($prize, $i) => [
                    'sort_order' => $i + 1,
                    'name' => $prize->name,
                    'description' => $prize->description,
                    'rule_label' => $prize->ruleLabel(),
                    'estimated_value' => $prize->estimated_value,
                ])->all();

        $data = [
            'game' => $game,
            'card' => $card,
            'blocks' => $blocks,
            'prizes' => $prizes,
            'model_base' => $modelBase,
            'code' => $code,
            'stub_code_type' => $stubCodeType,
            // Código do canhoto em vetorial (barras Code128 ou QR Code).
            'barcode_runs' => $barcodeRuns,
            'stub_qr_matrix' => $stubQrMatrix,
        ];

        return view('cards.art-svg', $data)->render();
    }

    /**
     * Grade de exemplo com marcas {NUM} para o modelo-base.
     */
    private function placeholderGrid(Game $game): array
    {
        $rows = $game->card_format === '90' ? 3 : 5;
        $cols = $game->card_format === '90' ? 9 : 5;

        return array_fill(0, $rows, array_fill(0, $cols, 0));
    }
}

<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Game;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Gera os QR Codes das cartelas como PNG data URI — o formato que a
 * pré-visualização de impressão e o PDF A4 (dompdf) conseguem embutir
 * sem carregar arquivos externos.
 */
class QrCodeService
{
    /**
     * PNG data URI do QR Code informado.
     */
    public function dataUri(string $data, int $scale = 8): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'scale' => $scale,
            'outputBase64' => true,
        ]);

        return (string) (new QRCode($options))->render($data);
    }

    /**
     * Matriz do QR Code (true = módulo escuro) — usada para renderizar
     * o QR em vetorial na arte exportável (canhoto da cartela).
     *
     * @return array<int, array<int, bool>>
     */
    public function matrix(string $data): array
    {
        $qrcode = new QRCode(new QROptions(['eccLevel' => EccLevel::M]));
        $qrcode->addByteSegment($data);

        return $qrcode->getQRMatrix()->getBooleanMatrix();
    }

    /**
     * URL pública de acompanhamento da cartela — o destino do QR
     * impresso. Sem $baseUrl usa o endereço da requisição atual;
     * gere/imprima as cartelas pelo mesmo endereço que o público
     * usará no evento. O job da fila informa a URL capturada no
     * momento do pedido, pois não há requisição ativa.
     */
    public function cardUrl(Game $game, Card $card, ?string $baseUrl = null): string
    {
        $path = '/games/'.$game->id.'/card/'.rawurlencode($card->code);

        return $baseUrl === null ? url($path) : rtrim($baseUrl, '/').$path;
    }

    /**
     * Data URI do QR de cada cartela, indexado pelo id da cartela.
     *
     * @param  iterable<Card>  $cards
     * @return array<int, string>
     */
    public function cardsDataUri(Game $game, iterable $cards, ?string $baseUrl = null): array
    {
        $map = [];

        foreach ($cards as $card) {
            $map[$card->id] = $this->dataUri($this->cardUrl($game, $card, $baseUrl));
        }

        return $map;
    }
}

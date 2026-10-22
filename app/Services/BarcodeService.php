<?php

namespace App\Services;

use App\Models\Card;

/**
 * Gera códigos de barras Code128-B como PNG data URI — o formato que a
 * pré-visualização de impressão e o PDF A4 (dompdf) embutem sem carregar
 * arquivos externos (mesma estratégia do QR Code). O conteúdo é o código
 * da cartela (ex.: C-0001), impresso no canhoto destacável: qualquer
 * leitor de código de barras ou câmera de celular identifica a cartela
 * na hora da conferência.
 */
class BarcodeService
{
    /**
     * Padrões Code128 (ISO/IEC 15417): 11 módulos por símbolo, onde 1 = barra
     * e 0 = espaço. Índices 0-105; 103-105 são os starts e o 106 é o stop
     * (13 módulos, definido em STOP).
     */
    private const PATTERNS = [
        0 => '11011001100', 1 => '11001101100', 2 => '11001100110', 3 => '10010011000',
        4 => '10010001100', 5 => '10001001100', 6 => '10011001000', 7 => '10011000100',
        8 => '10001100100', 9 => '11001001000', 10 => '11001000100', 11 => '11000100100',
        12 => '10110011100', 13 => '10011011100', 14 => '10011001110', 15 => '10111001100',
        16 => '10011101100', 17 => '10011100110', 18 => '11001110010', 19 => '11001011100',
        20 => '11001001110', 21 => '11011100100', 22 => '11001110100', 23 => '11101101110',
        24 => '11101001100', 25 => '11100101100', 26 => '11100100110', 27 => '11101100100',
        28 => '11100110100', 29 => '11100110010', 30 => '11011011000', 31 => '11011000110',
        32 => '11000110110', 33 => '10100011000', 34 => '10001011000', 35 => '10001000110',
        36 => '10110001000', 37 => '10001101000', 38 => '10001100010', 39 => '11010001000',
        40 => '11000101000', 41 => '11000100010', 42 => '10110111000', 43 => '10110001110',
        44 => '10001101110', 45 => '10111011000', 46 => '10111000110', 47 => '10001110110',
        48 => '11101110110', 49 => '11010001110', 50 => '11000101110', 51 => '11011101000',
        52 => '11011100010', 53 => '11011101110', 54 => '11101011000', 55 => '11101000110',
        56 => '11100010110', 57 => '11101101000', 58 => '11101100010', 59 => '11100011010',
        60 => '11101111010', 61 => '11001000010', 62 => '11110001010', 63 => '10100110000',
        64 => '10100001100', 65 => '10010110000', 66 => '10010000110', 67 => '10000101100',
        68 => '10000100110', 69 => '10110010000', 70 => '10110000100', 71 => '10011010000',
        72 => '10011000010', 73 => '10000110100', 74 => '10000110010', 75 => '11000010010',
        76 => '11001010000', 77 => '11110111010', 78 => '11000010100', 79 => '10001111010',
        80 => '10100111100', 81 => '10010111100', 82 => '10010011110', 83 => '10111100100',
        84 => '10011110100', 85 => '10011110010', 86 => '11110100100', 87 => '11110010100',
        88 => '11110010010', 89 => '11011011110', 90 => '11011110110', 91 => '11110110110',
        92 => '10101111000', 93 => '10100011110', 94 => '10001011110', 95 => '10111101000',
        96 => '10111100010', 97 => '11110101000', 98 => '11110100010', 99 => '10111011110',
        100 => '10111101110', 101 => '11101011110', 102 => '11110101110',
        103 => '11010000100', 104 => '11010010000', 105 => '11010011100',
    ];

    private const STOP = '1100011101011';

    private const START_B = 104;

    /** Margem silenciosa (módulos) em cada lado — mínimo de 10x pela especificação. */
    private const QUIET_MODULES = 10;

    /**
     * Trechos pretos do símbolo Code128-B em módulos: cada item é
     * [início, largura] contado a partir do primeiro módulo do símbolo,
     * sem a margem silenciosa — quem desenha decide a folga ao redor.
     * Usado para renderizar o código em SVG vetorial (arte exportável).
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public function runs(string $data): array
    {
        $bits = $this->encode($data);

        $runs = [];
        $start = null;
        foreach (str_split($bits) as $module => $bit) {
            if ($bit === '1') {
                $start ??= $module;
            } elseif ($start !== null) {
                $runs[] = [$start, $module - $start];
                $start = null;
            }
        }
        if ($start !== null) {
            $runs[] = [$start, strlen($bits) - $start];
        }

        return $runs;
    }

    /**
     * Sequência de módulos (1 = barra, 0 = espaço) do símbolo Code128-B
     * completo: start, dados, dígito verificador e stop.
     */
    private function encode(string $data): string
    {
        $data = trim($data);
        if ($data === '' || preg_match('/^[\x20-\x7E]+$/', $data) !== 1) {
            throw new \InvalidArgumentException('O código de barras aceita apenas caracteres ASCII imprimíveis.');
        }

        $symbols = [self::START_B];
        $checksum = self::START_B;
        foreach (str_split($data) as $position => $char) {
            $value = ord($char) - 32;
            $symbols[] = $value;
            $checksum += $value * ($position + 1);
        }
        $symbols[] = $checksum % 103;

        $bits = '';
        foreach ($symbols as $symbol) {
            $bits .= self::PATTERNS[$symbol];
        }

        return $bits.self::STOP;
    }

    /**
     * PNG data URI do código de barras Code128-B do texto informado.
     * Aceita apenas ASCII imprimível (32-126).
     */
    public function dataUri(string $data, int $scale = 2, int $height = 60): string
    {
        $bits = $this->encode($data);

        $width = (strlen($bits) + self::QUIET_MODULES * 2) * $scale;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        $x = self::QUIET_MODULES * $scale;
        foreach (str_split($bits) as $bit) {
            if ($bit === '1') {
                imagefilledrectangle($image, $x, 0, $x + $scale - 1, $height - 1, $black);
            }
            $x += $scale;
        }

        ob_start();
        imagepng($image, null, 1);
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    /**
     * Data URI do código de barras de cada cartela (conteúdo = código da
     * cartela), indexado pelo id — mesmo contrato do QrCodeService.
     *
     * @param  iterable<Card>  $cards
     * @return array<int, string>
     */
    public function cardsDataUri(iterable $cards): array
    {
        $map = [];

        foreach ($cards as $card) {
            $map[$card->id] = $this->dataUri($card->code);
        }

        return $map;
    }
}

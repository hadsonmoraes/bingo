<?php

namespace Tests\Unit;

use App\Models\Card;
use App\Services\BarcodeService;
use PHPUnit\Framework\TestCase;

class BarcodeServiceTest extends TestCase
{
    private BarcodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BarcodeService;
    }

    public function test_data_uri_embeds_a_png_image(): void
    {
        $uri = $this->service->dataUri('C-0001');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')), true) ?: '';
        $this->assertSame("\x89PNG", substr($png, 0, 4));
    }

    public function test_runs_encode_code128b_start_check_digit_and_stop(): void
    {
        $runs = $this->service->runs('C-0001');

        // Start B ("11010010000"): primeira barra com dois módulos.
        $this->assertSame([0, 2], $runs[0]);

        // Start + 6 caracteres + dígito verificador = 8 símbolos de 11
        // módulos, mais o stop de 13 módulos: 101 módulos no total.
        $last = end($runs);
        $this->assertSame(101, $last[0] + $last[1]);

        // Verificador de "C-0001" = 459 % 103 = 47 ("10001110110"):
        // barra simples no módulo 77 (11 do start + 6 x 11 dos dados).
        $this->assertContains([77, 1], $runs);
    }

    public function test_runs_match_the_png_data_uri_for_the_same_code(): void
    {
        $this->assertSame($this->service->runs('C-0001'), $this->service->runs(' C-0001 '));
    }

    public function test_rejects_data_outside_printable_ascii(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->dataUri('Cartela Café');
    }

    public function test_rejects_empty_data(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->runs('   ');
    }

    public function test_cards_data_uri_is_indexed_by_card_id(): void
    {
        $card = new Card;
        $card->id = 7;
        $card->code = 'C-0007';

        $map = $this->service->cardsDataUri([$card]);

        $this->assertSame([7], array_keys($map));
        $this->assertStringStartsWith('data:image/png;base64,', $map[7]);
    }
}

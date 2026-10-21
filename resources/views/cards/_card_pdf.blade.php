@php
    use App\Models\Card;
    /** @var Card $card */
    $blocks = $blocks ?? $card->effectiveBlocks();
    $useLetters = $game->card_format === '75';
    $prizes = $card->prizesForPrint();
    $blocksPerRow = $layout['blocks_per_row'] ?? min(3, max(1, $blocks->count()));
@endphp
<div class="card scale-{{ $scale }}{{ ($multiBlocks ?? false) ? ' card--multi' : '' }}">
    <table class="head" style="background-color: {{ $theme[1] }};">
        <tr>
            <td class="head-brand">
                @if ($logoDataUri)
                    <img class="logo" src="{{ $logoDataUri }}" alt="">
                @endif
                @if ($game->organization_name)
                    <p class="org" style="color: {{ $theme[0] }};">{{ $game->organization_name }}</p>
                @endif
                <p class="event">{{ $game->name }}</p>
            </td>
            <td class="head-meta">
                <table class="head-qr-row"><tr>
                    @if (($showQr ?? true) && ($qrCodes[$card->id] ?? null))
                        <td class="head-qr-cell">
                            <img class="qr" src="{{ $qrCodes[$card->id] }}" alt="QR Code para acompanhar a cartela {{ $card->code }}">
                        </td>
                    @endif
                    <td>
                        <p class="title">AÇÃO ENTRE AMIGOS</p>
                        <p class="code">Cartela {{ $card->code }}</p>
                        @if ($showDate && $game->event_date)
                            <p class="date">{{ $game->event_date->format('d/m/Y') }}</p>
                        @endif
                    </td>
                </tr></table>
            </td>
        </tr>
    </table>

    @if ($showPrizes && count($prizes) > 0)
        <div class="prizes">
            <p class="prizes-label" style="color: {{ $theme[0] }};">PRÊMIOS DA AÇÃO ENTRE AMIGOS</p>
            @foreach ($prizes as $index => $prize)
                <p>
                    <strong>{{ $prize['sort_order'] ?? $index + 1 }}º</strong>
                    {{ $prize['name'] }}
                    @if (! empty($prize['description']))
                        — {{ $prize['description'] }}
                    @endif
                    — <em>{{ $prize['rule_label'] }}</em>
                    @if ($prize['estimated_value'] ?? null)
                        — <strong>{{ $prize['estimated_value'] }}</strong>
                    @endif
                    — Bloco {{ $prize['sort_order'] ?? $index + 1 }}
                </p>
            @endforeach
        </div>
    @endif

    {{-- Blocos numéricos: um por prêmio, legenda fora da grade --}}
    <table class="blocks"><tr>
        @foreach ($blocks as $block)
            <td class="block-cell" style="width: {{ round(100 / $blocksPerRow, 2) }}%;">
                <p class="block-caption" style="border-color: {{ $theme[0] }}; background-color: {{ $theme[1] }}; color: {{ $theme[0] }};">
                    <strong>{{ $block->label() }}</strong> · {{ $block->prize_name }} · {{ $block->prize_rule_label }}
                </p>
                <table class="grid">
                    @if ($useLetters)
                        <tr>
                            @foreach ($game->letters() as $letter)
                                <th style="background-color: {{ $theme[0] }};">{{ $letter }}</th>
                            @endforeach
                        </tr>
                    @endif
                    @for ($r = 0; $r < count($block->grid); $r++)
                        <tr>
                            @for ($c = 0; $c < count($block->grid[0]); $c++)
                                @php $value = $block->grid[$r][$c] ?? null; @endphp
                                <td class="{{ $value === null ? 'free' : '' }}">{{ $value ?? '★' }}</td>
                            @endfor
                        </tr>
                    @endfor
                </table>
            </td>
            @if (($loop->iteration % $blocksPerRow) === 0 && ! $loop->last)
                </tr><tr>
            @endif
        @endforeach
        @for ($i = 0; $i < ($blocksPerRow - ($blocks->count() % $blocksPerRow)) % $blocksPerRow; $i++)
            <td class="block-cell"></td>
        @endfor
    </tr></table>

    @if ($showBuyer)
        @if ($stub)
            <p class="stub-cut">- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</p>
            <div class="stub">
                <p><strong style="color: {{ $theme[0] }};">{{ $game->organization_name ?? 'AÇÃO ENTRE AMIGOS' }}</strong></p>
                <p>{{ $game->name }} · Cartela <strong>{{ $card->code }}</strong></p>
                @if ($barcodes[$card->id] ?? null)
                    <table class="stub-row"><tr>
                        <td class="stub-fields">Nome: ______________________________ Telefone: ______________</td>
                        <td class="stub-barcode">
                            <img class="barcode" src="{{ $barcodes[$card->id] }}" alt="Código de barras da cartela {{ $card->code }}">
                            <p class="barcode-code">{{ $card->code }}</p>
                        </td>
                    </tr></table>
                @else
                    <p>Nome: ______________________________ Telefone: ______________</p>
                @endif
            </div>
            <div class="buyer">
                <p>Nome do comprador: ________________________________ Telefone: ______________ Assinatura: ____________</p>
            </div>
        @else
            <div class="buyer">
                <p>Nome do comprador: ______________________________________________________</p>
                <p>Telefone: ______________________ Assinatura / Conferência: ______________</p>
            </div>
        @endif
    @endif

    @if ($showPrice && $game->card_price)
        <p class="price">Valor da cartela: R$ {{ number_format((float) $game->card_price, 2, ',', '.') }}</p>
    @endif
</div>

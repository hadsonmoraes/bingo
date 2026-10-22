@php
    use App\Models\Card;
    /** @var Card $card */
    $blocks = $blocks ?? $card->effectiveBlocks();
    $useLetters = $game->card_format === '75';
    $prizes = $card->prizesForPrint();
    $theme = [
        'amber' => ['#b45309', '#fef3c7'],
        'emerald' => ['#047857', '#d1fae5'],
        'sky' => ['#0369a1', '#e0f2fe'],
        'rose' => ['#be123c', '#ffe4e6'],
        'violet' => ['#6d28d9', '#ede9fe'],
        'slate' => ['#334155', '#e2e8f0'],
    ][$game->theme_color] ?? ['#b45309', '#fef3c7'];
    $blocksPerRow = $layout['blocks_per_row'] ?? min(3, max(1, $blocks->count()));
@endphp
<div class="bingo-card bingo-card--{{ $perPage }}{{ $blocks->count() > 1 ? ' has-multi-blocks' : '' }}">
    {{-- Cabeçalho personalizado --}}
    <div class="card-header" style="border-color: {{ $theme[0] }}; background: {{ $theme[1] }};">
        <div class="card-header__brand">
            @if ($game->logoUrl())
                <img src="{{ $game->logoUrl() }}" alt="">
            @endif
            <div class="card-header__texts">
                @if ($game->organization_name)
                    <p class="card-header__org" style="color: {{ $theme[0] }};">{{ $game->organization_name }}</p>
                @endif
                <p class="card-header__event">{{ $game->name }}</p>
            </div>
        </div>
        <div class="card-header__meta">
            @if (($showQr ?? true) && ($qrCodes[$card->id] ?? null))
                <img class="card-header__qr" src="{{ $qrCodes[$card->id] }}"
                    alt="QR Code para acompanhar a cartela {{ $card->code }}">
            @endif
            <div class="card-header__meta-text">
                <p class="card-header__title">AÇÃO ENTRE AMIGOS</p>
                <p class="card-header__code">Cartela <strong>{{ $card->code }}</strong></p>
                @if ($showDate && $game->event_date)
                    <p class="card-header__date">{{ $game->event_date->format('d/m/Y') }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Área dos prêmios: SEMPRE fora das grades numéricas --}}
    @if ($showPrizes && count($prizes) > 0)
        <div class="card-prizes" style="border-color: {{ $theme[0] }};">
            <p class="card-prizes__label" style="color: {{ $theme[0] }};">PRÊMIOS DA AÇÃO ENTRE AMIGOS</p>
            <ul class="card-prizes__list">
                @foreach ($prizes as $index => $prize)
                    <li>
                        <strong>{{ $prize['sort_order'] ?? $index + 1 }}º</strong>
                        {{ $prize['name'] }}
                        @if (! empty($prize['description']))
                            — <span>{{ $prize['description'] }}</span>
                        @endif
                        — <em>{{ $prize['rule_label'] }}</em>
                        @if ($prize['estimated_value'] ?? null)
                            — <strong>{{ $prize['estimated_value'] }}</strong>
                        @endif
                        — <span class="card-prizes__block">Bloco {{ $prize['sort_order'] ?? $index + 1 }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Blocos numéricos: um bloco por prêmio, legenda fora da grade --}}
    <div class="card-blocks card-blocks--{{ $blocksPerRow === 1 ? '1' : ($game->card_format === '90' ? '90' : 'multi') }}" style="--blocks-per-row: {{ $blocksPerRow }};">
        @foreach ($blocks as $block)
            <div class="card-block">
                <p class="card-block__caption" style="background: {{ $theme[1] }}; color: {{ $theme[0] }}; border-color: {{ $theme[0] }};">
                    <strong>{{ $block->label() }}</strong>
                    <span>{{ $block->prize_name }}</span>
                    <em>{{ $block->prize_rule_label }}</em>
                </p>
                <table class="card-grid">
                    @if ($useLetters)
                        <tr>
                            @foreach ($game->letters() as $letter)
                                <th style="background: {{ $theme[0] }};">{{ $letter }}</th>
                            @endforeach
                        </tr>
                    @endif
                    @for ($r = 0; $r < count($block->grid); $r++)
                        <tr>
                            @for ($c = 0; $c < count($block->grid[0]); $c++)
                                @php $value = $block->grid[$r][$c] ?? null; @endphp
                                <td class="{{ $value === null ? 'card-grid__free' : '' }}">{{ $value ?? '★' }}</td>
                            @endfor
                        </tr>
                    @endfor
                </table>
            </div>
        @endforeach
    </div>

    {{-- Identificação do comprador / canhoto --}}
    @if ($showBuyer)
        @if ($stub)
            <div class="card-stub">
                <div class="card-stub__cut">✂ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>
                <div class="card-stub__body" style="border-color: {{ $theme[0] }};">
                    <p class="card-stub__title" style="color: {{ $theme[0] }};">{{ $game->organization_name ?? 'AÇÃO ENTRE AMIGOS' }}</p>
                    <p class="card-stub__event">{{ $game->name }} · Cartela <strong>{{ $card->code }}</strong></p>
                    <div class="card-stub__row">
                        <p class="card-stub__fields">Nome: ______________________________ Telefone: ______________</p>
                        @if ($stubCodeType === 'qrcode' && ($qrCodes[$card->id] ?? null))
                            <div class="card-stub__code" title="QR Code da cartela {{ $card->code }}">
                                <img class="card-stub__qr" src="{{ $qrCodes[$card->id] }}" alt="QR Code da cartela {{ $card->code }}">
                                <p class="card-stub__barcode-code">{{ $card->code }}</p>
                            </div>
                        @elseif ($barcodes[$card->id] ?? null)
                            <div class="card-stub__barcode" title="Código de barras da cartela {{ $card->code }}">
                                <img src="{{ $barcodes[$card->id] }}" alt="Código de barras da cartela {{ $card->code }}">
                                <p class="card-stub__barcode-code">{{ $card->code }}</p>
                            </div>
                        @elseif ($stubCodeType === 'none')
                            <div class="card-stub__barcode" title="Número da cartela {{ $card->code }}">
                                <p class="card-stub__number">{{ $card->code }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-buyer card-buyer--compact">
                Nome do comprador: ________________________________ Telefone: ______________ Assinatura: ____________
            </div>
        @else
            <div class="card-buyer">
                <div class="card-buyer__row">Nome do comprador: ______________________________________________________</div>
                <div class="card-buyer__row">Telefone: ______________________ Assinatura / Conferência: ______________</div>
            </div>
        @endif
    @endif

    @if ($showPrice && $game->card_price)
        <p class="card-price" style="background: {{ $theme[0] }};">Valor da cartela: R$ {{ number_format((float) $game->card_price, 2, ',', '.') }}</p>
    @endif
</div>

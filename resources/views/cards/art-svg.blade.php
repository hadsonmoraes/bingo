@php
    $useLetters = $game->card_format === '75';
    $is90 = $game->card_format === '90';
    $blocksPerRow = max(1, min($is90 ? 2 : 3, $blocks->count()));
    $margin = 8;
    $usableW = 210 - 2 * $margin;
    $blockGap = 4;
    $blockW = ($usableW - ($blocksPerRow - 1) * $blockGap) / $blocksPerRow;

    $y = $margin;
    $headerH = 16;
    $rows = $is90 ? 3 : 5;
    $cols = $is90 ? 9 : 5;
    $cell = $is90 ? min($blockW / $cols, 11) : min($blockW / $cols, 13);
    $captionH = 7;
    $lettersH = $useLetters ? $cell * 0.7 : 0;
    $blockH = $captionH + $lettersH + $rows * $cell;

    $prizeLines = count($prizes);
    $prizesH = $prizeLines > 0 ? 6 + $prizeLines * 5.5 : 0;

    $buyerY = 297 - $margin - ($model_base ? 30 : 34);
@endphp
<svg xmlns="http://www.w3.org/2000/svg" width="210mm" height="297mm" viewBox="0 0 210 297" font-family="DejaVu Sans, sans-serif">
    <rect x="0" y="0" width="210" height="297" fill="#ffffff"/>

    {{-- Cabeçalho: área reservada [CABEÇALHO] --}}
    <g id="area-cabecalho">
        <rect x="{{ $margin }}" y="{{ $y }}" width="{{ $usableW }}" height="{{ $headerH }}" fill="#fef3c7" stroke="#b45309" stroke-width="0.6"/>
        <text x="{{ $margin + 3 }}" y="{{ $y + 6 }}" font-size="5" font-weight="bold" fill="#b45309">{{ $game->organization_name ?: '[INSTITUIÇÃO]' }}</text>
        <text x="{{ $margin + 3 }}" y="{{ $y + 12 }}" font-size="7" font-weight="bold" fill="#0f172a">{{ $game->name ?: '[NOME DO EVENTO]' }}</text>
        <text x="{{ 210 - $margin - 3 }}" y="{{ $y + 8 }}" text-anchor="end" font-size="10" font-weight="bold" letter-spacing="2" fill="#0f172a">AÇÃO ENTRE AMIGOS</text>
        <text x="{{ 210 - $margin - 3 }}" y="{{ $y + 13 }}" text-anchor="end" font-size="5.5" font-weight="bold" fill="#0f172a">Cartela {{ $code }}</text>
        @if ($game->event_date)
            <text x="{{ 210 - $margin - 3 }}" y="{{ $y + 17 }}" text-anchor="end" font-size="4" fill="#334155">{{ $game->event_date->format('d/m/Y') }}</text>
        @endif
    </g>
@php $y += $headerH + 4; @endphp

    {{-- Área reservada [PRÊMIOS DA AÇÃO ENTRE AMIGOS] — sempre fora das grades --}}
    @if ($prizeLines > 0)
    <g id="area-premios">
        <rect x="{{ $margin }}" y="{{ $y }}" width="{{ $usableW }}" height="{{ $prizesH }}" fill="none" stroke="#0f172a" stroke-width="0.4" stroke-dasharray="2,1"/>
        <text x="{{ $margin + 2 }}" y="{{ $y + 4.5 }}" font-size="4.5" font-weight="bold" letter-spacing="1.5" fill="#b45309">PRÊMIOS DA AÇÃO ENTRE AMIGOS</text>
        @foreach ($prizes as $i => $prize)
            <text x="{{ $margin + 2 }}" y="{{ $y + 4.5 + 5.5 * ($i + 1) }}" font-size="4.2" fill="#0f172a">
                {{ ($prize['sort_order'] ?? $i + 1) }}º {{ $prize['name'] }} — {{ $prize['rule_label'] }}@if(!empty($prize['estimated_value'])) — {{ $prize['estimated_value'] }}@endif — Bloco {{ ($prize['sort_order'] ?? $i + 1) }}
            </text>
        @endforeach
    </g>
@php $y += $prizesH + 4; @endphp
    @endif

    {{-- Blocos numéricos: um bloco por prêmio, legenda fora da grade --}}
    <g id="area-blocos">
        @foreach ($blocks as $i => $block)
            @php
                $row = intdiv($i, $blocksPerRow);
                $col = $i % $blocksPerRow;
                $bx = $margin + $col * ($blockW + $blockGap);
                $by = $y + $row * ($blockH + $blockGap);
            @endphp
            <g id="area-bloco-{{ $i + 1 }}">
                <rect x="{{ $bx }}" y="{{ $by }}" width="{{ $blockW }}" height="{{ $captionH - 1 }}" fill="#fef3c7" stroke="#b45309" stroke-width="0.4"/>
                <text x="{{ $bx + 1.5 }}" y="{{ $by + 4.5 }}" font-size="3.8" font-weight="bold" fill="#b45309">{{ $block['label'] }}</text>
                <text x="{{ $bx + 7 }}" y="{{ $by + 4.5 }}" font-size="3.8" fill="#0f172a">{{ $block['prize_name'] }} · {{ $block['prize_rule_label'] }}</text>

                @php $gy = $by + $captionH; @endphp
                @if ($useLetters)
                    @foreach ($game->letters() as $c => $letter)
                        <rect x="{{ $bx + $c * ($blockW / 5) }}" y="{{ $gy }}" width="{{ $blockW / 5 }}" height="{{ $lettersH }}" fill="#b45309" stroke="#0f172a" stroke-width="0.3"/>
                        <text x="{{ $bx + $c * ($blockW / 5) + $blockW / 10 }}" y="{{ $gy + $lettersH * 0.75 }}" text-anchor="middle" font-size="{{ $cell * 0.6 }}" font-weight="bold" fill="#ffffff">{{ $letter }}</text>
                    @endforeach
                    @php $gy += $lettersH; @endphp
                @endif

                @for ($r = 0; $r < $rows; $r++)
                    @for ($c = 0; $c < $cols; $c++)
                        @php
                            $value = $block['grid'][$r][$c] ?? null;
                            $cx = $bx + $c * ($blockW / $cols);
                            $cy = $gy + $r * $cell;
                        @endphp
                        <rect x="{{ $cx }}" y="{{ $cy }}" width="{{ $blockW / $cols }}" height="{{ $cell }}" fill="{{ $value === null ? '#e2e8f0' : '#ffffff' }}" stroke="#475569" stroke-width="0.3"/>
                        <text x="{{ $cx + ($blockW / $cols) / 2 }}" y="{{ $cy + $cell * 0.72 }}" text-anchor="middle" font-size="{{ $cell * 0.55 }}" font-weight="bold" fill="#0f172a">{{ $value ?? '★' }}</text>
                    @endfor
                @endfor
            </g>
        @endforeach
    </g>

    {{-- Área reservada [COMPRADOR] e canhoto --}}
    <g id="area-comprador">
        <rect x="{{ $margin }}" y="{{ $buyerY + 12 }}" width="{{ $usableW }}" height="14" fill="none" stroke="#475569" stroke-width="0.4" stroke-dasharray="3,1"/>
        <text x="{{ $margin + 2 }}" y="{{ $buyerY + 19 }}" font-size="4.5" fill="#0f172a">Nome do comprador: ______________________________  Telefone: ______________</text>
    </g>
    <g id="area-canhoto">
        <text x="{{ $margin }}" y="{{ $buyerY + 8 }}" font-size="3.5" fill="#64748b">✂ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</text>
        <rect x="{{ $margin + 2 }}" y="{{ $buyerY - 10 }}" width="{{ $usableW - 4 }}" height="8" fill="none" stroke="#0f172a" stroke-width="0.4"/>
        <text x="{{ $margin + 4 }}" y="{{ $buyerY - 6 }}" font-size="4" font-weight="bold" fill="#0f172a">{{ $game->organization_name ?: 'AÇÃO ENTRE AMIGOS' }} — {{ $game->name }} · Cartela {{ $code }}</text>
        <text x="{{ $margin + 4 }}" y="{{ $buyerY - 2.5 }}" font-size="4" fill="#0f172a">Nome: ____________________________ Telefone: ______________</text>
    </g>

    {{-- Rodapé --}}
    <g id="area-rodape">
        <text x="105" y="293" text-anchor="middle" font-size="3.5" fill="#64748b">{{ $game->name }}@if($game->card_price) · Valor da cartela: R$ {{ number_format((float) $game->card_price, 2, ',', '.') }}@endif</text>
    </g>
</svg>

@php
    /**
     * Folha A4 com arte personalizada importada + dados dinâmicos
     * posicionados nas áreas mapeadas. A arte define a aparência; os
     * números, identificadores e prêmios continuam controlados pelo
     * sistema.
     *
     * Variáveis: $part ['card','blocks'], $template, $mode ('web'|'pdf')
     */
    $card = $part['card'];
    $blocks = $part['blocks'];
    $isPdf = ($mode ?? 'web') === 'pdf';
    $artSrc = $isPdf ? ($templateDataUri ?? $template->artDataUri()) : $template->artUrl();
    $prizes = $card->prizesForPrint();
    $ptPerMm = 2.8346;
@endphp
<div class="template-sheet" style="position: relative; width: 210mm; height: 297mm; overflow: hidden; background: #fff;">
    @if ($artSrc)
        <img src="{{ $artSrc }}" alt="" style="position: absolute; left: 0; top: 0; width: 210mm; height: 297mm;">
    @else
        <div style="position: absolute; left: 0; top: 0; width: 210mm; height: 297mm; background: #f1f5f9;"></div>
        <div style="position: absolute; left: 20mm; top: 145mm; width: 170mm; text-align: center; font-size: 10pt; color: #475569;">
            A arte deste modelo ({{ strtoupper(pathinfo($template->art_path, PATHINFO_EXTENSION)) }})
            não pode ser embutida no PDF. Converta para PNG ou use a pré-visualização/impressão do navegador.
        </div>
    @endif

    @foreach (['header', 'code', 'prizes', 'buyer', 'stub'] as $areaKey)
        @php $area = $template->area($areaKey); @endphp
        @if ($area)
            <div style="position: absolute; left: {{ $area['x'] }}mm; top: {{ $area['y'] }}mm; width: {{ $area['w'] }}mm; height: {{ $area['h'] }}mm; overflow: hidden;">
                @if ($areaKey === 'header')
                    <div style="text-align: center; font-weight: bold;">
                        <p style="margin: 0; font-size: {{ round($area['h'] * $ptPerMm * 0.18, 1) }}pt; letter-spacing: 1pt;">{{ $game->organization_name }}</p>
                        <p style="margin: 0; font-size: {{ round($area['h'] * $ptPerMm * 0.22, 1) }}pt;">{{ $game->name }}</p>
                        @if ($game->event_date)
                            <p style="margin: 0; font-size: {{ round($area['h'] * $ptPerMm * 0.14, 1) }}pt;">{{ $game->event_date->format('d/m/Y') }}</p>
                        @endif
                    </div>
                @elseif ($areaKey === 'code')
                    <p style="margin: 0; text-align: center; font-weight: bold; font-size: {{ round($area['h'] * $ptPerMm * 0.5, 1) }}pt;">
                        Cartela {{ $card->code }}
                    </p>
                @elseif ($areaKey === 'prizes')
                    <p style="margin: 0 0 1mm; font-weight: bold; font-size: {{ round(min($area['h'] / max(1, count($prizes) + 1), 6) * $ptPerMm * 0.55, 1) }}pt; letter-spacing: 1pt;">PRÊMIOS DA AÇÃO ENTRE AMIGOS</p>
                    @foreach ($prizes as $i => $prize)
                        <p style="margin: 0; font-size: {{ round(min($area['h'] / max(1, count($prizes) + 1), 6) * $ptPerMm * 0.5, 1) }}pt;">
                            {{ $prize['sort_order'] ?? $i + 1 }}º {{ $prize['name'] }} — {{ $prize['rule_label'] }}@if (! empty($prize['estimated_value'])) — {{ $prize['estimated_value'] }}@endif — Bloco {{ $prize['sort_order'] ?? $i + 1 }}
                        </p>
                    @endforeach
                @elseif ($areaKey === 'buyer')
                    <p style="margin: 0; font-size: {{ round($area['h'] * $ptPerMm * 0.3, 1) }}pt;">Nome do comprador: ______________________________</p>
                    <p style="margin: 0; font-size: {{ round($area['h'] * $ptPerMm * 0.3, 1) }}pt;">Telefone: ______________________ Assinatura: ______________</p>
                @elseif ($areaKey === 'stub')
                    <p style="margin: 0; text-align: center; font-size: 7pt; color: #64748b;">✂ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</p>
                    <p style="margin: 0; font-size: {{ round($area['h'] * $ptPerMm * 0.22, 1) }}pt; font-weight: bold;">{{ $game->organization_name ?? 'AÇÃO ENTRE AMIGOS' }} · {{ $game->name }} · Cartela {{ $card->code }}</p>
                    <p style="margin: 0; font-size: {{ round($area['h'] * $ptPerMm * 0.22, 1) }}pt;">Nome: ______________________ Telefone: ______________</p>
                @endif
            </div>
        @endif
    @endforeach

    {{-- Blocos numéricos nas áreas mapeadas; blocos sem área vão para posição sequencial segura --}}
    @foreach ($blocks as $i => $block)
        @php
            $area = $template->area('blocks', $i)
                ?? ['x' => 10, 'y' => 66 + $i * 76, 'w' => 190, 'h' => 72];
            $useLetters = $game->card_format === '75';
            $rows = count($block->grid);
            $cols = count($block->grid[0]);
            $captionPt = round(min(8, $area['h'] * $ptPerMm * 0.08), 1);
        @endphp
        <div style="position: absolute; left: {{ $area['x'] }}mm; top: {{ $area['y'] }}mm; width: {{ $area['w'] }}mm; height: {{ $area['h'] }}mm; overflow: hidden;">
            <p style="margin: 0; font-size: {{ $captionPt }}pt; font-weight: bold;">
                {{ $block->label() }} · {{ $block->prize_name }} · {{ $block->prize_rule_label }}
            </p>
            <table style="width: {{ $area['w'] }}mm; border-collapse: collapse; table-layout: fixed;">
                @if ($useLetters)
                    <tr>
                        @foreach ($card->game->letters() as $letter)
                            <th style="border: 0.4mm solid #334155; background: #334155; color: #ffffff;
                                      font-size: {{ round($area['h'] * $ptPerMm * 0.07, 1) }}pt;
                                      padding: 0.6mm 0; width: {{ round($area['w'] / 5, 2) }}mm;">{{ $letter }}</th>
                        @endforeach
                    </tr>
                @endif
                @for ($r = 0; $r < $rows; $r++)
                    <tr>
                        @for ($c = 0; $c < $cols; $c++)
                            @php $value = $block->grid[$r][$c] ?? null; @endphp
                            <td style="border: 0.4mm solid #475569; text-align: center;
                                      width: {{ round($area['w'] / $cols, 2) }}mm;
                                      height: {{ round(($area['h'] - 8) / $rows, 2) }}mm;
                                      font-size: {{ round(($area['h'] - 8) / max($rows, $cols) * $ptPerMm * 0.45, 1) }}pt;
                                      font-weight: bold; {{ $value === null ? 'background: #e2e8f0;' : '' }}">{{ $value ?? '★' }}</td>
                        @endfor
                    </tr>
                @endfor
            </table>
        </div>
    @endforeach
</div>

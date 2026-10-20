@php
    // dompdf não carrega URLs externas: o logotipo é embutido como data URI
    $logoDataUri = null;
    if ($game->logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($game->logo_path)) {
        $logoDataUri = 'data:image/'.pathinfo($game->logo_path, PATHINFO_EXTENSION).';base64,'
            .base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($game->logo_path));
    }

    $theme = [
        'amber' => ['#b45309', '#fef3c7'],
        'emerald' => ['#047857', '#d1fae5'],
        'sky' => ['#0369a1', '#e0f2fe'],
        'rose' => ['#be123c', '#ffe4e6'],
        'violet' => ['#6d28d9', '#ede9fe'],
        'slate' => ['#334155', '#e2e8f0'],
    ][$game->theme_color] ?? ['#b45309', '#fef3c7'];

    $scale = $perPage === 1 ? 'big' : ($perPage === 4 ? 'small' : 'mid');
@endphp
<html lang="pt-BR">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        body { font-family: sans-serif; color: #0f172a; margin: 0; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        table.layout { width: 100%; border-collapse: separate; border-spacing: 4mm; }
        table.layout td.cell { vertical-align: top; width: 50%; }
        table.blocks { width: 100%; border-collapse: separate; border-spacing: 2.5mm; }
        table.blocks td.block-cell { vertical-align: top; }
        p.block-caption {
            margin: 0; border: 1px solid; border-bottom: 0; font-size: 8pt; font-weight: bold;
            padding: 1mm 2mm;
        }
        p.block-caption span { color: #0f172a; }

        .card { border: 2px solid #0f172a; border-radius: 6px; overflow: hidden; }
        table.head { width: 100%; border-collapse: collapse; }
        table.head td { padding: 2mm 3mm; vertical-align: middle; }
        td.head-brand { text-align: left; }
        td.head-meta { text-align: right; }
        table.head-qr-row { width: 100%; border-collapse: collapse; margin: 0; }
        table.head-qr-row td { border: 0; padding: 0 0 0 2mm; vertical-align: middle; text-align: right; }
        td.head-qr-cell { width: 13mm; }
        img.qr { width: 12mm; height: 12mm; }
        .org { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; margin: 0; }
        .event { font-size: 9pt; font-weight: bold; margin: 0; }
        .title { font-size: 20pt; font-weight: bold; letter-spacing: 2pt; margin: 0; line-height: 1; }
        .code { font-size: 9pt; font-weight: bold; margin: 0; }
        .date { font-size: 7.5pt; color: #334155; margin: 0; }
        img.logo { height: 13mm; }

        .prizes { border-bottom: 1px dashed #0f172a; padding: 1.5mm 3mm 1mm; }
        .prizes-label { font-size: 7.5pt; font-weight: bold; letter-spacing: 1.5pt; margin: 0 0 1mm; }
        .prizes p { font-size: 7.5pt; margin: 0 0 0.5mm; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background-color: {{ $theme[0] }}; color: #ffffff; font-size: 13pt; text-align: center; padding: 1.5mm 0; border: 1px solid #0f172a; font-weight: bold; }
        table.grid td { text-align: center; font-size: 20pt; font-weight: bold; border: 1px solid #475569; padding: 4.5mm 0; }

        .free { background-color: #e2e8f0; font-size: 14pt; }
        .buyer { border-top: 1px dashed #475569; padding: 2mm 3mm; font-size: 8.5pt; }
        .buyer p { margin: 1mm 0; }
        .stub-cut { text-align: center; font-size: 8pt; color: #64748b; margin: 0; }
        .stub { margin: 1.5mm 2mm 2mm; border: 1.5px solid #0f172a; border-radius: 4px; padding: 1.5mm 2.5mm; font-size: 8.5pt; }
        .stub p { margin: 0.5mm 0; }
        .price { margin: 0; padding: 1.5mm 3mm; color: #ffffff; font-size: 9pt; font-weight: bold; text-align: center; background-color: {{ $theme[0] }}; }

        .scale-big table.grid td { font-size: 30pt; padding: 8mm 0; }
        .scale-big table.grid th { font-size: 18pt; padding: 2.5mm 0; }
        .scale-big .title { font-size: 30pt; }
        .scale-small table.grid td { font-size: 13pt; padding: 2.5mm 0; }
        .scale-small .title { font-size: 13pt; }
        .scale-small .prizes p { font-size: 6.5pt; }
        .scale-small td.head-qr-cell { width: 9mm; }
        .scale-small img.qr { width: 8mm; height: 8mm; }

        .card--multi td.head-qr-cell { width: 10mm; }
        .card--multi img.qr { width: 9mm; height: 9mm; }

        /* Vários blocos por cartela: escala segura para caber no A4
           sem reduzir os números a tamanho ilegível */
        .card--multi table.grid td { font-size: 11pt; padding: 2.0mm 0; }
        .card--multi table.grid th { font-size: 7pt; padding: 1.0mm 0; }
        .card--multi .title { font-size: 14pt; }
        .card--multi .org { font-size: 6.5pt; }
        .card--multi .event { font-size: 8pt; }
        .card--multi .code { font-size: 8pt; }
        .card--multi .prizes p { font-size: 6.5pt; }
        .card--multi p.block-caption { font-size: 6.5pt; padding: 0.8mm 1.5mm; }
        .card--multi .buyer p, .card--multi .stub p { font-size: 7pt; }
        .card--multi .stub { padding: 1mm 2mm; margin: 1mm; }
        .card--multi .price { font-size: 7.5pt; padding: 1mm 2mm; }
    </style>
</head>
<body>
    @php
        $useTemplate = $template && $template->supportsPdfEmbed();
        $templateDataUri = $useTemplate ? $template->artDataUri() : null;
    @endphp
    @if ($useTemplate)
        @foreach ($parts as $part)
            <div class="page">
                @include('cards._template_sheet', ['part' => $part, 'mode' => 'pdf', 'templateDataUri' => $templateDataUri])
            </div>
        @endforeach
    @else
        @php
            $columns = $perPage === 1 ? 1 : 2;
            $pageChunks = collect($parts)->chunk($perPage);
            $multiBlocks = $blocksCount > 1;
        @endphp
        @foreach ($pageChunks as $pageChunk)
            <div class="page">
                @php $rowChunks = $pageChunk->chunk($columns); @endphp
                @foreach ($rowChunks as $rowChunk)
                    <table class="layout"><tr>
                        @foreach ($rowChunk as $part)
                            <td class="cell">@include('cards._card_pdf', ['card' => $part['card'], 'blocks' => $part['blocks'], 'multiBlocks' => $multiBlocks])</td>
                        @endforeach
                        @for ($i = $rowChunk->count(); $i < $columns; $i++)
                            <td class="cell"></td>
                        @endfor
                    </tr></table>
                @endforeach
            </div>
        @endforeach
    @endif
</body>
</html>

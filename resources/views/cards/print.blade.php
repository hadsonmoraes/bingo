<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cartelas — {{ $game->name }}</title>
    @vite('resources/css/app.css')
    <style>
        body { background: #e2e8f0; padding-top: 64px; }
        .no-print {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
            padding: 10px 16px; background: #0f172a; color: #fff;
        }
        .no-print form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .no-print label { display: flex; align-items: center; gap: 4px; font-size: 13px; }
        .no-print select { border-radius: 8px; border: 0; padding: 4px 8px; }
        .no-print button {
            padding: 8px 20px; border-radius: 8px; border: 0; font-weight: 700;
            cursor: pointer; background: #fbbf24; color: #0f172a;
        }
        .no-print a { color: #93c5fd; font-size: 13px; }

        /* Folha A4 física (210 x 297 mm) — prévia fiel ao tamanho de impressão */
        .sheet {
            width: 210mm; min-height: 297mm; margin: 16px auto; padding: 8mm;
            background: #fff; box-sizing: border-box; display: grid; gap: 10mm;
            align-content: start; box-shadow: 0 2px 16px rgb(15 23 42 / 0.25);
        }
        .template-page { width: 210mm; height: 297mm; margin: 16px auto; background: #fff; box-shadow: 0 2px 16px rgb(15 23 42 / 0.25); overflow: hidden; page-break-after: always; }
        @media print { .template-page { margin: 0; box-shadow: none; } }
        .sheet--1 { grid-template-columns: 1fr; }
        .sheet--2 { grid-template-columns: 1fr 1fr; }
        .sheet--4 { grid-template-columns: 1fr 1fr; }
        @media screen and (max-width: 900px) {
            .sheet { transform: scale(0.62); transform-origin: top center; margin-bottom: -110mm; }
        }

        .bingo-card {
            background: #fff; border: 3px solid #0f172a; border-radius: 10px;
            overflow: hidden; page-break-inside: avoid; break-inside: avoid;
            box-shadow: 0 4px 12px rgb(15 23 42 / 0.15);
            display: flex; flex-direction: column;
        }

        .card-header {
            display: flex; justify-content: space-between; align-items: center; gap: 8px;
            padding: 3mm 4mm; border-bottom: 3px solid #0f172a;
        }
        .card-header__brand { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .card-header__brand img { height: 14mm; max-width: 22mm; object-fit: contain; }
        .card-header__org { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .card-header__event { font-size: 10px; font-weight: 700; color: #0f172a; }
        .card-header__texts { min-width: 0; }
        .card-header__meta { text-align: right; }
        .card-header__title { font-size: 22px; font-weight: 900; letter-spacing: 2px; line-height: 1; color: #0f172a; }
        .card-header__code { font-size: 11px; font-weight: 600; color: #0f172a; }
        .card-header__date { font-size: 9px; color: #334155; }

        .card-prizes { border-bottom: 1px dashed #0f172a; padding: 2mm 4mm 1.5mm; }
        .card-prizes__label { font-size: 8.5px; font-weight: 900; letter-spacing: 1.5px; }
        .card-prizes__list { list-style: none; display: flex; flex-wrap: wrap; gap: 2mm 5mm; font-size: 8.5px; color: #0f172a; }
        .card-prizes__list em { font-style: normal; color: #475569; }
        .card-prizes__block { color: #475569; font-weight: 700; }

        /* Blocos numéricos: um por prêmio, legendas fora das grades */
        .card-blocks {
            display: grid; gap: 3mm; padding: 2.5mm 4mm; flex: 1;
            grid-template-columns: repeat(var(--blocks-per-row, 3), 1fr);
        }
        .card-block { min-width: 0; }
        .card-block__caption {
            display: flex; flex-wrap: wrap; gap: 2mm; align-items: baseline;
            font-size: 8px; font-weight: 700; border: 1px solid; border-bottom: 0;
            padding: 1mm 2mm; margin: 0;
        }
        .card-block__caption span { color: #0f172a; }
        .card-block__caption em { font-style: normal; color: #475569; }
        .card-block .card-grid { border-top: 0; }
        .card-block .card-grid td { font-size: 13px; padding: 2.0mm 0; }
        .card-block .card-grid th { font-size: 9px; padding: 1.0mm 0; }
        .card-blocks--1 .card-block .card-grid td { font-size: 24px; padding: 5mm 0; }
        .card-blocks--1 .card-block .card-grid th { font-size: 16px; padding: 2mm 0; }
        .card-blocks--90 .card-block .card-grid td { font-size: 10px; padding: 1.8mm 0; }

        .card-grid { width: 100%; border-collapse: collapse; table-layout: fixed; flex: 1; }
        .card-grid th { font-size: 14px; padding: 1.5mm 0; color: #fff; border: 1px solid #0f172a; font-weight: 900; }
        .card-grid td {
            text-align: center; font-weight: 800; color: #0f172a;
            border: 1px solid #475569; padding: 4mm 0; font-size: 22px;
        }
        .card-grid__free { background: repeating-linear-gradient(45deg, #e2e8f0, #e2e8f0 4px, #cbd5e1 4px, #cbd5e1 8px); font-size: 16px; }

        .card-buyer { padding: 3mm 4mm; border-top: 1px dashed #475569; font-size: 10px; color: #0f172a; }
        .card-buyer__row { padding: 1.5mm 0; }
        .card-buyer--compact { padding: 2mm 4mm; }

        .card-stub { border-top: 2px dashed #475569; }
        .card-stub__cut { text-align: center; font-size: 9px; color: #64748b; padding: 0.5mm 0; }
        .card-stub__body { margin: 2mm; border: 2px solid #0f172a; border-radius: 6px; padding: 2mm 3mm; font-size: 10px; }
        .card-stub__title { font-weight: 900; text-transform: uppercase; letter-spacing: 1px; font-size: 9px; }
        .card-stub__event { font-weight: 700; }
        .card-stub__fields { margin-top: 1.5mm; }

        .card-price { margin: 0; padding: 1.5mm 4mm; color: #fff; font-size: 11px; font-weight: 800; text-align: center; }

        /* Escala conforme cartelas por página (apenas cartelas de bloco único) */
        .bingo-card--1:not(.has-multi-blocks) .card-grid td { font-size: 34px; padding: 7mm 0; }
        .bingo-card--1:not(.has-multi-blocks) .card-grid th { font-size: 20px; padding: 2.5mm 0; }
        .bingo-card--1:not(.has-multi-blocks) .card-header__title { font-size: 34px; }
        .bingo-card--1:not(.has-multi-blocks) .card-prizes__list { font-size: 11px; }
        .bingo-card--4:not(.has-multi-blocks) .card-grid td { font-size: 15px; padding: 2.5mm 0; }
        .bingo-card--4:not(.has-multi-blocks) .card-header__title { font-size: 15px; }
        .bingo-card--4:not(.has-multi-blocks) .card-prizes__list { font-size: 7.5px; }
        .bingo-card.has-multi-blocks .card-grid td { font-size: 13px; padding: 2.0mm 0; }
        .bingo-card.has-multi-blocks .card-grid th { font-size: 9px; padding: 1.0mm 0; }
        .bingo-card.has-multi-blocks .card-header__title { font-size: 20px; }
        .bingo-card.has-multi-blocks .card-blocks--90 .card-grid td { font-size: 10px; padding: 1.8mm 0; }

        @media print {
            .no-print { display: none; }
            body { background: #fff; padding: 0; }
            .sheet { max-width: none; margin: 0; display: grid !important; }
            .sheet--2 { grid-template-columns: 1fr 1fr; }
            .sheet--4 { grid-template-columns: 1fr 1fr; }
            .bingo-card { box-shadow: none; }
            .page-break { page-break-after: always; }
            @page { size: A4 portrait; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <strong>Pré-visualização</strong>
        @if ($layout['warning'])
            <span style="background:#b45309; border-radius:6px; padding:4px 10px; font-size:12px; font-weight:700">
                ⚠ {{ $blocksCount }} blocos não cabem de forma legível em uma única folha A4.
            </span>
        @endif
        @if ($template && $templateWarnings)
            <span style="background:#b91c1c; border-radius:6px; padding:4px 10px; font-size:12px; font-weight:700">
                ⚠ Arte sem áreas para: {{ implode(', ', $templateWarnings) }}
            </span>
        @endif
        <form method="GET">
            <label>Arte
                <select name="template" onchange="this.form.submit()">
                    <option value="">Modelo padrão do evento</option>
                    <option value="none" {{ request('template') === 'none' ? 'selected' : '' }}>Sem arte personalizada</option>
                    @foreach ($game->cardTemplates as $tpl)
                        <option value="{{ $tpl->id }}" {{ (string) request('template') === (string) $tpl->id ? 'selected' : '' }}>{{ $tpl->name }}</option>
                    @endforeach
                </select>
            </label>
            @if ($blocksCount <= 1)
                <label>Por página
                    <select name="per_page" onchange="this.form.submit()">
                        <option value="1" {{ $perPage === 1 ? 'selected' : '' }}>1 cartela</option>
                        <option value="2" {{ $perPage === 2 ? 'selected' : '' }}>2 cartelas</option>
                        <option value="4" {{ $perPage === 4 ? 'selected' : '' }}>4 cartelas</option>
                    </select>
                </label>
            @else
                <span style="font-size:12px">1 cartela por folha ({{ $blocksCount }} blocos)</span>
                @if ($layout['warning'])
                    <label><input type="checkbox" name="split_blocks" value="1" {{ request()->boolean('split_blocks') ? 'checked' : '' }} onchange="this.form.submit()"> Dividir blocos em mais de uma folha</label>
                @endif
            @endif
            <label><input type="checkbox" name="stub" value="1" {{ $stub ? 'checked' : '' }} onchange="this.form.submit()"> Com canhoto</label>
            <label><input type="checkbox" name="prizes" value="1" {{ $showPrizes ? 'checked' : '' }} onchange="this.form.submit()"> Prêmios</label>
            <label><input type="checkbox" name="price" value="1" {{ $showPrice ? 'checked' : '' }} onchange="this.form.submit()"> Valor</label>
            <label><input type="checkbox" name="buyer" value="1" {{ $showBuyer ? 'checked' : '' }} onchange="this.form.submit()"> Comprador</label>
            <label><input type="checkbox" name="date" value="1" {{ $showDate ? 'checked' : '' }} onchange="this.form.submit()"> Data</label>
            <input type="hidden" name="card" value="{{ request('card') }}">
            <button type="button" onclick="window.print()">🖨 Imprimir</button>
        </form>
        <a href="{{ route('games.cards.pdf', $game) }}?{{ http_build_query(request()->query()) }}">⬇ Baixar PDF A4</a>
        <a href="{{ route('games.show', $game) }}">← Voltar</a>
    </div>

    @if ($layout['warning'] && ! request()->boolean('split_blocks'))
        <div class="no-print" style="background:#7c2d12">
            <strong>⚠ Aviso de layout:</strong> esta cartela possui {{ $blocksCount }} blocos numéricos —
            acima do limite legível por folha ({{ $layout['blocks_per_sheet'] }}). Ative
            <em>“Dividir blocos em mais de uma folha”</em> acima ou reduza a quantidade de prêmios.
            Os números não serão reduzidos a um tamanho ilegível.
        </div>
    @endif

    @if ($template)
        {{-- Folha A4 por cartela com a arte personalizada aplicada --}}
        @foreach ($parts as $part)
            <div class="template-page">
                @include('cards._template_sheet', ['part' => $part, 'mode' => 'web'])
            </div>
        @endforeach
    @else
        <div class="sheet sheet--{{ $perPage }}">
            @foreach ($parts as $part)
                @include('cards._card', ['card' => $part['card'], 'blocks' => $part['blocks']])
            @endforeach
        </div>
    @endif
</body>
</html>

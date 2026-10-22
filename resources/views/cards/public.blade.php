<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020617">
    <title>Cartela {{ $card->code }} — {{ $game->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/card.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div id="state-data" hidden>{{ json_encode($state) }}</div>

    @php
        $blocks = $card->effectiveBlocks();
        $useLetters = $game->card_format === '75';
        $statusLabels = [
            'setup' => 'Aguardando início',
            'in_progress' => 'Partida em andamento',
            'paused' => 'Partida pausada',
            'finished' => 'Partida encerrada',
        ];
        $theme = [
            'amber' => '#fbbf24',
            'emerald' => '#34d399',
            'sky' => '#38bdf8',
            'rose' => '#fb7185',
            'violet' => '#a78bfa',
            'slate' => '#94a3b8',
        ][$game->theme_color] ?? '#fbbf24';
    @endphp

    <div class="mx-auto max-w-lg px-4 pb-10 pt-5">
        {{-- Cabeçalho --}}
        <header class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($game->organization_name)
                    <p class="truncate text-[11px] font-bold uppercase tracking-widest" style="color: {{ $theme }}">{{ $game->organization_name }}</p>
                @endif
                <h1 class="truncate text-lg font-black leading-tight">{{ $game->name }}</h1>
                <p class="mt-0.5 text-xs font-bold text-slate-400">Sua cartela: <span class="text-white">{{ $card->code }}</span></p>
            </div>
            <span id="status-badge"
                class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ ($state['status'] ?? 'setup') === 'in_progress' ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-300' }}">
                {{ $statusLabels[$state['status'] ?? 'setup'] ?? '' }}
            </span>
        </header>

        {{-- Última pedra e contadores --}}
        <div class="mt-4 grid grid-cols-[auto_1fr] items-center gap-4 rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800">
            <div class="text-center">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Última pedra</p>
                <p class="leading-none">
                    <span id="last-letter" class="text-2xl font-black" style="color: {{ $theme }}"></span>
                    <span id="last-number" class="text-6xl font-black tracking-tight">--</span>
                </p>
            </div>
            <div class="text-right text-sm leading-6">
                <p><span class="font-bold text-slate-400">Sorteadas:</span> <span id="drawn-count" class="font-black">0</span></p>
                <p><span class="font-bold text-slate-400">Restam:</span> <span id="remaining-count" class="font-black">{{ $state['remaining_count'] ?? 0 }}</span></p>
                <p class="mt-1 border-t border-slate-800 pt-1"><span class="font-bold text-slate-400">Prêmio em disputa:</span>
                    <span id="current-prize-name" class="font-black" style="color: {{ $theme }}">—</span></p>
            </div>
        </div>

        {{-- Blocos numéricos: um por prêmio --}}
        @foreach ($blocks as $index => $block)
            <section data-block="{{ $index }}" class="mt-4 overflow-hidden rounded-2xl bg-slate-900 ring-1 ring-slate-800">
                <div class="flex items-center justify-between gap-2 border-b border-slate-800 bg-slate-900 px-3 py-2">
                    <p class="min-w-0 truncate text-xs font-bold">
                        <strong>{{ $block->label() }}</strong>
                        <span class="text-slate-400">· {{ $block->prize_name }} · {{ $block->prize_rule_label }}</span>
                    </p>
                    <span data-missing
                        class="shrink-0 rounded-full bg-slate-800 px-2.5 py-0.5 text-[11px] font-black text-slate-300"></span>
                </div>
                <table class="w-full table-fixed border-collapse">
                    @if ($useLetters)
                        <tr>
                            @foreach ($game->letters() as $letter)
                                <th class="border border-slate-800 py-1 text-sm font-black" style="background: {{ $theme }}; color: #020617;">{{ $letter }}</th>
                            @endforeach
                        </tr>
                    @endif
                    @for ($r = 0; $r < count($block->grid); $r++)
                        <tr>
                            @for ($c = 0; $c < count($block->grid[0]); $c++)
                                @php $value = $block->grid[$r][$c] ?? null; @endphp
                                @if ($value === null)
                                    <td class="border border-slate-800 bg-slate-800/60 py-2.5 text-center text-base font-black text-slate-500">★</td>
                                @else
                                    <td data-number="{{ $value }}"
                                        class="cell border border-slate-800 bg-slate-950 py-2.5 text-center text-xl font-black text-slate-300 transition-colors">{{ $value }}</td>
                                @endif
                            @endfor
                        </tr>
                    @endfor
                </table>
            </section>
        @endforeach

        {{-- Ganhadores --}}
        <section class="mt-4 rounded-2xl bg-slate-900 p-3 ring-1 ring-slate-800">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Ganhadores validados</p>
            <ul id="winners-list" class="mt-1.5 space-y-1 text-sm"></ul>
            <p id="winners-empty" class="mt-1.5 text-sm text-slate-500">Nenhum ganhador até agora.</p>
        </section>

        <p class="mt-4 text-center text-[11px] text-slate-600">
            Esta página atualiza sozinha — deixe aberta no celular para acompanhar o sorteio.
        </p>
    </div>

    {{-- Destaque quando um dos seus blocos completa o prêmio --}}
    <div id="bingo-overlay" class="hidden fixed inset-0 z-50 flex flex-col items-center justify-center bg-emerald-950/95 text-center">
        <p class="text-5xl">🎉</p>
        <p class="mt-2 text-3xl font-black uppercase tracking-widest text-amber-300">Bingo!</p>
        <p class="mt-2 px-6 text-lg font-bold">Um bloco da sua cartela completou o prêmio. Chame o operador para validar!</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $game->name }} — Bingo (tela pública)</title>
    @vite(['resources/css/app.css', 'resources/js/screen.js'])
</head>
<body class="h-screen overflow-hidden bg-slate-950 text-white">
    <div id="state-data" hidden>{{ json_encode($state) }}</div>

    {{-- Banner: aguardando início --}}
    <div id="waiting-banner" class="hidden absolute inset-0 z-40 flex flex-col items-center justify-center bg-slate-950/95 text-center">
        <p class="text-6xl font-black text-amber-400">BINGO</p>
        <p class="mt-6 text-3xl font-bold">Aguardando o início da partida…</p>
        <p class="mt-3 text-xl text-slate-400">{{ $game->name }}</p>
    </div>

    {{-- Banner: partida pausada --}}
    <div id="paused-banner" class="hidden absolute inset-0 z-40 flex flex-col items-center justify-center bg-slate-950/95 text-center">
        <p class="text-7xl font-black tracking-wide text-amber-400">⏸</p>
        <p class="mt-6 text-4xl font-black uppercase tracking-widest">Partida pausada</p>
    </div>

    {{-- Banner: ganhador --}}
    <div id="winner-overlay" class="hidden absolute inset-0 z-50 flex items-center justify-center bg-emerald-950/95 text-center ring-8 ring-emerald-500/50">
        <div id="winner-message" class="px-8"></div>
    </div>

    <div class="flex h-full flex-col gap-4 p-6">
        {{-- Cabeçalho --}}
        <div class="flex items-center justify-between">
            <div class="flex items-baseline gap-4">
                <h1 id="game-name" class="text-3xl font-black">{{ $game->name }}</h1>
                <span class="rounded-full bg-amber-500 px-4 py-1 text-xl font-black text-slate-900">
                    Restam <span id="remaining-count">{{ $state['remaining_count'] }}</span>
                </span>
                <span id="na-boa-badge" class="rounded-full bg-emerald-600 px-4 py-1 text-xl font-black text-white">
                    <span id="na-boa-count">{{ $state['cards_summary']['na_boa'] ?? 0 }}</span> na boa
                </span>
            </div>
            <div class="text-right">
                <p class="text-sm font-semibold uppercase tracking-widest text-slate-400">Prêmio em disputa</p>
                <p id="prize-name" class="text-2xl font-black text-amber-400">—</p>
                <p id="prize-rule" class="text-base font-semibold text-slate-300">—</p>
                <p id="prize-position" class="text-sm font-semibold text-slate-400">—</p>
            </div>
        </div>

        {{-- Última pedra + histórico --}}
        <div class="flex items-center gap-8">
            <div class="flex h-64 w-80 shrink-0 flex-col items-center justify-center rounded-3xl bg-white shadow-2xl">
                <span id="last-letter" class="text-8xl font-black leading-none text-amber-500"></span>
                <span id="last-number" class="text-9xl font-black leading-none text-slate-900">--</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="mb-2 text-sm font-bold uppercase tracking-widest text-slate-400">Pedras anteriores</p>
                <div id="history" class="flex max-h-56 flex-wrap gap-2 overflow-y-auto"></div>
            </div>
        </div>

        {{-- Painel de números --}}
        <div class="min-h-0 flex-1 rounded-3xl bg-slate-900 p-5 ring-1 ring-white/10">
            <div id="board" class="h-full"></div>
        </div>
    </div>

    <button id="fullscreen-btn" type="button"
            class="fixed bottom-4 right-4 z-50 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20">
        ⛶ Tela cheia
    </button>

    <button id="sound-toggle" type="button" title="Ativar/desativar sons"
            class="fixed bottom-4 right-32 z-50 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20">
        🔊
    </button>
</body>
</html>

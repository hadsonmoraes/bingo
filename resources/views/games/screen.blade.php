<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $game->name }} — Bingo (tela pública)</title>
    @vite(['resources/css/app.css', 'resources/js/screen.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div id="state-data" hidden>{{ json_encode($state) }}</div>

    {{-- Banner: aguardando início --}}
    <div id="waiting-banner" class="hidden absolute inset-0 z-40 flex flex-col items-center justify-center bg-slate-950/95 text-center">
        <p class="text-6xl font-black text-amber-400">{{ $game->lettersWord() }}</p>
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

    <div class="flex flex-col gap-4 p-4 sm:p-6">
        {{-- Cabeçalho --}}
        <div class="flex flex-wrap items-center justify-between gap-2 sm:gap-4">
            <div class="flex flex-wrap items-baseline gap-2 sm:gap-4">
                <h1 id="game-name" class="text-xl font-black sm:text-3xl">{{ $game->name }}</h1>
                <span class="rounded-full bg-amber-500 px-3 py-1 text-sm font-black text-slate-900 sm:px-4 sm:text-xl">
                    Restam <span id="remaining-count">{{ $state['remaining_count'] }}</span>
                </span>
                <span id="na-boa-badge" class="rounded-full bg-emerald-600 px-3 py-1 text-sm font-black text-white sm:px-4 sm:text-xl">
                    <span id="na-boa-count">{{ $state['cards_summary']['na_boa'] ?? 0 }}</span> na boa
                </span>
            </div>
            <div class="text-left sm:text-right">
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 sm:text-sm">Prêmio em disputa</p>
                <p id="prize-name" class="text-lg font-black text-amber-400 sm:text-2xl">—</p>
                <p id="prize-rule" class="text-sm font-semibold text-slate-300 sm:text-base">—</p>
                <p id="prize-position" class="text-xs font-semibold text-slate-400 sm:text-sm">—</p>
            </div>
        </div>

        {{-- Última pedra + histórico --}}
        <div class="flex flex-col items-stretch gap-4 sm:flex-row sm:items-center sm:gap-8">
            <div class="flex h-36 w-full shrink-0 flex-col items-center justify-center rounded-3xl bg-white shadow-2xl sm:h-64 sm:w-80">
                <span id="last-letter" class="text-6xl font-black leading-none text-amber-500 sm:text-8xl"></span>
                <span id="last-number" class="text-7xl font-black leading-none text-slate-900 sm:text-9xl">--</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="mb-2 text-xs font-bold uppercase tracking-widest text-slate-400 sm:text-sm">Pedras anteriores</p>
                <div id="history" class="flex max-h-40 flex-wrap gap-2 overflow-y-auto sm:max-h-56"></div>
            </div>
        </div>

        {{-- Painel de números --}}
        <div class="min-h-0 flex-1 rounded-3xl bg-slate-900 p-3 ring-1 ring-white/10 max-sm:min-h-[60vh] sm:p-5">
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

@extends('layouts.app')
@section('title', 'Sorteio — ' . $game->name)

@section('content')
    <div id="state-data" hidden>{{ json_encode($state) }}</div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $game->name }}</h1>
            <p class="text-sm text-slate-500">Registro manual das pedras do globo físico</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('games.show', $game) }}"
                class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">Voltar</a>
            <a href="{{ route('games.screen', $game) }}" target="_blank" rel="noopener"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Tela pública
                ↗</a>
            <button id="start-btn"
                class="hidden rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500">Iniciar
                partida</button>
            <button id="pause-btn"
                class="hidden rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-amber-400">Pausar</button>
            <button id="resume-btn"
                class="hidden rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500">Retomar</button>
            <button id="finish-btn"
                class="hidden rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500">Encerrar</button>
        </div>
    </div>

    <div id="alert-box" class="mt-4 hidden" role="alert"></div>

    <div class="mt-4 grid gap-6">
        {{-- Painel principal: registro da pedra em tela cheia --}}
        <div class="space-y-6">
            <div class="relative rounded-2xl bg-white p-4 shadow-sm">
                <div id="paused-overlay" class="absolute inset-0 z-10 hidden rounded-2xl bg-slate-900/80 backdrop-blur-sm">
                    <div class="flex h-full flex-col items-center justify-center gap-2 text-white">
                        <p class="text-3xl font-black tracking-wide">⏸ PARTIDA PAUSADA</p>
                        <p class="text-sm text-slate-300">Retome a partida para registrar novas pedras.</p>
                    </div>
                </div>

                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Última pedra registrada</p>
                <div class="grid grid-cols-4 gap-2">
                    <div id="last-display"
                        class="mt-2 flex items-center justify-center gap-3 rounded-xl bg-slate-900 px-4 py-3">
                        <span id="last-letter" class="text-3xl font-black text-amber-400">—</span>
                        <span id="last-number" class="text-4xl font-black leading-none text-white">--</span>
                    </div>

                    <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                        <p class="text-[10px] font-semibold uppercase text-slate-500">Sorteadas</p>
                        <p id="drawn-count" class="text-2xl font-black text-slate-900">0</p>
                    </div>
                    <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                        <p class="text-[10px] font-semibold uppercase text-slate-500">Restantes</p>
                        <p id="remaining-count" class="text-2xl font-black text-slate-900">0</p>
                    </div>
                    <div class="rounded-xl bg-white p-3 text-center shadow-sm">
                        <p class="text-[10px] font-semibold uppercase text-slate-500">Possíveis</p>
                        <p class="text-2xl font-black text-slate-900">{{ $state['total_numbers'] }}</p>
                    </div>
                </div>
                <label class="mt-6 block text-sm font-semibold text-slate-700">
                    Painel de números — clique na pedra sorteada
                </label>
                <div id="keypad" class="mt-4 -mx-4 w-full"></div>

                <div class="px-4 pb-4">
                    <button id="undo-btn" type="button"
                        class="w-full rounded-xl border-2 border-red-200 bg-red-50 py-3 text-sm font-bold text-red-700 transition hover:bg-red-100">
                        ↩ Desfazer último registro
                    </button>
                </div>
            </div>

            <div class="rounded-xl bg-slate-900 p-4 shadow-sm">
                <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">Histórico da rodada</p>
                <div id="history" class="flex max-h-32 flex-wrap gap-1.5 overflow-y-auto"></div>
            </div>


            {{-- Validação de ganhador --}}
            <div id="winner-panel" class="rounded-2xl bg-white p-4 shadow-sm">
                <h2 class="font-bold">Verificar ganhador</h2>
                <p class="mt-1 text-xs text-slate-500">Prêmio em disputa: <strong id="current-prize-name">—</strong></p>
                <p class="mt-1 text-xs text-slate-500">
                    Informe o código da cartela e selecione o prêmio: o sistema valida
                    automaticamente o <strong>bloco numérico</strong> correspondente a esse prêmio.
                </p>

                <div class="mt-4 space-y-3">
                    <input id="card-code" type="text" placeholder="Código da cartela (ex.: C-0001)"
                        class="w-full rounded-xl border-slate-300 font-mono uppercase focus:border-amber-500 focus:ring-amber-500">
                    <select id="prize-select"
                        class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Selecione o prêmio…</option>
                    </select>
                    <input id="winner-name" type="text"
                        placeholder="Nome do ganhador (opcional — usa o comprador se vazio)"
                        class="w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    <button id="validate-btn" type="button"
                        class="w-full rounded-xl bg-emerald-600 py-3 font-bold text-white hover:bg-emerald-500">
                        Validar ganhador
                    </button>
                </div>

                <ul id="winners-list" class="mt-4 space-y-2"></ul>
            </div>
        </div>


        {{-- Coluna direita: painel, histórico e contadores --}}
        <div class="space-y-4">
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold">Cartelas na boa</h2>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                        Faltam até
                        <select id="threshold-select" class="rounded-lg border-slate-300 py-1 text-sm font-semibold">
                            <option value="0">0</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                        números
                    </label>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Considera o prêmio em disputa e a melhor forma de completar a condição de vitória.
                </p>
                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-xl bg-slate-100 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-slate-500">Cartelas em jogo</p>
                        <p id="cs-in-play" class="text-2xl font-black text-slate-900">0</p>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-slate-500">Blocos em jogo</p>
                        <p id="cs-blocks-in-play" class="text-2xl font-black text-slate-900">0</p>
                    </div>
                    <div class="rounded-xl bg-amber-100 p-3 text-center ring-2 ring-amber-300">
                        <p class="text-[10px] font-bold uppercase text-amber-700">Cartelas na boa</p>
                        <p id="cs-na-boa" class="text-2xl font-black text-amber-700">0</p>
                    </div>
                    <div class="rounded-xl bg-amber-100 p-3 text-center ring-2 ring-amber-300">
                        <p class="text-[10px] font-bold uppercase text-amber-700">Blocos na boa</p>
                        <p id="cs-na-boa-blocks" class="text-2xl font-black text-amber-700">0</p>
                    </div>
                    <div class="rounded-xl bg-slate-100 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-slate-500">Distantes</p>
                        <p id="cs-far" class="text-2xl font-black text-slate-900">0</p>
                    </div>
                    <div class="rounded-xl bg-emerald-100 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-emerald-700">Cartelas vencedoras</p>
                        <p id="cs-winners" class="text-2xl font-black text-emerald-700">0</p>
                    </div>
                    <div class="rounded-xl bg-emerald-100 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-emerald-700">Blocos vencedores</p>
                        <p id="cs-winning-blocks" class="text-2xl font-black text-emerald-700">0</p>
                    </div>
                    <div class="rounded-xl bg-red-50 p-3 text-center">
                        <p class="text-[10px] font-bold uppercase text-red-600">Canceladas</p>
                        <p id="cs-cancelled" class="text-2xl font-black text-red-600">0</p>
                    </div>
                </div>
                <div class="mt-4 max-h-56 space-y-1.5 overflow-y-auto">
                    <div id="na-boa-list"></div>
                </div>
            </div>

            <div class="rounded-2xl bg-slate-900 p-5 shadow hidden">
                <p class="mb-3 text-xs font-bold uppercase tracking-widest text-slate-400">Painel de números</p>
                <div id="board"></div>
            </div>
        </div>
    </div>

    {{-- Anúncio de ganhador validado --}}
    <div id="winner-overlay"
        class="pointer-events-none fixed inset-0 z-[60] hidden items-center justify-center bg-emerald-900/90 p-4">
        <div class="max-w-xl rounded-3xl bg-white p-10 text-center shadow-2xl">
            <p class="text-4xl font-black tracking-tight text-emerald-600">TEMOS UM GANHADOR!</p>
            <div id="winner-overlay-details" class="mt-4 space-y-1 text-lg text-slate-800"></div>
        </div>
    </div>

    {{-- Modal de confirmação: desfazer validação de ganhador --}}
    <div id="winner-undo-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/70 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <h3 class="text-lg font-bold">Desfazer validação de ganhador</h3>
            <p class="mt-2 text-sm text-slate-600">
                O prêmio voltará à disputa e a cartela/bloco retornarão ao jogo.
                Use quando a cartela não foi vendida ou a validação foi feita por engano.
                A correção ficará registrada no histórico da partida.
            </p>
            <label for="winner-undo-reason" class="mt-4 block text-sm font-semibold text-slate-700">Motivo da
                correção</label>
            <input id="winner-undo-reason" type="text" placeholder="Ex.: cartela não foi vendida"
                class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
            <div class="mt-5 flex justify-end gap-3">
                <button id="winner-undo-cancel" type="button"
                    class="rounded-xl px-4 py-2 font-semibold text-slate-600 hover:text-slate-900">Cancelar</button>
                <button id="winner-undo-confirm" type="button"
                    class="rounded-xl bg-red-600 px-5 py-2 font-bold text-white hover:bg-red-500">Desfazer
                    validação</button>
            </div>
        </div>
    </div>

    {{-- Modal de confirmação da correção --}}
    <div id="undo-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-900/70 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
            <h3 class="text-lg font-bold">Confirmar correção</h3>
            <p class="mt-2 text-sm text-slate-600">
                Você está desfazendo o registro da pedra
                <strong id="undo-target" class="text-slate-900"></strong>.
                A correção ficará registrada no histórico da partida.
            </p>
            <label for="undo-reason" class="mt-4 block text-sm font-semibold text-slate-700">Motivo da correção</label>
            <input id="undo-reason" type="text" placeholder="Ex.: pedra identificada incorretamente"
                class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
            <div class="mt-5 flex justify-end gap-3">
                <button id="undo-cancel" type="button"
                    class="rounded-xl px-4 py-2 font-semibold text-slate-600 hover:text-slate-900">Cancelar</button>
                <button id="undo-confirm" type="button"
                    class="rounded-xl bg-red-600 px-5 py-2 font-bold text-white hover:bg-red-500">Desfazer
                    registro</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/draw.js')
@endpush

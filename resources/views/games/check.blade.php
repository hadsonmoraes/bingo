@extends('layouts.app')
@section('title', 'Conferir cartela — ' . $game->name)

@section('content')
    <div class="mx-auto max-w-2xl">

        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h1 class="text-lg font-bold leading-tight">Conferir cartela</h1>
                <p class="text-xs text-slate-500">{{ $game->name }} — escaneie o código de barras do canhoto ou digite o código</p>
            </div>
            <a href="{{ route('games.draw', $game) }}"
                class="rounded-lg bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-300">Ir para o sorteio</a>
        </div>

        <div id="alert-box" class="mt-3 hidden" role="alert"></div>

        <div class="mt-3 rounded-2xl bg-white p-4 shadow-sm">
            <div class="flex gap-1.5">
                <input id="check-card" type="text" autofocus autocomplete="off" spellcheck="false"
                    placeholder="Código da cartela (ex.: C-0001)"
                    class="flex-1 rounded-xl border-slate-300 py-2.5 px-4 font-mono text-base uppercase focus:border-amber-500 focus:ring-amber-500">
                <button id="check-btn" type="button"
                    class="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-500"
                    title="Conferir a cartela">
                    Conferir
                </button>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                Leitores de código de barras enviam “texto + Enter” — a conferência acontece sozinha ao final da leitura.
            </p>

            <div id="check-result" class="mt-3 hidden text-sm"></div>
        </div>

        <div class="mt-3 rounded-2xl bg-white p-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-700">Status de uma cartela</h2>
            <ul class="mt-2 space-y-1.5 text-xs text-slate-600">
                <li><span class="font-bold text-amber-600">Em jogo</span> — sem comprador registrado: aparece o formulário de venda rápida.</li>
                <li><span class="font-bold text-emerald-600">Vendida</span> — mostra o comprador e o telefone registrados na cartela.</li>
            </ul>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        window.bingoCheck = { gameId: {{ $game->id }} };
    </script>
    @vite('resources/js/check.js')
@endpush

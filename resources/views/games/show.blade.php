@extends('layouts.app')
@section('title', $game->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">{{ $game->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                @if ($game->organization_name)<strong>{{ $game->organization_name }}</strong> · @endif
                @if ($game->event_date){{ $game->event_date->format('d/m/Y') }} · @endif
                @if ($game->card_price)Cartela R$ {{ number_format((float) $game->card_price, 2, ',', '.') }} · @endif
                @if ($game->card_format === '75')
                    75 bolas · {{ $game->use_letters ? implode('-', $game->letters()) : 'B-I-N-G-O' }} · cartela 5×5
                @elseif ($game->card_format === '90')
                    90 bolas · cartela 3×9
                @else
                    Números {{ $game->number_min }} a {{ $game->number_max }} · cartela 5×5
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('games.edit', $game) }}" class="rounded-xl bg-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-300">Editar evento</a>
            @if ($game->status === 'setup')
                <form method="POST" action="{{ route('games.start', $game) }}">
                    @csrf
                    <button class="rounded-xl bg-emerald-600 px-5 py-2.5 font-bold text-white hover:bg-emerald-500">Iniciar partida</button>
                </form>
            @endif
            @if (in_array($game->status, ['in_progress', 'paused']))
                <a href="{{ route('games.draw', $game) }}" class="rounded-xl bg-amber-500 px-5 py-2.5 font-bold text-slate-900 hover:bg-amber-400">Tela de sorteio</a>
                <a href="{{ route('games.screen', $game) }}" target="_blank" rel="noopener" class="rounded-xl bg-slate-900 px-5 py-2.5 font-bold text-white hover:bg-slate-700">Tela pública</a>
                <form method="POST" action="{{ route('games.finish', $game) }}"
                      onsubmit="return confirm('Encerrar a partida? Não será possível registrar novas pedras.')">
                    @csrf
                    <button class="rounded-xl bg-red-600 px-5 py-2.5 font-bold text-white hover:bg-red-500">Encerrar</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-4">
        <div class="rounded-2xl bg-white p-5 shadow">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pedras sorteadas</p>
            <p class="mt-1 text-3xl font-black">{{ $game->draws_count }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cartelas geradas</p>
            <p class="mt-1 text-3xl font-black">{{ $game->cards_count }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Prêmios</p>
            <p class="mt-1 text-3xl font-black">{{ $prizes->count() }}</p>
        </div>
        <div class="rounded-2xl bg-white p-5 shadow">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Ganhadores validados</p>
            <p class="mt-1 text-3xl font-black">{{ $game->winners_count }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">Prêmios</h2>
                <a href="{{ route('games.prizes.index', $game) }}" class="text-sm font-semibold text-amber-600 hover:text-amber-700">Gerenciar →</a>
            </div>
            @if ($prizes->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Nenhum prêmio cadastrado. Cadastre os prêmios antes de iniciar.</p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @foreach ($prizes as $prize)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <div>
                                <p class="font-semibold">{{ $prize->name }}</p>
                                <p class="text-slate-500">{{ $prize->ruleLabel() }}</p>
                            </div>
                            @if ($prize->winner)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
                                    Ganho — {{ $prize->winner->card?->code }}
                                </span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">Em disputa</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-2xl bg-white p-6 shadow">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">Cartelas</h2>
                <a href="{{ route('games.cards.index', $game) }}" class="text-sm font-semibold text-amber-600 hover:text-amber-700">Gerenciar →</a>
            </div>
            @if ($game->cards_count === 0)
                <p class="mt-3 text-sm text-slate-500">Nenhuma cartela gerada. Gere e imprima as cartelas antes de iniciar.</p>
            @else
                <p class="mt-3 text-sm text-slate-600">{{ $game->cards_count }} cartela(s) gerada(s). Use a página de cartelas para imprimir.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('games.cards.print', $game) }}" target="_blank" rel="noopener" class="inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Imprimir cartelas</a>
                    <a href="{{ route('games.check', $game) }}" target="_blank" rel="noopener" class="inline-block rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-amber-400">Conferir cartelas</a>
                </div>
            @endif
        </div>
    </div>

    @if ($winners->isNotEmpty())
        <div class="mt-6 rounded-2xl bg-white p-6 shadow">
            <h2 class="font-bold">Ganhadores</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($winners as $winner)
                    <li class="flex items-center justify-between py-2">
                        <span>
                            <strong>{{ $winner->prize?->name }}</strong> — cartela {{ $winner->card?->code }}
                            @if ($winner->winner_name) · {{ $winner->winner_name }} @endif
                        </span>
                        <span class="text-slate-500">{{ $winner->validated_at?->format('d/m/Y H:i:s') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6">
        <a href="{{ route('games.report', $game) }}" class="font-semibold text-slate-600 hover:text-slate-900">Ver histórico completo da partida →</a>
    </div>
@endsection

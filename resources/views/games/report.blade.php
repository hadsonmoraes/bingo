@extends('layouts.app')
@section('title', 'Histórico — '.$game->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Histórico da partida</h1>
            <p class="text-sm text-slate-500">{{ $game->name }}</p>
        </div>
        <div class="flex gap-2">
            @if (in_array($game->status, ['in_progress', 'paused']))
                <a href="{{ route('games.draw', $game) }}" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-slate-900 hover:bg-amber-400">Tela de sorteio</a>
            @endif
            <a href="{{ route('games.show', $game) }}" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">Voltar</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow">
            <h2 class="font-bold">Pedras registradas (em ordem)</h2>
            @if ($game->draws->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Nenhuma pedra registrada.</p>
            @else
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($game->draws as $draw)
                        <span class="flex items-center justify-center rounded-xl bg-slate-900 px-3 py-2 text-lg font-black text-white" title="#{{ $draw->sequence }} — {{ $draw->created_at?->format('H:i:s') }}">
                            @if ($draw->letter)<span class="mr-1 text-sm text-amber-300">{{ $draw->letter }}</span>@endif{{ $draw->number }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white p-6 shadow">
            <h2 class="font-bold">Eventos e correções</h2>
            @if ($game->events->isEmpty())
                <p class="mt-3 text-sm text-slate-500">Nenhum evento registrado.</p>
            @else
                <ul class="mt-4 max-h-96 space-y-2 overflow-y-auto text-sm">
                    @foreach ($game->events as $event)
                        <li class="rounded-lg bg-slate-50 px-3 py-2">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-semibold {{ $event->type === 'draw_reverted' ? 'text-red-600' : ($event->type === 'winner_validated' ? 'text-emerald-600' : 'text-slate-800') }}">
                                    {{ $event->typeLabel() }}
                                </span>
                                <span class="shrink-0 text-xs text-slate-500">{{ $event->created_at?->format('d/m/Y H:i:s') }}</span>
                            </div>
                            @if ($event->type === 'draw_registered')
                                <p class="text-slate-600">
                                    @if ($event->data['letter'] ?? null)<span class="font-bold text-amber-600">{{ $event->data['letter'] }}</span> · @endif
                                    número {{ $event->data['number'] }} ({{ $event->data['sequence'] }}ª pedra)
                                </p>
                            @elseif ($event->type === 'draw_reverted')
                                <p class="text-slate-600">
                                    Pedra {{ $event->data['number'] ?? '?' }} desfeita — motivo: “{{ $event->data['reason'] ?? '—' }}”
                                </p>
                            @elseif ($event->type === 'winner_validated')
                                <p class="text-slate-600">
                                    Prêmio {{ $event->data['prize'] ?? '' }} — cartela {{ $event->data['card_code'] ?? '' }}{{ !empty($event->data['winner_name']) ? ' — '.$event->data['winner_name'] : '' }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if ($game->winners->isNotEmpty())
        <div class="mt-6 rounded-2xl bg-white p-6 shadow">
            <h2 class="font-bold">Ganhadores validados</h2>
            <ul class="mt-3 divide-y divide-slate-100 text-sm">
                @foreach ($game->winners as $winner)
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
@endsection

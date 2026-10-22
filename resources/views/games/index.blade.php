@extends('layouts.app')
@section('title', 'Partidas')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Partidas</h1>
        <a href="{{ route('games.create') }}" class="rounded-xl bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700">Nova partida</a>
    </div>

    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <strong>Primeiros passos:</strong> Crie uma partida → configure prêmios → gere cartelas → inicie o sorteio → valide ganhadores. Use a tela de Sorteio para registrar pedras e a tela pública para transmissão.
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Partida</th>
                    <th class="px-4 py-3">Formato</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Pedras</th>
                    <th class="px-4 py-3">Cartelas</th>
                    <th class="px-4 py-3">Ganhadores</th>
                    <th class="px-4 py-3">Início</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($games as $game)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-semibold">
                            <a href="{{ route('games.show', $game) }}" class="text-slate-900 hover:text-amber-600">{{ $game->name }}</a>
                        </td>
                        <td class="px-4 py-3">
                            @if ($game->card_format === '75')
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">75 bolas ({{ $game->use_letters ? implode('-', $game->letters()) : 'B-I-N-G-O' }})</span>
                            @else
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $game->number_min }}–{{ $game->number_max }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($game->status === 'setup')
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">Preparação</span>
                            @elseif ($game->status === 'in_progress')
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Em andamento</span>
                            @elseif ($game->status === 'paused')
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Pausada</span>
                            @else
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-500">Encerrada</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $game->draws_count }}</td>
                        <td class="px-4 py-3">{{ $game->cards_count }}</td>
                        <td class="px-4 py-3">{{ $game->winners_count }}</td>
                        <td class="px-4 py-3">{{ $game->started_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($game->status === 'in_progress' || $game->status === 'paused')
                                <a href="{{ route('games.draw', $game) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500">Sorteio</a>
                            @endif
                            <a href="{{ route('games.show', $game) }}" class="ml-1 rounded-lg bg-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-300">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                            Nenhuma partida cadastrada ainda. <a href="{{ route('games.create') }}" class="font-semibold text-amber-600">Crie a primeira</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@extends('layouts.app')
@section('title', 'Prêmios — '.$game->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Prêmios</h1>
            <p class="text-sm text-slate-500">{{ $game->name }}</p>
        </div>
        <a href="{{ route('games.show', $game) }}" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">Voltar</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_420px]">
        <div class="overflow-hidden rounded-2xl bg-white shadow">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Prêmio</th>
                        <th class="px-4 py-3">Regra de vitória</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($prizes as $prize)
                        <tr>
                            <td class="px-4 py-3 font-mono text-slate-500">{{ $prize->sort_order }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold">{{ $prize->name }}</p>
                                @if ($prize->estimated_value)
                                    <p class="text-xs font-semibold text-emerald-700">Valor: {{ $prize->estimated_value }}</p>
                                @endif
                                @if ($prize->description)
                                    <p class="text-xs text-slate-500">{{ $prize->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $prize->ruleLabel() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($prize->winner)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">Ganho</span>
                                @else
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">Em disputa</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('games.prizes.edit', [$game, $prize]) }}" class="rounded-lg bg-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-300">Editar</a>
                                @unless ($prize->winner)
                                    <form method="POST" action="{{ route('games.prizes.destroy', [$game, $prize]) }}" class="inline" onsubmit="return confirm('Excluir este prêmio?')">
                                        @csrf @method('DELETE')
                                        <button class="ml-1 rounded-lg bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200">Excluir</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Nenhum prêmio cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="border-t border-slate-100 px-4 py-3 text-xs text-slate-500">
                Os prêmios cadastrados aqui aparecem na própria cartela impressa, na seção “PRÊMIOS DO BINGO”.
                Alterações após a emissão não mudam cartelas já impressas — gere um novo lote.
            </p>
        </div>

        <form method="POST" action="{{ route('games.prizes.store', $game) }}" enctype="multipart/form-data" id="prize-form"
              class="h-fit space-y-4 rounded-2xl bg-white p-6 shadow">
            @csrf
            <h2 class="font-bold">Novo prêmio</h2>

            <div>
                <label for="name" class="block text-sm font-semibold text-slate-700">Nome</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                       placeholder="Ex.: Cesta de alimentos">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-semibold text-slate-700">Descrição (opcional)</label>
                <input id="description" type="text" name="description" value="{{ old('description') }}" maxlength="500"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
            </div>

            <div>
                <label for="estimated_value" class="block text-sm font-semibold text-slate-700">Valor estimado (opcional)</label>
                <input id="estimated_value" type="text" name="estimated_value" value="{{ old('estimated_value') }}" maxlength="60"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                       placeholder="Ex.: R$ 500,00">
            </div>

            <div>
                <label for="rule" class="block text-sm font-semibold text-slate-700">Regra de vitória</label>
                <select id="rule" name="rule" data-rule-select class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    @foreach (\App\Models\Prize::RULES as $value => $label)
                        <option value="{{ $value }}" {{ old('rule') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('rule')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div data-pattern-block hidden>
                <p class="text-sm font-semibold text-slate-700">Desenhe o padrão de vitória</p>
                <p class="mb-2 text-xs text-slate-500">Marque as casas que precisam estar marcadas na cartela (casa livre conta automaticamente).</p>
                <div data-pattern-grid class="inline-grid gap-1 rounded-xl bg-slate-100 p-2"></div>
                <input type="hidden" name="pattern" data-pattern-input value="">
                @error('pattern')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="image" class="block text-sm font-semibold text-slate-700">Imagem do prêmio (opcional)</label>
                <input id="image" type="file" name="image" accept="image/*"
                       class="mt-1 w-full rounded-xl border-slate-300 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white">
            </div>

            <button type="submit" class="w-full rounded-xl bg-amber-500 py-3 font-bold text-slate-900 hover:bg-amber-400">Cadastrar prêmio</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        window.bingoCardDimensions = @json([5, 5]);
    </script>
    @vite('resources/js/prizes.js')
@endpush

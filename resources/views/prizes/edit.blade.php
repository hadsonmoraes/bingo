@extends('layouts.app')
@section('title', 'Editar prêmio — '.$game->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Editar prêmio</h1>
            <p class="text-sm text-slate-500">{{ $game->name }}</p>
        </div>
        <a href="{{ route('games.prizes.index', $game) }}" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">Voltar</a>
    </div>

    <form method="POST" action="{{ route('games.prizes.update', [$game, $prize]) }}" enctype="multipart/form-data" id="prize-form"
          class="mt-6 max-w-xl space-y-4 rounded-2xl bg-white p-6 shadow">
        @csrf @method('PUT')

        <div>
            <label for="name" class="block text-sm font-semibold text-slate-700">Nome</label>
            <input id="name" type="text" name="name" value="{{ old('name', $prize->name) }}" required
                   class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-semibold text-slate-700">Descrição (opcional)</label>
            <input id="description" type="text" name="description" value="{{ old('description', $prize->description) }}" maxlength="500"
                   class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
        </div>

        <div>
            <label for="estimated_value" class="block text-sm font-semibold text-slate-700">Valor estimado (opcional)</label>
            <input id="estimated_value" type="text" name="estimated_value" value="{{ old('estimated_value', $prize->estimated_value) }}" maxlength="60"
                   class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
        </div>

        <div>
            <label for="rule" class="block text-sm font-semibold text-slate-700">Regra de vitória</label>
            <select id="rule" name="rule" data-rule-select class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                @foreach (\App\Models\Prize::RULES as $value => $label)
                    <option value="{{ $value }}" {{ old('rule', $prize->rule) === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('rule')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div data-pattern-block hidden>
            <p class="text-sm font-semibold text-slate-700">Desenhe o padrão de vitória</p>
            <p class="mb-2 text-xs text-slate-500">Marque as casas que precisam estar marcadas na cartela (casa livre conta automaticamente).</p>
            <div data-pattern-grid class="inline-grid gap-1 rounded-xl bg-slate-100 p-2"></div>
            <input type="hidden" name="pattern" data-pattern-input
                   value=""
                   data-existing-pattern='@json(old('pattern', $prize->pattern))'>
            @error('pattern')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="image" class="block text-sm font-semibold text-slate-700">Imagem do prêmio (opcional)</label>
            @if ($prize->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($prize->image_path))
                <div class="mt-2 flex items-center gap-3">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($prize->image_path) }}" alt="Imagem atual" class="h-14 rounded-lg bg-slate-100 object-contain px-2">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remove_image" value="1"> Remover imagem atual
                    </label>
                </div>
            @endif
            <input id="image" type="file" name="image" accept="image/*"
                   class="mt-1 w-full rounded-xl border-slate-300 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white">
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('games.prizes.index', $game) }}" class="rounded-xl px-4 py-2 font-semibold text-slate-600 hover:text-slate-900">Cancelar</a>
            <button type="submit" class="rounded-xl bg-amber-500 px-6 py-2 font-bold text-slate-900 hover:bg-amber-400">Salvar</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        window.bingoCardDimensions = @json($game->card_format === '90' ? [3, 9] : [5, 5]);
    </script>
    @vite('resources/js/prizes.js')
@endpush

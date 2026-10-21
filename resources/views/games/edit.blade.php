@extends('layouts.app')
@section('title', 'Editar evento — '.$game->name)

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold">Dados do evento</h1>
        <a href="{{ route('games.show', $game) }}" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">Voltar</a>
    </div>

    <form method="POST" action="{{ route('games.update', $game) }}" enctype="multipart/form-data"
          class="mt-6 max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow">
        @csrf @method('PUT')

        <div>
            <label for="name" class="block text-sm font-semibold text-slate-700">Nome do evento *</label>
            <input id="name" type="text" name="name" value="{{ old('name', $game->name) }}" required
                   class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="organization_name" class="block text-sm font-semibold text-slate-700">Instituição responsável</label>
            <input id="organization_name" type="text" name="organization_name" value="{{ old('organization_name', $game->organization_name) }}" maxlength="150"
                   class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="event_date" class="block text-sm font-semibold text-slate-700">Data do evento</label>
                <input id="event_date" type="date" name="event_date" value="{{ old('event_date', $game->event_date?->format('Y-m-d')) }}"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                @error('event_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="card_price" class="block text-sm font-semibold text-slate-700">Valor da cartela (R$)</label>
                <input id="card_price" type="number" step="0.01" min="0" name="card_price" value="{{ old('card_price', $game->card_price) }}"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                @error('card_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="theme_color" class="block text-sm font-semibold text-slate-700">Cor do tema</label>
                <select id="theme_color" name="theme_color" class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    @foreach (\App\Models\Game::THEME_COLORS as $value => $label)
                        <option value="{{ $value }}" {{ old('theme_color', $game->theme_color) === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="letters_word" class="block text-sm font-semibold text-slate-700">Palavra do painel e das cartelas</label>
                <input id="letters_word" type="text" name="letters_word" value="{{ old('letters_word', $game->letters_word) }}" maxlength="10"
                       placeholder="BINGO" class="mt-1 w-full rounded-xl border-slate-300 uppercase focus:border-amber-500 focus:ring-amber-500">
                <p class="mt-1 text-xs text-slate-500">Tema com exatamente 5 letras (ex.: FESTA) — substitui o "BINGO" no painel de números e nas cartelas. Vazio mantém BINGO.</p>
                @error('letters_word')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="logo" class="block text-sm font-semibold text-slate-700">Logotipo da instituição</label>
            @if ($game->logoUrl())
                <div class="mt-2 flex items-center gap-3">
                    <img src="{{ $game->logoUrl() }}" alt="Logotipo atual" class="h-14 rounded-lg bg-slate-100 object-contain px-2">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="remove_logo" value="1"> Remover logotipo atual
                    </label>
                </div>
            @endif
            <input id="logo" type="file" name="logo" accept="image/*"
                   class="mt-1 w-full rounded-xl border-slate-300 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white">
            @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('games.show', $game) }}" class="rounded-xl px-4 py-2 font-semibold text-slate-600 hover:text-slate-900">Cancelar</a>
            <button type="submit" class="rounded-xl bg-amber-500 px-6 py-2 font-bold text-slate-900 hover:bg-amber-400">Salvar</button>
        </div>
    </form>
@endsection

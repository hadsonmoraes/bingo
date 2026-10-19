@extends('layouts.app')
@section('title', 'Nova partida')

@section('content')
    <h1 class="text-2xl font-bold">Nova partida</h1>

    <form method="POST" action="{{ route('games.store') }}" enctype="multipart/form-data"
          class="mt-6 max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow">
        @csrf

        <div class="border-b border-slate-200 pb-4">
            <p class="text-sm font-bold uppercase tracking-wide text-slate-400">Identidade do evento</p>

            <div class="mt-3">
                <label for="name" class="block text-sm font-semibold text-slate-700">Nome do evento *</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                       placeholder="Ex.: Bingo Beneficente da Igreja São José">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4">
                <label for="organization_name" class="block text-sm font-semibold text-slate-700">Instituição responsável</label>
                <input id="organization_name" type="text" name="organization_name" value="{{ old('organization_name') }}" maxlength="150"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                       placeholder="Ex.: Paróquia São José">
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="event_date" class="block text-sm font-semibold text-slate-700">Data do evento</label>
                    <input id="event_date" type="date" name="event_date" value="{{ old('event_date') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    @error('event_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="card_price" class="block text-sm font-semibold text-slate-700">Valor da cartela (R$)</label>
                    <input id="card_price" type="number" step="0.01" min="0" name="card_price" value="{{ old('card_price') }}"
                           class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    @error('card_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="theme_color" class="block text-sm font-semibold text-slate-700">Cor do tema</label>
                    <select id="theme_color" name="theme_color"
                            class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                        @foreach (\App\Models\Game::THEME_COLORS as $value => $label)
                            <option value="{{ $value }}" {{ old('theme_color', 'amber') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <label for="logo" class="block text-sm font-semibold text-slate-700">Logotipo da instituição (opcional)</label>
                <input id="logo" type="file" name="logo" accept="image/*"
                       class="mt-1 w-full rounded-xl border-slate-300 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:text-white">
                <p class="mt-1 text-xs text-slate-500">PNG ou JPG até 2MB. Sem logotipo, a cartela usa um cabeçalho tipográfico elegante.</p>
                @error('logo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="border-b border-slate-200 pb-4">
            <p class="text-sm font-bold uppercase tracking-wide text-slate-400">Formato do bingo</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3" data-format-cards>
                <label class="cursor-pointer rounded-xl border-2 border-slate-200 p-4 transition hover:border-amber-400 has-checked:border-amber-500 has-checked:bg-amber-50">
                    <input type="radio" name="card_format" value="75" class="sr-only" {{ old('card_format', '75') === '75' ? 'checked' : '' }} data-format="75">
                    <span class="block font-bold">75 bolas</span>
                    <span class="text-sm text-slate-500">Colunas B-I-N-G-O, cartela 5×5 com casa livre.</span>
                </label>
                <label class="cursor-pointer rounded-xl border-2 border-slate-200 p-4 transition hover:border-amber-400 has-checked:border-amber-500 has-checked:bg-amber-50">
                    <input type="radio" name="card_format" value="90" class="sr-only" {{ old('card_format') === '90' ? 'checked' : '' }} data-format="90">
                    <span class="block font-bold">90 bolas</span>
                    <span class="text-sm text-slate-500">Cartela 3×9 com 15 números.</span>
                </label>
                <label class="cursor-pointer rounded-xl border-2 border-slate-200 p-4 transition hover:border-amber-400 has-checked:border-amber-500 has-checked:bg-amber-50">
                    <input type="radio" name="card_format" value="custom" class="sr-only" {{ old('card_format') === 'custom' ? 'checked' : '' }} data-format="custom">
                    <span class="block font-bold">Personalizado</span>
                    <span class="text-sm text-slate-500">Defina o intervalo de números. Cartela 5×5.</span>
                </label>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2" data-custom-range hidden>
            <div>
                <label for="number_min" class="block text-sm font-semibold text-slate-700">Número mínimo</label>
                <input id="number_min" type="number" name="number_min" value="{{ old('number_min', 1) }}" min="1" max="200"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                @error('number_min')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="number_max" class="block text-sm font-semibold text-slate-700">Número máximo</label>
                <input id="number_max" type="number" name="number_max" value="{{ old('number_max', 50) }}" min="2" max="200"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                @error('number_max')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('games.index') }}" class="rounded-xl px-4 py-2 font-semibold text-slate-600 hover:text-slate-900">Cancelar</a>
            <button type="submit" class="rounded-xl bg-amber-500 px-6 py-2 font-bold text-slate-900 hover:bg-amber-400">Criar partida</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const cards = document.querySelectorAll('[data-format-cards] input[name="card_format"]');
        const customRange = document.querySelector('[data-custom-range]');
        const sync = () => customRange.hidden = !document.querySelector('[data-format-cards] input[value="custom"]').checked;
        cards.forEach((card) => card.addEventListener('change', sync));
        sync();
    </script>
@endpush

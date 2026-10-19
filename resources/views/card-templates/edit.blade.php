@extends('layouts.app')
@section('title', 'Mapear áreas — '.$template->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Mapear áreas da arte</h1>
            <p class="text-sm text-slate-500">{{ $template->name }} · {{ $game->name }}</p>
        </div>
        <a href="{{ route('games.templates.index', $game) }}" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">Voltar aos modelos</a>
    </div>

    <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Posicione cada área em <strong>milímetros</strong> dentro da folha A4 (210 × 297 mm).
        Os dados dinâmicos (números, código, prêmios, comprador) são inseridos pelo sistema
        nessas posições — a arte continua intacta e os números do jogo nunca mudam.
    </div>

    <form method="POST" action="{{ route('games.templates.update', [$game, $template]) }}" class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
        @csrf @method('PUT')

        <div class="h-fit space-y-4 rounded-2xl bg-white p-6 shadow">
            <div>
                <label for="name" class="block text-sm font-semibold text-slate-700">Nome do modelo</label>
                <input id="name" type="text" name="name" required value="{{ $template->name }}"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
            </div>
            <div>
                <label for="is_default" class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input id="is_default" type="checkbox" name="is_default" value="1"
                           {{ $template->is_default ? 'checked' : '' }}
                           class="rounded border-slate-300 text-amber-500 focus:ring-amber-500">
                    Modelo padrão deste evento
                </label>
            </div>

            @foreach ([
                'header' => 'Cabeçalho (instituição + evento)',
                'code' => 'Identificador da cartela',
                'prizes' => 'Lista de prêmios',
                'buyer' => 'Comprador (nome e telefone)',
                'stub' => 'Canhoto destacável',
            ] as $key => $label)
                <div class="rounded-xl border border-slate-200 p-3">
                    <p class="text-sm font-bold text-slate-700">{{ $label }}</p>
                    <div class="mt-2 grid grid-cols-4 gap-2">
                        @foreach (['x' => 'X', 'y' => 'Y', 'w' => 'Largura', 'h' => 'Altura'] as $field => $fieldLabel)
                            <label class="text-xs font-semibold text-slate-500">
                                {{ $fieldLabel }} (mm)
                                <input type="number" step="0.5" min="0"
                                       @if ($field === 'w') max="210" @else max="297" @endif
                                       name="areas[{{ $key }}][{{ $field }}]"
                                       value="{{ $template->area($key)[$field] ?? 0 }}"
                                       data-area="{{ $key }}-{{ $field }}"
                                       class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="rounded-xl border border-slate-300 p-3">
                <p class="text-sm font-bold text-slate-700">Blocos numéricos (um por prêmio)</p>
                <div id="block-fields" class="mt-2 space-y-2">
                    @for ($i = 0; $i < $blocksNeeded; $i++)
                        <div class="rounded-lg bg-slate-50 p-2">
                            <p class="text-xs font-bold text-slate-600">Bloco {{ $i + 1 }}</p>
                            <div class="mt-1 grid grid-cols-4 gap-2">
                                @foreach (['x' => 'X', 'y' => 'Y', 'w' => 'Largura', 'h' => 'Altura'] as $field => $fieldLabel)
                                    <label class="text-xs font-semibold text-slate-500">
                                        {{ $fieldLabel }}
                                        <input type="number" step="0.5" min="0"
                                               @if ($field === 'w') max="210" @else max="297" @endif
                                               name="areas[blocks][{{ $i }}][{{ $field }}]"
                                               value="{{ $template->area('blocks', $i)[$field] ?? 0 }}"
                                               data-area="block-{{ $i }}-{{ $field }}"
                                               class="mt-0.5 w-full rounded-lg border-slate-300 text-sm">
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <button type="submit" class="w-full rounded-xl bg-amber-500 py-3 font-bold text-slate-900 hover:bg-amber-400">
                Salvar mapeamento
            </button>
        </div>

        {{-- Pré-visualização A4 com a arte e as áreas sobrepostas --}}
        <div class="overflow-auto rounded-2xl bg-slate-100 p-4">
            <div class="mx-auto" style="position: relative; width: 210mm; height: 297mm; background: #fff; box-shadow: 0 4px 16px rgb(15 23 42 / 0.25);">
                <img src="{{ $template->artUrl() }}" alt="Arte importada"
                     style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: fill;">
                <div id="overlay"></div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            (function () {
                const overlay = document.getElementById('overlay');
                const labels = {
                    header: 'CABEÇALHO', code: 'CÓDIGO', prizes: 'PRÊMIOS',
                    buyer: 'COMPRADOR', stub: 'CANHOTO',
                };
                const colors = {
                    header: '#0369a1', code: '#7c3aed', prizes: '#b45309',
                    buyer: '#047857', stub: '#be123c', block: '#0f172a',
                };

                function field(key, f) {
                    return document.querySelector('[data-area="' + key + '-' + f + '"]');
                }

                function rect(a) {
                    return {
                        x: parseFloat(field(a, 'x')?.value) || 0,
                        y: parseFloat(field(a, 'y')?.value) || 0,
                        w: parseFloat(field(a, 'w')?.value) || 0,
                        h: parseFloat(field(a, 'h')?.value) || 0,
                    };
                }

                function render() {
                    const parts = [];
                    const draw = (key, label, color) => {
                        const r = rect(key);
                        if (!r.w || !r.h) return;
                        parts.push(
                            '<div style="position:absolute;left:' + r.x + 'mm;top:' + r.y + 'mm;width:' + r.w + 'mm;height:' + r.h + 'mm;' +
                            'border:1px dashed ' + color + ';background:' + color + '18;box-sizing:border-box;display:flex;align-items:center;justify-content:center;">' +
                            '<span style="font-size:9px;font-weight:800;color:' + color + ';background:#fff;padding:1px 4px;border-radius:4px">' + label + '</span></div>'
                        );
                    };
                    Object.keys(labels).forEach((k) => draw(k, labels[k], colors[k]));

                    const blockCount = document.querySelectorAll('[data-area^="block-"]').length / 4;
                    for (let i = 0; i < blockCount; i++) {
                        draw('block-' + i, 'BLOCO ' + (i + 1), colors.block);
                    }
                    overlay.innerHTML = parts.join('');
                }

                document.querySelectorAll('[data-area]').forEach((input) => input.addEventListener('input', render));
                render();
            })();
        </script>
    @endpush
@endsection

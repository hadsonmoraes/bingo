@extends('layouts.app')
@section('title', 'Modelos de arte — '.$game->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Modelos de arte personalizada</h1>
            <p class="text-sm text-slate-500">{{ $game->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('games.cards.art-model', $game) }}"
               class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">⬇ Baixar modelo-base para editar</a>
            <a href="{{ route('games.cards.index', $game) }}" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">Voltar às cartelas</a>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
        <strong>Fluxo:</strong> baixe o modelo-base (SVG vetorial A4), personalize no programa gráfico
        (Inkscape, Illustrator, CorelDRAW, Canva…), importe o arquivo abaixo e mapeie as áreas onde o
        sistema insere os dados dinâmicos. <strong>A arte nunca altera os números, o identificador da
        cartela ou a relação entre blocos e prêmios.</strong>
        Formatos aceitos: SVG, PNG e PDF (para gerar o PDF final a partir da arte, use PNG — SVG/PDF
        servem como base de edição e pré-visualização).
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[420px_1fr]">
        <form method="POST" action="{{ route('games.templates.store', $game) }}"
              enctype="multipart/form-data" class="h-fit rounded-2xl bg-white p-6 shadow">
            @csrf
            <h2 class="font-bold">Importar arte personalizada</h2>
            <label for="name" class="mt-4 block text-sm font-semibold text-slate-700">Nome do modelo</label>
            <input id="name" type="text" name="name" required value="{{ old('name') }}"
                   class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                   placeholder="Ex.: Arte festa junina 2026">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

            <label for="art" class="mt-4 block text-sm font-semibold text-slate-700">Arquivo da arte (SVG, PNG ou PDF · máx. 4 MB)</label>
            <input id="art" type="file" name="art" required accept=".svg,.png,.jpg,.jpeg,.pdf"
                   class="mt-1 w-full rounded-xl border-slate-300 text-sm">
            @error('art')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

            <button type="submit" class="mt-4 w-full rounded-xl bg-amber-500 py-3 font-bold text-slate-900 hover:bg-amber-400">
                Importar arte
            </button>
            <p class="mt-2 text-xs text-slate-500">
                SVG com scripts ou arquivos corrompidos são rejeitados na validação.
            </p>
        </form>

        <div class="space-y-4">
            @forelse ($templates as $template)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-5 shadow">
                    <div class="min-w-0">
                        <p class="font-bold">
                            {{ $template->name }}
                            @if ($template->is_default)
                                <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">Padrão</span>
                            @endif
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ strtoupper(pathinfo($template->art_path, PATHINFO_EXTENSION)) }} ·
                            importado em {{ $template->created_at->format('d/m/Y H:i') }} ·
                            {{ count($template->areas['blocks'] ?? []) }} área(s) de bloco mapeada(s)
                            @if (! $template->supportsPdfEmbed())
                                · <span class="text-amber-700">não embutível no PDF final (use PNG para isso)</span>
                            @endif
                        </p>
                        @php $missing = $template->missingAreas($prizesCount); @endphp
                        @if ($missing)
                            <p class="mt-1 text-xs font-semibold text-red-600">⚠ Áreas faltando: {{ implode(', ', $missing) }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $template->artUrl() }}" target="_blank" rel="noopener"
                           class="rounded-lg bg-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-300">Ver arte</a>
                        <a href="{{ route('games.templates.edit', [$game, $template]) }}"
                           class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-700">Mapear áreas</a>
                        @if (! $template->is_default)
                            <form method="POST" action="{{ route('games.templates.default', [$game, $template]) }}">
                                @csrf
                                <button class="rounded-lg bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">Definir padrão</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('games.templates.duplicate', [$game, $template]) }}">
                            @csrf
                            <button class="rounded-lg bg-sky-100 px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-200">Duplicar</button>
                        </form>
                        <form method="POST" action="{{ route('games.templates.destroy', [$game, $template]) }}"
                              onsubmit="return confirm('Excluir o modelo “{{ $template->name }}”?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-200">Excluir</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-white p-8 text-center text-sm text-slate-500 shadow">
                    Nenhum modelo importado ainda. Baixe o modelo-base, personalize e importe acima.
                </div>
            @endforelse
        </div>
    </div>
@endsection

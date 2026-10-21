@extends('layouts.app')
@section('title', 'Cartelas — '.$game->name)

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Cartelas</h1>
            <p class="text-sm text-slate-500">{{ $game->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('games.cards.print', $game) }}" target="_blank" rel="noopener"
               class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Pré-visualizar / imprimir ↗</a>
            <a href="{{ route('games.cards.pdf', $game) }}?per_page=2&stub=1"
               class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600">Gerar PDF A4</a>
            <a href="{{ route('games.templates.index', $game) }}"
               class="rounded-xl bg-violet-700 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-600">🎨 Arte personalizada</a>
            <a href="{{ route('games.cards.art-model', $game) }}"
               class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-600">⬇ Baixar arte para editar (modelo-base)</a>
            <a href="{{ route('games.show', $game) }}" class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-300">Voltar</a>
        </div>
    </div>

    @if ($prizes_changed)
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
            <strong>Atenção:</strong> os prêmios foram alterados depois da última emissão de cartelas.
            As cartelas já impressas mantêm os prêmios originais (congelados na emissão).
            Gere um novo lote e reimprima para divulgar os prêmios atualizados.
        </div>
    @endif

    {{-- Exportações de PDF: lotes grandes gerados pela fila --}}
    <div id="pdf-exports-panel" class="mt-4 hidden rounded-2xl bg-white p-5 shadow"
         data-poll-url="{{ route('games.pdf-exports.index', $game) }}">
        <h2 class="text-sm font-bold">Exportações de PDF</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            Lotes grandes são gerados em segundo plano (fila) — o download (ZIP com os PDFs) aparece assim que o arquivo fica pronto.
        </p>
        <ul id="pdf-exports-list" class="mt-2 divide-y divide-slate-100"></ul>
    </div>

    {{-- Geração de lotes grandes de cartelas pela fila --}}
    @session('generating_batch')
        <div id="cards-generation-panel" class="mt-4 rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow"
             data-poll-url="{{ route('games.cards.generation-status', $game) }}"
             data-batch="{{ session('generating_batch') }}"
             data-quantity="{{ session('generating_quantity') }}">
            <h2 class="text-sm font-bold text-amber-900">Gerando lote {{ session('generating_batch') }} em segundo plano</h2>
            <p class="mt-0.5 text-xs text-amber-800">
                {{ session('generating_quantity') }} cartelas estão sendo geradas pela fila, sem travar o servidor.
                A lista é recarregada automaticamente quando o lote fica pronto.
            </p>
            <p id="cards-generation-progress" class="mt-2 text-sm font-semibold text-amber-700">Preparando…</p>
        </div>
    @endsession

    <div class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
        <div class="h-fit space-y-6">
            <form method="POST" action="{{ route('games.cards.generate', $game) }}" class="rounded-2xl bg-white p-6 shadow">
                @csrf
                <h2 class="font-bold">Gerar novo lote</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Códigos únicos automáticos, combinações válidas e sem cartelas integralmente duplicadas.
                </p>
                <label for="quantity" class="mt-4 block text-sm font-semibold text-slate-700">Quantidade</label>
                <input id="quantity" type="number" name="quantity" value="10" min="1" max="500"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                @error('quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="mt-4 w-full rounded-xl bg-amber-500 py-3 font-bold text-slate-900 hover:bg-amber-400">Gerar cartelas</button>
            </form>

            <form method="GET" class="rounded-2xl bg-white p-6 shadow">
                <h2 class="font-bold">Pesquisar cartelas</h2>
                <label for="q" class="mt-3 block text-sm font-semibold text-slate-700">Código, comprador ou telefone</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}"
                       class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500"
                       placeholder="Ex.: C-0007, Maria ou 99999...">
                <label for="status" class="mt-3 block text-sm font-semibold text-slate-700">Situação</label>
                <select id="status" name="status" class="mt-1 w-full rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500">
                    <option value="">Todas</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="mt-4 w-full rounded-xl bg-slate-900 py-2.5 font-semibold text-white hover:bg-slate-700">Filtrar</button>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-3">Código</th>
                        <th class="px-3 py-3">Comprador</th>
                        <th class="px-3 py-3">Situação</th>
                        <th class="px-3 py-3">Lote</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($cards as $card)
                        <tr class="align-middle">
                            <td class="px-3 py-3 font-mono font-bold whitespace-nowrap">{{ $card->code }}</td>
                            <td class="px-3 py-3">
                                <form method="POST" action="{{ route('games.cards.buyer', [$game, $card]) }}" class="flex flex-wrap items-center gap-1">
                                    @csrf
                                    <input type="text" name="buyer_name" value="{{ $card->buyer_name }}" placeholder="Nome"
                                           class="w-32 rounded-lg border-slate-300 px-2 py-1 text-sm focus:border-amber-500 focus:ring-amber-500">
                                    <input type="text" name="buyer_phone" value="{{ $card->buyer_phone }}" placeholder="Telefone"
                                           class="w-28 rounded-lg border-slate-300 px-2 py-1 text-sm focus:border-amber-500 focus:ring-amber-500">
                                    <button class="rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-700">Salvar</button>
                                </form>
                            </td>
                            <td class="px-3 py-3">
                                @if ($card->status === 'available')
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Disponível</span>
                                @elseif ($card->status === 'sold')
                                    <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700">Vendida</span>
                                @elseif ($card->status === 'in_play')
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Em jogo</span>
                                @elseif ($card->status === 'winner')
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">Vencedora</span>
                                @else
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Cancelada</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-slate-500">{{ $card->batch }}</td>
                            <td class="px-3 py-3 whitespace-nowrap text-right">
                                <a href="{{ route('games.cards.print', $game) }}?card={{ $card->code }}" target="_blank" rel="noopener"
                                   class="rounded-lg bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-300">Reimprimir</a>
                                @if ($card->status === 'cancelled')
                                    <form method="POST" action="{{ route('games.cards.cancel', [$game, $card]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="restore" value="1">
                                        <button class="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">Reativar</button>
                                    </form>
                                @elseif ($card->status !== 'winner')
                                    <form method="POST" action="{{ route('games.cards.cancel', [$game, $card]) }}" class="inline"
                                          onsubmit="return confirm('Cancelar a cartela {{ $card->code }}? Ela sairá da contagem da rodada.')">
                                        @csrf
                                        <button class="rounded-lg bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-200">Cancelar</button>
                                    </form>
                                @endif
                                <a href="{{ route('games.cards.art', $game) }}?card={{ $card->code }}"
                                   class="rounded-lg bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-200">Arte SVG</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Nenhuma cartela encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="border-t border-slate-100 px-4 py-3">
                {{ $cards->links() }}
            </div>
        </div>
    </div>
@endsection

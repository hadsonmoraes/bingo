<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateCardsRequest;
use App\Http\Requests\StoreBuyerRequest;
use App\Jobs\GenerateCardsJob;
use App\Jobs\GenerateCardsPdfJob;
use App\Models\Card;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\PdfExport;
use App\Services\ArtExportService;
use App\Services\CardGenerator;
use App\Services\CardPrintService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CardController extends Controller
{
    public function index(Request $request, Game $game)
    {
        $query = $game->cards();

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($cards) use ($search) {
                $cards->where('code', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")
                    ->orWhere('buyer_phone', 'like', "%{$search}%");
            });
        }

        if ($request->query('status') && array_key_exists($request->query('status'), Card::STATUS_LABELS)) {
            $query->where('status', $request->query('status'));
        }

        $cards = $query->orderBy('id')->paginate(24)->withQueryString();

        $prizesUpdatedAt = $game->prizes()->max('updated_at');
        $lastPrintedAt = $game->cards()->whereNotNull('printed_at')->max('printed_at');

        $prizesChanged = $prizesUpdatedAt && $lastPrintedAt
            && Carbon::parse($prizesUpdatedAt)
                ->gt(Carbon::parse($lastPrintedAt));

        return view('cards.index', [
            'game' => $game,
            'cards' => $cards,
            'statuses' => Card::STATUS_LABELS,
            'prizes_changed' => $prizesChanged,
        ]);
    }

    /**
     * Gera um novo lote de cartelas com códigos únicos e grades
     * válidas, sem duplicar integralmente cartelas já existentes.
     * Lotes grandes (acima do limite síncrono) vão para a fila: a
     * verificação de duplicidade tem custo quadrático e travaria a
     * requisição HTTP com centenas de cartelas.
     */
    public function generate(GenerateCardsRequest $request, Game $game, CardGenerator $generator)
    {
        $validated = $request->validated();

        $quantity = (int) $validated['quantity'];
        $batch = ((int) $game->cards()->max('batch')) + 1;

        if ($quantity > CardGenerator::INLINE_MAX_GENERATE) {
            GenerateCardsJob::dispatch($game, $quantity, $batch);

            return back()
                ->with('success', "Lote {$batch} com {$quantity} cartelas está sendo gerado em segundo plano (fila). Acompanhe o andamento abaixo — as cartelas aparecem na lista assim que ficam prontas.")
                ->with('generating_batch', $batch)
                ->with('generating_quantity', $quantity);
        }

        $generator->generateBatch($game, $quantity, $batch);

        return back()->with('success', $quantity.' cartela(s) gerada(s) no lote '.$batch.'.');
    }

    /**
     * Progresso de um lote grande gerado pela fila — usado pelo polling
     * da página de cartelas.
     */
    public function generationStatus(Request $request, Game $game)
    {
        $batch = (int) $request->query('batch', 0);

        return response()->json([
            'generated' => $game->cards()->where('batch', $batch)->count(),
        ]);
    }

    /**
     * Cadastro digital do comprador (opcional) — também pode ser
     * preenchido à mão na própria cartela impressa.
     */
    public function storeBuyer(StoreBuyerRequest $request, Game $game, Card $card)
    {
        $validated = $request->validated();

        if ($card->status === Card::STATUS_CANCELLED || $card->status === Card::STATUS_WINNER) {
            return back()->with('error', 'Não é possível alterar o comprador desta cartela.');
        }

        $status = $card->status;
        if ($status === Card::STATUS_AVAILABLE && ($validated['buyer_name'] || $validated['buyer_phone'])) {
            $status = Card::STATUS_SOLD;
        }

        $card->update([
            'buyer_name' => $validated['buyer_name'] ?? null,
            'buyer_phone' => $validated['buyer_phone'] ?? null,
            'status' => $status,
        ]);

        $game->events()->create([
            'type' => GameEvent::TYPE_BUYER_UPDATED,
            'data' => ['card_code' => $card->code, 'buyer_name' => $card->buyer_name, 'buyer_phone' => $card->buyer_phone],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Comprador da cartela '.$card->code.' atualizado.',
                'type' => 'success',
                'card' => [
                    'code' => $card->code,
                    'status' => $card->statusLabel(),
                    'sold' => ($status === Card::STATUS_SOLD),
                    'buyer_name' => $card->buyer_name,
                    'buyer_phone' => $card->buyer_phone,
                ],
            ]);
        }

        return back()->with('success', 'Comprador da cartela '.$card->code.' atualizado.');
    }

    public function cancel(Request $request, Game $game, Card $card)
    {
        if ($card->status === Card::STATUS_WINNER) {
            return back()->with('error', 'Não é possível cancelar uma cartela vencedora.');
        }

        if ($request->boolean('restore')) {
            $card->update(['status' => $game->status === Game::STATUS_SETUP ? Card::STATUS_AVAILABLE : Card::STATUS_IN_PLAY]);

            return back()->with('success', 'Cartela '.$card->code.' reativada.');
        }

        $card->update(['status' => Card::STATUS_CANCELLED]);

        $game->events()->create([
            'type' => GameEvent::TYPE_CARD_CANCELLED,
            'data' => ['card_code' => $card->code],
        ]);

        return back()->with('success', 'Cartela '.$card->code.' cancelada. Ela sai da contagem da rodada.');
    }

    /**
     * Pré-visualização da impressão. Na primeira emissão captura um
     * snapshot dos prêmios: cartelas já emitidas nunca mudam em silêncio.
     */
    public function print(Request $request, Game $game)
    {
        $prints = app(CardPrintService::class);
        $cards = $prints->cardsForFilters($game, $request->query());

        if ($cards->isEmpty()) {
            return redirect()->route('games.cards.index', $game)->with('error', 'Gere cartelas antes de imprimir.');
        }

        $prints->issue($game, $cards);

        return view('cards.print', $prints->data($game, $request->query(), $cards));
    }

    /**
     * PDF A4: lotes grandes (acima do limite síncrono) são gerados pela
     * fila — centenas de páginas dompdf não cabem num request HTTP.
     */
    public function pdf(Request $request, Game $game, PdfService $pdf)
    {
        $prints = app(CardPrintService::class);
        $cards = $prints->cardsForFilters($game, $request->query());

        if ($cards->isEmpty()) {
            return redirect()->route('games.cards.index', $game)->with('error', 'Gere cartelas antes de gerar o PDF.');
        }

        if ($cards->count() > CardPrintService::INLINE_MAX_CARDS) {
            $export = $game->pdfExports()->create([
                'status' => PdfExport::STATUS_PENDING,
                'cards_count' => $cards->count(),
                'filters' => $request->query(),
                'base_url' => $request->root(),
            ]);

            GenerateCardsPdfJob::dispatch($export);

            return redirect()
                ->route('games.cards.index', $game)
                ->with('success', "PDF de {$cards->count()} cartelas está sendo gerado em segundo plano. Acompanhe em “Exportações de PDF” — o download (ZIP com os PDFs) aparece quando o arquivo estiver pronto.");
        }

        $prints->issue($game, $cards);

        return $pdf->render('cards.pdf', $prints->data($game, $request->query(), $cards), "cartelas-{$game->id}.pdf");
    }

    /**
     * "Baixar arte para editar": SVG vetorial em A4 (mm) da cartela
     * informada ou do modelo-base com áreas reservadas nomeadas.
     */
    public function artSvg(Request $request, Game $game, ArtExportService $art)
    {
        $card = null;
        if ($code = trim((string) $request->query('card'))) {
            $card = $game->cards()->where('code', $code)->first();
        }

        return $art->svg($game, $card);
    }

    public function artModelBase(Game $game, ArtExportService $art)
    {
        return $art->svgModelBase($game);
    }

    /**
     * Consulta o status de uma cartela a partir do seu código.
     * Aceita o código puro (C-0001), o conteúdo do código de barras
     * impresso no canhoto ou a URL completa do QR Code.
     */
    public function check(Request $request, Game $game)
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:255']]);
        $card = $game->cards()->where('code', Card::normalizeCodeInput($validated['code']))->first();

        if (! $card) {
            return response()->json(['message' => 'Cartela não encontrada nesta partida.', 'type' => 'error'], 404);
        }

        $sold = (bool) ($card->buyer_name || $card->buyer_phone);

        return response()->json([
            'card' => [
                'id' => $card->id,
                'code' => $card->code,
                'status' => $card->statusLabel(),
                'sold' => $sold,
                'buyer_name' => $card->buyer_name,
                'buyer_phone' => $card->buyer_phone,
                'status_color' => $sold ? 'emerald' : ($card->status === Card::STATUS_IN_PLAY ? 'amber' : 'red'),
            ],
        ]);
    }

    /**
     * Página de conferência de cartelas: recebe a leitura do código de
     * barras do canhoto (ou o código digitado) e registra vendas rápidas
     * no caixa — separada da tela do sorteio para não interromper o
     * registro das pedras.
     */
    public function checkPage(Game $game)
    {
        return view('games.check', ['game' => $game]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Game;
use App\Models\GameEvent;
use App\Services\ArtExportService;
use App\Services\CardGenerator;
use App\Services\CardLayoutService;
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
     */
    public function generate(Request $request, Game $game)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'quantity.required' => 'Informe a quantidade de cartelas.',
            'quantity.max' => 'Gere no máximo 500 cartelas por vez.',
        ]);

        $generator = app(CardGenerator::class);
        $batch = ((int) $game->cards()->max('batch')) + 1;

        for ($i = 0; $i < $validated['quantity']; $i++) {
            $generator->createCard($game, batch: $batch);
        }

        return back()->with('success', $validated['quantity'].' cartela(s) gerada(s) no lote '.$batch.'.');
    }

    /**
     * Cadastro digital do comprador (opcional) — também pode ser
     * preenchido à mão na própria cartela impressa.
     */
    public function storeBuyer(Request $request, Game $game, Card $card)
    {
        $validated = $request->validate([
            'buyer_name' => ['nullable', 'string', 'max:150'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
        ]);

        if ($card->game_id !== $game->id) {
            return back()->with('error', 'Cartela não pertence a esta partida.');
        }

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

        return back()->with('success', 'Comprador da cartela '.$card->code.' atualizado.');
    }

    public function cancel(Request $request, Game $game, Card $card)
    {
        if ($card->game_id !== $game->id) {
            return back()->with('error', 'Cartela não pertence a esta partida.');
        }

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
        $cards = $this->cardsForPrint($request, $game);

        if ($cards->isEmpty()) {
            return redirect()->route('games.cards.index', $game)->with('error', 'Gere cartelas antes de imprimir.');
        }

        $this->issueCards($game, $cards);

        return view('cards.print', $this->printData($request, $game, $cards));
    }

    public function pdf(Request $request, Game $game, PdfService $pdf)
    {
        $cards = $this->cardsForPrint($request, $game);

        if ($cards->isEmpty()) {
            return redirect()->route('games.cards.index', $game)->with('error', 'Gere cartelas antes de gerar o PDF.');
        }

        $this->issueCards($game, $cards);

        return $pdf->render('cards.pdf', $this->printData($request, $game, $cards), "cartelas-{$game->id}.pdf");
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

    private function cardsForPrint(Request $request, Game $game)
    {
        $query = $game->cards()->orderBy('id');

        if ($request->filled('batch')) {
            $query->where('batch', (int) $request->query('batch'));
        }

        if ($code = $request->query('card')) {
            $query->where('code', trim($code));
        }

        return $query->get();
    }

    private function printData(Request $request, Game $game, $cards): array
    {
        $blocksCount = $cards->first()?->effectiveBlocks()->count() ?? 1;
        $layout = app(CardLayoutService::class)->layout($game, $blocksCount);

        // Modelo de arte personalizada aplicado à geração (padrão do
        // evento quando nenhum for informado). A arte nunca altera os
        // dados do jogo — apenas a aparência visual.
        $template = null;
        if ($request->query('template') === 'none') {
            $template = null;
        } elseif ($request->filled('template')) {
            $template = $game->cardTemplates()->find((int) $request->query('template'));
        } elseif (! $request->exists('template')) {
            $template = $game->cardTemplates()->where('is_default', true)->first();
        }

        $templateWarnings = [];
        if ($template) {
            $templateWarnings = $template->missingAreas($blocksCount);
        }

        // Blocos divididos em mais de uma folha quando não couberem
        // de forma legível e o organizador autorizar a divisão.
        $splitBlocks = $request->boolean('split_blocks') && $layout['warning'];

        $parts = [];
        foreach ($cards as $card) {
            $cardBlocks = $card->effectiveBlocks();
            $chunks = $splitBlocks || $cardBlocks->count() > $layout['blocks_per_sheet']
                ? $cardBlocks->chunk($layout['blocks_per_sheet'])
                : [$cardBlocks];

            foreach ($chunks as $chunk) {
                $parts[] = ['card' => $card, 'blocks' => $chunk->values()];
            }
        }

        return [
            'game' => $game,
            'cards' => $cards,
            'parts' => $parts,
            'layout' => $layout,
            'blocksCount' => $blocksCount,
            'template' => $template,
            'templateWarnings' => $templateWarnings,
            'perPage' => $blocksCount > 1
                ? 1
                : (in_array((int) $request->query('per_page'), [1, 2, 4]) ? (int) $request->query('per_page') : 2),
            'stub' => $request->boolean('stub', true),
            'showPrizes' => $request->boolean('prizes', true),
            'showPrice' => $request->boolean('price', true),
            'showBuyer' => $request->boolean('buyer', true),
            'showDate' => $request->boolean('date', true),
        ];
    }

    /**
     * Primeira emissão: registra a data e congela os prêmios exibidos.
     */
    private function issueCards(Game $game, $cards): void
    {
        foreach ($cards as $card) {
            if ($card->printed_at === null) {
                $card->forceFill([
                    'printed_at' => now(),
                    'prize_snapshot' => $card->prizesForPrint(),
                ])->save();
            }
        }
    }
}

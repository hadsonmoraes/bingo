<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardBlock;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Prize;
use App\Models\Winner;
use App\Services\GameStateService;
use App\Services\WinRuleValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WinnerController extends Controller
{
    /**
     * Valida um ganhador usando exclusivamente os números registrados
     * na rodada atual e a regra configurada para o prêmio. A validação
     * é feita sobre o BLOCO numérico correspondente ao prêmio — nunca
     * sobre a cartela inteira.
     */
    public function validate(Request $request, Game $game, WinRuleValidator $validator)
    {
        $validated = $request->validate([
            'card_code' => ['required', 'string'],
            'prize_id' => ['required', 'integer', 'exists:prizes,id'],
            'winner_name' => ['nullable', 'string', 'max:120'],
        ], [
            'card_code.required' => 'Informe o código da cartela.',
            'prize_id.exists' => 'Prêmio inválido.',
        ]);

        $card = $game->cards()->where('code', trim($validated['card_code']))->first();
        if (! $card) {
            return $this->error($request, $game, 'Cartela não encontrada nesta partida.');
        }

        if ($card->status === Card::STATUS_CANCELLED) {
            return $this->error($request, $game, 'Esta cartela está cancelada e não pode receber prêmios.');
        }

        $prize = Prize::where('game_id', $game->id)->findOrFail($validated['prize_id']);

        if ($prize->winner()->exists()) {
            return $this->error($request, $game, 'Este prêmio já possui ganhador validado.');
        }

        // Bloco numérico correspondente ao prêmio nesta cartela.
        // O comprador é preenchido automaticamente quando cadastrado, mas o
        // nome do ganhador real pode ser diferente e informado manualmente.
        $blocks = $card->effectiveBlocks();
        $block = $card->blocks()->where('prize_id', $prize->id)->first();

        if ($block === null && $blocks->count() > 1) {
            return $this->error($request, $game, 'Este prêmio não possui bloco numérico nesta cartela. Gere um novo lote com os prêmios atualizados.');
        }

        $grid = $block?->grid ?? $card->grid;

        $drawnNumbers = $game->draws()->pluck('number')->all();

        $valid = $validator->validate($grid, $drawnNumbers, $prize->rule, $prize->pattern);

        if (! $valid) {
            return $this->error($request, $game, 'O bloco não cumpre a regra do prêmio com as pedras registradas até agora.');
        }

        // Comprador cadastrado preenche o nome automaticamente; o operador
        // pode informar outro nome para o ganhador real.
        $winnerName = trim($validated['winner_name'] ?? '') ?: $card->buyer_name;

        $winner = DB::transaction(function () use ($game, $card, $block, $prize, $winnerName) {
            $winner = $game->winners()->create([
                'prize_id' => $prize->id,
                'card_id' => $card->id,
                'card_block_id' => $block?->id,
                'winner_name' => $winnerName,
                'validated_at' => now(),
            ]);

            if ($block) {
                $block->update(['status' => CardBlock::STATUS_WINNER]);
            }
            $card->update(['status' => Card::STATUS_WINNER]);

            $game->events()->create([
                'type' => GameEvent::TYPE_WINNER_VALIDATED,
                'data' => [
                    'prize' => $prize->name,
                    'card_code' => $card->code,
                    'block' => $block?->label(),
                    'winner_name' => $winner->winner_name,
                ],
            ]);

            return $winner;
        });

        $message = sprintf(
            'TEMOS UM GANHADOR! %s — cartela %s%s — prêmio "%s" (%s) — %s',
            $winnerName ?: 'Ganhador',
            $card->code,
            $block ? '/'.$block->label() : '',
            $prize->name,
            $prize->ruleLabel(),
            now()->format('d/m/Y H:i'),
        );

        // Aviso: a cartela pode ter sido vendida com o nome preenchido à mão
        // na via impressa, mas sem cadastro digital isso impede a conferência.
        if (! $card->buyer_name && ! $card->buyer_phone) {
            $message .= ' — Atenção: cartela sem comprador cadastrado.';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'type' => 'success',
                'winner' => [
                    'name' => $winnerName,
                    'card_code' => $card->code,
                    'block' => $block?->label(),
                    'prize' => $prize->name,
                    'rule_label' => $prize->ruleLabel(),
                    'validated_at' => $winner->validated_at->format('d/m/Y H:i:s'),
                ],
                'state' => app(GameStateService::class)->all($game->refresh()),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Desfaz uma validação de ganhador — usado, por exemplo, quando a
     * cartela não foi vendida ou foi validada por engano. O prêmio
     * volta à disputa, a cartela e o bloco retornam ao jogo e a
     * correção fica registrada no histórico com motivo obrigatório.
     */
    public function undo(Request $request, Game $game, Winner $winner)
    {
        if ($winner->game_id !== $game->id) {
            return $this->error($request, $game, 'Registro de ganhador não pertence a esta partida.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'reason.required' => 'Informe o motivo da correção.',
            'reason.min' => 'Descreva o motivo da correção com mais detalhes.',
        ]);

        $card = $winner->card;
        $block = $winner->block;
        $prize = $winner->prize;

        DB::transaction(function () use ($game, $winner, $card, $block, $prize, $validated) {
            $winner->delete();

            if ($block && $block->status === CardBlock::STATUS_WINNER) {
                $block->update(['status' => CardBlock::STATUS_ACTIVE]);
            }

            if ($card && $card->status === Card::STATUS_WINNER) {
                $card->update([
                    'status' => $game->isPlaying()
                        ? Card::STATUS_IN_PLAY
                        : ($card->buyer_name || $card->buyer_phone ? Card::STATUS_SOLD : Card::STATUS_AVAILABLE),
                ]);
            }

            $game->events()->create([
                'type' => GameEvent::TYPE_WINNER_REVERTED,
                'data' => [
                    'prize' => $prize?->name,
                    'card_code' => $card?->code,
                    'block' => $block?->label(),
                    'reason' => $validated['reason'],
                ],
            ]);
        });

        $message = sprintf(
            'Validação desfeita: prêmio "%s" volta à disputa. Correção registrada no histórico.',
            $prize?->name ?? 'prêmio',
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'type' => 'success',
                'state' => app(GameStateService::class)->all($game->refresh()),
            ]);
        }

        return back()->with('success', $message);
    }

    private function error(Request $request, Game $game, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'type' => 'error'], 422);
        }

        return back()->with('error', $message);
    }
}

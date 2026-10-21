<?php

namespace App\Http\Controllers;

use App\Http\Requests\RevertActionRequest;
use App\Http\Requests\StoreDrawRequest;
use App\Models\Game;
use App\Models\GameEvent;
use App\Services\GameStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DrawController extends Controller
{
    public function store(StoreDrawRequest $request, Game $game)
    {
        $validated = $request->validated();

        if ($game->status !== Game::STATUS_IN_PROGRESS) {
            return $this->error($request, $game, 'A partida precisa estar em andamento para registrar pedras.');
        }

        $number = (int) $validated['number'];

        // Somente números válidos para o formato selecionado
        if ($number < $game->number_min || $number > $game->number_max) {
            return $this->error($request, $game, "Número fora do intervalo válido ({$game->number_min} a {$game->number_max}).");
        }

        // Impede registro duplicado na mesma rodada
        if ($game->draws()->where('number', $number)->exists()) {
            return $this->error($request, $game, "O número {$number} já foi sorteado nesta rodada.", 'duplicate');
        }

        DB::transaction(function () use ($game, $number) {
            $sequence = ((int) $game->draws()->max('sequence')) + 1;

            $game->draws()->create([
                'number' => $number,
                'letter' => $game->letterFor($number),
                'sequence' => $sequence,
            ]);

            $game->events()->create([
                'type' => GameEvent::TYPE_DRAW_REGISTERED,
                'data' => ['number' => $number, 'letter' => $game->letterFor($number), 'sequence' => $sequence],
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Pedra {$number} registrada.",
                'type' => 'success',
                'state' => app(GameStateService::class)->all($game->refresh()),
            ]);
        }

        return back()->with('success', "Pedra {$number} registrada.");
    }

    /**
     * Desfaz o último registro de pedra, exigindo confirmação e
     * registrando a correção no histórico da partida.
     */
    public function undo(RevertActionRequest $request, Game $game)
    {
        if (! in_array($game->status, [Game::STATUS_IN_PROGRESS, Game::STATUS_PAUSED])) {
            return $this->error($request, $game, 'Não é possível desfazer registros com a partida neste estado.');
        }

        $validated = $request->validated();

        // reorder() remove o orderBy embutido na relação Game::draws()
        // (sequence ASC) — sem ele, first() devolveria a pedra mais antiga.
        $last = $game->draws()->reorder()->orderByDesc('sequence')->first();

        if (! $last) {
            return $this->error($request, $game, 'Não há pedras registradas para desfazer.');
        }

        DB::transaction(function () use ($game, $last, $validated) {
            $last->delete();

            $game->events()->create([
                'type' => GameEvent::TYPE_DRAW_REVERTED,
                'data' => [
                    'number' => $last->number,
                    'letter' => $last->letter,
                    'sequence' => $last->sequence,
                    'reason' => $validated['reason'],
                ],
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Registro da pedra {$last->number} desfeito.",
                'type' => 'success',
                'state' => app(GameStateService::class)->all($game->refresh()),
            ]);
        }

        return back()->with('success', "Registro da pedra {$last->number} desfeito.");
    }

    private function error(Request $request, Game $game, string $message, string $code = 'error')
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'type' => 'error',
                'code' => $code,
                'state' => app(GameStateService::class)->all($game),
            ], 422);
        }

        return back()->with('error', $message);
    }
}

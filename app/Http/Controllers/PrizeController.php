<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrizeRequest;
use App\Http\Requests\UpdatePrizeRequest;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Prize;
use App\Services\GameStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrizeController extends Controller
{
    public function index(Game $game)
    {
        $prizes = $game->prizes()->with('winner.card')->orderBy('sort_order')->orderBy('id')->get();

        return view('prizes.index', ['game' => $game, 'prizes' => $prizes]);
    }

    public function store(StorePrizeRequest $request, Game $game)
    {
        $validated = $request->validated();

        $pattern = $this->parsePattern($request, $game);

        if ($validated['rule'] === Prize::RULE_CUSTOM_PATTERN && $pattern === null) {
            return back()->withErrors(['pattern' => 'Desenhe o padrão de vitória na grade.'])->withInput();
        }

        $game->prizes()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'estimated_value' => $validated['estimated_value'] ?? null,
            'rule' => $validated['rule'],
            'pattern' => $pattern,
            'sort_order' => ((int) $game->prizes()->max('sort_order')) + 1,
            'image_path' => $request->hasFile('image') ? $request->file('image')->store('prizes', 'public') : null,
        ]);

        return back()->with('success', 'Prêmio cadastrado.');
    }

    /**
     * Avança manualmente o ponteiro do prêmio em disputa para o próximo
     * prêmio sem ganhador na sequência (do último para o primeiro). O
     * prêmio anterior continua disponível para validação manual.
     */
    public function advance(Request $request, Game $game, GameStateService $state)
    {
        $current = $state->currentPrize($game);
        $next = $state->nextPrize($game);

        if ($next === null) {
            $message = $current === null
                ? 'Não há prêmios em disputa para avançar.'
                : sprintf('"%s" é o último prêmio da disputa.', $current->name);

            return $this->advanceError($request, $game, $message);
        }

        $game->update(['current_prize_id' => $next->id]);

        $game->events()->create([
            'type' => GameEvent::TYPE_PRIZE_ADVANCED,
            'data' => [
                'from' => $current?->name,
                'to' => $next->name,
            ],
        ]);

        $message = sprintf(
            'Prêmio em disputa avançado: "%s" → "%s".',
            $current?->name ?? '—',
            $next->name,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'type' => 'success',
                'state' => $state->all($game->refresh()),
            ]);
        }

        return back()->with('success', $message);
    }

    private function advanceError(Request $request, Game $game, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'type' => 'error',
                'state' => app(GameStateService::class)->all($game),
            ], 422);
        }

        return back()->with('error', $message);
    }

    public function edit(Game $game, Prize $prize)
    {
        return view('prizes.edit', ['game' => $game, 'prize' => $prize]);
    }

    public function update(UpdatePrizeRequest $request, Game $game, Prize $prize)
    {
        $validated = $request->validated();

        $pattern = $this->parsePattern($request, $game);

        if ($validated['rule'] === Prize::RULE_CUSTOM_PATTERN && $pattern === null) {
            return back()->withErrors(['pattern' => 'Desenhe o padrão de vitória na grade.'])->withInput();
        }

        $data = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'estimated_value' => $validated['estimated_value'] ?? null,
            'rule' => $validated['rule'],
            'pattern' => $pattern,
        ];

        if ($request->hasFile('image')) {
            if ($prize->image_path) {
                Storage::disk('public')->delete($prize->image_path);
            }
            $data['image_path'] = $request->file('image')->store('prizes', 'public');
        }

        if ($request->boolean('remove_image') && $prize->image_path) {
            Storage::disk('public')->delete($prize->image_path);
            $data['image_path'] = null;
        }

        $prize->update($data);

        return redirect()->route('games.prizes.index', $game)->with('success', 'Prêmio atualizado.');
    }

    public function destroy(Game $game, Prize $prize)
    {
        if ($prize->winner()->exists()) {
            return back()->with('error', 'Não é possível excluir um prêmio que já tem ganhador validado.');
        }

        $prize->delete();

        return back()->with('success', 'Prêmio excluído.');
    }

    private function parsePattern(Request $request, Game $game): ?array
    {
        $raw = $request->input('pattern');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $rows = $game->card_format === '90' ? 3 : 5;
        $cols = $game->card_format === '90' ? 9 : 5;

        $pattern = [];
        $any = false;

        for ($r = 0; $r < $rows; $r++) {
            $row = [];
            for ($c = 0; $c < $cols; $c++) {
                $selected = (bool) ($decoded[$r][$c] ?? false);
                $row[] = $selected;
                $any = $any || $selected;
            }
            $pattern[] = $row;
        }

        return $any ? $pattern : null;
    }
}

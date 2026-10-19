<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Game;
use App\Models\GameEvent;
use App\Services\GameStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GameController extends Controller
{
    public function index()
    {
        $games = Game::withCount(['draws', 'cards', 'winners'])
            ->latest('id')
            ->get();

        return view('games.index', ['games' => $games]);
    }

    public function create()
    {
        return view('games.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'organization_name' => ['nullable', 'string', 'max:150'],
            'event_date' => ['nullable', 'date'],
            'card_price' => ['nullable', 'numeric', 'min:0'],
            'theme_color' => ['nullable', 'in:'.implode(',', array_keys(Game::THEME_COLORS))],
            'logo' => ['nullable', 'image', 'max:2048'],
            'card_format' => ['required', 'in:75,90,custom'],
            'number_min' => ['required', 'integer', 'min:1', 'max:200'],
            'number_max' => ['required', 'integer', 'gt:number_min', 'max:200'],
            'use_letters' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Informe o nome da partida.',
            'number_max.gt' => 'O número máximo deve ser maior que o mínimo.',
        ]);

        $format = $validated['card_format'];

        if ($format === '75') {
            $validated['number_min'] = 1;
            $validated['number_max'] = 75;
            $validated['use_letters'] = true;
        } elseif ($format === '90') {
            $validated['number_min'] = 1;
            $validated['number_max'] = 90;
            $validated['use_letters'] = false;
        } else {
            $validated['use_letters'] = false;
            if ($validated['number_max'] - $validated['number_min'] + 1 < 25) {
                return back()->withErrors(['number_max' => 'O formato personalizado precisa de pelo menos 25 números possíveis.'])->withInput();
            }
        }

        $game = Game::create($validated + ['status' => Game::STATUS_SETUP]);

        if ($request->hasFile('logo')) {
            $game->update(['logo_path' => $request->file('logo')->store('logos', 'public')]);
        }

        return redirect()->route('games.show', $game)->with('success', 'Partida criada. Cadastre os prêmios e gere as cartelas.');
    }

    public function edit(Game $game)
    {
        return view('games.edit', ['game' => $game]);
    }

    public function update(Request $request, Game $game)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'organization_name' => ['nullable', 'string', 'max:150'],
            'event_date' => ['nullable', 'date'],
            'card_price' => ['nullable', 'numeric', 'min:0'],
            'theme_color' => ['nullable', 'in:'.implode(',', array_keys(Game::THEME_COLORS))],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->boolean('remove_logo')) {
            if ($game->logo_path) {
                Storage::disk('public')->delete($game->logo_path);
            }
            $validated['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            if ($game->logo_path) {
                Storage::disk('public')->delete($game->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $game->update($validated);

        return redirect()->route('games.show', $game)->with('success', 'Dados do evento atualizados.');
    }

    public function threshold(Request $request, Game $game)
    {
        $validated = $request->validate([
            'threshold' => ['required', 'integer', 'min:0', 'max:5'],
        ]);

        $game->update(['na_boa_threshold' => $validated['threshold']]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Limite de proximidade atualizado.',
                'type' => 'success',
                'state' => app(GameStateService::class)->all($game),
            ]);
        }

        return back()->with('success', 'Limite de proximidade atualizado.');
    }

    public function show(Game $game)
    {
        $game->loadCount(['draws', 'cards', 'winners']);
        $prizes = $game->prizes()->with('winner.card')->orderBy('sort_order')->get();
        $winners = $game->winners()->with(['prize', 'card'])->get();

        return view('games.show', [
            'game' => $game,
            'prizes' => $prizes,
            'winners' => $winners,
        ]);
    }

    public function start(Request $request, Game $game)
    {
        if ($game->status !== Game::STATUS_SETUP) {
            return back()->with('error', 'A partida já foi iniciada.');
        }

        $game->update([
            'status' => Game::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        // Cartelas vendidas ou disponíveis entram em jogo
        $game->cards()
            ->whereIn('status', [Card::STATUS_AVAILABLE, Card::STATUS_SOLD])
            ->update(['status' => Card::STATUS_IN_PLAY]);

        $game->events()->create(['type' => GameEvent::TYPE_GAME_STARTED, 'data' => ['at' => now()->toDateTimeString()]]);

        return redirect()->route('games.draw', $game)->with('success', 'Partida iniciada.');
    }

    public function pause(Request $request, Game $game)
    {
        if ($game->status !== Game::STATUS_IN_PROGRESS) {
            return $this->jsonOrBack($request, $game, 'A partida não está em andamento.', 'error');
        }

        $game->update(['status' => Game::STATUS_PAUSED]);
        $game->events()->create(['type' => GameEvent::TYPE_GAME_PAUSED, 'data' => ['at' => now()->toDateTimeString()]]);

        return $this->jsonOrBack($request, $game, 'Partida pausada.');
    }

    public function resume(Request $request, Game $game)
    {
        if ($game->status !== Game::STATUS_PAUSED) {
            return $this->jsonOrBack($request, $game, 'A partida não está pausada.', 'error');
        }

        $game->update(['status' => Game::STATUS_IN_PROGRESS]);
        $game->events()->create(['type' => GameEvent::TYPE_GAME_RESUMED, 'data' => ['at' => now()->toDateTimeString()]]);

        return $this->jsonOrBack($request, $game, 'Partida retomada.');
    }

    public function finish(Request $request, Game $game)
    {
        if (! in_array($game->status, [Game::STATUS_IN_PROGRESS, Game::STATUS_PAUSED])) {
            return $this->jsonOrBack($request, $game, 'A partida não está em andamento.', 'error');
        }

        $game->update([
            'status' => Game::STATUS_FINISHED,
            'finished_at' => now(),
        ]);
        $game->events()->create(['type' => GameEvent::TYPE_GAME_FINISHED, 'data' => ['at' => now()->toDateTimeString()]]);

        return $this->jsonOrBack($request, $game, 'Partida encerrada.');
    }

    public function state(Request $request, Game $game)
    {
        return response()->json(app(GameStateService::class)->all($game));
    }

    public function draw(Game $game)
    {
        return view('games.draw', [
            'game' => $game,
            'state' => app(GameStateService::class)->all($game),
        ]);
    }

    public function screen(Game $game)
    {
        return view('games.screen', [
            'game' => $game,
            'state' => app(GameStateService::class)->all($game),
        ]);
    }

    public function report(Game $game)
    {
        $game->load(['draws' => fn ($q) => $q->orderBy('sequence'), 'events', 'winners.prize', 'winners.card']);

        return view('games.report', ['game' => $game]);
    }

    private function jsonOrBack(Request $request, Game $game, string $message, string $type = 'success')
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'type' => $type,
                'state' => app(GameStateService::class)->all($game),
            ]);
        }

        return back()->with($type, $message);
    }
}

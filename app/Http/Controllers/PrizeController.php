<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Prize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrizeController extends Controller
{
    public function index(Game $game)
    {
        $prizes = $game->prizes()->with('winner.card')->orderBy('sort_order')->orderBy('id')->get();

        return view('prizes.index', ['game' => $game, 'prizes' => $prizes]);
    }

    public function store(Request $request, Game $game)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'estimated_value' => ['nullable', 'string', 'max:60'],
            'rule' => ['required', 'in:'.implode(',', array_keys(Prize::RULES))],
            'pattern' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
        ], [
            'name.required' => 'Informe o nome do prêmio.',
            'rule.required' => 'Selecione a regra de vitória.',
        ]);

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

    public function edit(Game $game, Prize $prize)
    {
        return view('prizes.edit', ['game' => $game, 'prize' => $prize]);
    }

    public function update(Request $request, Game $game, Prize $prize)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'estimated_value' => ['nullable', 'string', 'max:60'],
            'rule' => ['required', 'in:'.implode(',', array_keys(Prize::RULES))],
            'pattern' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
        ], [
            'name.required' => 'Informe o nome do prêmio.',
            'rule.required' => 'Selecione a regra de vitória.',
        ]);

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

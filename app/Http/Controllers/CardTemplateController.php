<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importação de arte personalizada: o organizador baixa a arte,
 * edita em programas gráficos e reimporta como modelo reutilizável,
 * mapeando as áreas onde o sistema insere os dados dinâmicos.
 * A arte nunca altera os números, a identidade da cartela ou a
 * relação entre blocos e prêmios.
 */
class CardTemplateController extends Controller
{
    public function index(Game $game)
    {
        return view('card-templates.index', [
            'game' => $game,
            'templates' => $game->cardTemplates()->orderByDesc('is_default')->orderBy('name')->get(),
            'prizesCount' => $game->prizes()->count(),
        ]);
    }

    public function store(Request $request, Game $game)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'art' => ['required', 'file', 'max:4096'],
        ], [
            'name.required' => 'Informe um nome para o modelo.',
            'art.required' => 'Selecione o arquivo de arte (SVG, PNG ou PDF).',
            'art.max' => 'O arquivo deve ter no máximo 4 MB.',
        ]);

        $file = $request->file('art');
        $mime = $file->getMimeType();

        if (! in_array($mime, ['image/svg+xml', 'image/png', 'image/jpeg', 'application/pdf'])) {
            return back()->with('error', 'Formato não suportado. Envie SVG, PNG ou PDF.')->withInput();
        }

        if (! $this->isSafeUpload($file->getRealPath(), $mime)) {
            return back()->with('error', 'Arquivo inválido ou corrompido.')->withInput();
        }

        $path = $file->store('card-templates', 'public');

        $template = $game->cardTemplates()->create([
            'name' => $validated['name'],
            'art_path' => $path,
            'art_mime' => $mime,
            'areas' => $this->defaultAreas($game),
            'is_default' => $game->cardTemplates()->count() === 0,
        ]);

        return redirect()
            ->route('games.templates.edit', [$game, $template])
            ->with('success', 'Arte importada. Ajuste as áreas dos dados dinâmicos abaixo.');
    }

    /**
     * Editor de áreas: posiciona os dados dinâmicos sobre a arte
     * importada, em milímetros, dentro da folha A4.
     */
    public function edit(Game $game, CardTemplate $template)
    {
        return view('card-templates.edit', [
            'game' => $game,
            'template' => $template,
            'blocksNeeded' => max(1, $game->prizes()->count()),
        ]);
    }

    public function update(Request $request, Game $game, CardTemplate $template)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
            'areas' => ['required', 'array'],
        ]);

        $areas = $this->parseAreas($request->input('areas'), max(1, $game->prizes()->count()));

        $data = [
            'name' => $validated['name'],
            'areas' => $areas,
        ];

        if ($request->boolean('is_default')) {
            $game->cardTemplates()->update(['is_default' => false]);
            $data['is_default'] = true;
        } else {
            $data['is_default'] = false;
        }

        $template->update($data);

        return redirect()->route('games.templates.index', $game)
            ->with('success', 'Modelo "'.$template->name.'" salvo.');
    }

    public function duplicate(Request $request, Game $game, CardTemplate $template)
    {
        $copy = $template->replicate();
        $copy->name = $template->name.' (cópia)';
        $copy->is_default = false;

        // Cópia física da arte para permitir exclusões independentes
        if (Storage::disk('public')->exists($template->art_path)) {
            $copy->art_path = Storage::disk('public')->put(
                'card-templates',
                Storage::disk('public')->get($template->art_path)
            ) ?: $template->art_path;
        }

        $game->cardTemplates()->save($copy);

        return back()->with('success', 'Modelo duplicado. Ajuste a cópia conforme necessário.');
    }

    public function setDefault(Request $request, Game $game, CardTemplate $template)
    {
        $game->cardTemplates()->update(['is_default' => false]);
        $template->update(['is_default' => true]);

        return back()->with('success', '"'.$template->name.'" é agora o modelo padrão do evento.');
    }

    public function destroy(Request $request, Game $game, CardTemplate $template)
    {
        if ($template->art_path) {
            Storage::disk('public')->delete($template->art_path);
        }

        $wasDefault = $template->is_default;
        $name = $template->name;
        $template->delete();

        if ($wasDefault) {
            $first = $game->cardTemplates()->first();
            $first?->update(['is_default' => true]);
        }

        return back()->with('success', 'Modelo "'.$name.'" excluído.');
    }

    /**
     * Áreas iniciais sugeridas (mm, dentro do A4) para agilizar o
     * mapeamento — o organizador ajusta depois no editor.
     */
    private function defaultAreas(Game $game): array
    {
        $blocksNeeded = max(1, $game->prizes()->count());

        $areas = [
            'header' => ['x' => 10, 'y' => 10, 'w' => 190, 'h' => 20],
            'code' => ['x' => 150, 'y' => 12, 'w' => 45, 'h' => 8],
            'prizes' => ['x' => 10, 'y' => 34, 'w' => 190, 'h' => 26],
            'buyer' => ['x' => 10, 'y' => 258, 'w' => 190, 'h' => 12],
            'stub' => ['x' => 10, 'y' => 240, 'w' => 190, 'h' => 14],
            'blocks' => [],
        ];

        $perRow = min(3, $blocksNeeded);
        for ($i = 0; $i < $blocksNeeded; $i++) {
            $areas['blocks'][] = [
                'x' => 10 + ($i % $perRow) * 64,
                'y' => 66 + intdiv($i, $perRow) * 74,
                'w' => 60,
                'h' => 70,
            ];
        }

        return $areas;
    }

    /**
     * Normaliza as áreas enviadas: retângulos em mm dentro do A4.
     */
    private function parseAreas(array $raw, int $blocksNeeded): array
    {
        $rect = function ($value) {
            return [
                'x' => min(210, max(0, (float) ($value['x'] ?? 0))),
                'y' => min(297, max(0, (float) ($value['y'] ?? 0))),
                'w' => min(210, max(5, (float) ($value['w'] ?? 10))),
                'h' => min(297, max(5, (float) ($value['h'] ?? 10))),
            ];
        };

        $areas = [];
        foreach (CardTemplate::AREA_KEYS as $key) {
            if (! empty($raw[$key])) {
                $areas[$key] = $rect($raw[$key]);
            }
        }

        $areas['blocks'] = [];
        for ($i = 0; $i < $blocksNeeded; $i++) {
            if (! empty($raw['blocks'][$i])) {
                $areas['blocks'][] = $rect($raw['blocks'][$i]);
            }
        }

        return $areas;
    }

    /**
     * Validação segura do conteúdo: imagens reais (binário), PDF com
     * cabeçalho válido e SVG sanitizado (sem scripts ou eventos inline).
     */
    private function isSafeUpload(string $path, string $mime): bool
    {
        if ($mime === 'application/pdf') {
            $head = file_get_contents($path, false, null, 0, 5) ?: '';

            return Str::startsWith($head, '%PDF');
        }

        if (in_array($mime, ['image/png', 'image/jpeg'])) {
            return @getimagesize($path) !== false;
        }

        if ($mime === 'image/svg+xml') {
            $svg = (string) file_get_contents($path);

            // Rejeita SVG com scripts ou atributos de evento inline
            return ! preg_match('/<script|on\w+\s*=/i', $svg);
        }

        return false;
    }
}

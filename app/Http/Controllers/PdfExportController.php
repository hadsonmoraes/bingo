<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\PdfExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportações de PDF geradas pela fila (lotes grandes de cartelas).
 * O painel de cartelas acompanha o andamento e baixa o arquivo pronto.
 */
class PdfExportController extends Controller
{
    /**
     * Estado das exportações recentes — alimenta o polling do painel.
     */
    public function index(Game $game): JsonResponse
    {
        $exports = $game->pdfExports()->latest('id')->limit(10)->get();

        return response()->json([
            'exports' => $exports->map(fn (PdfExport $export) => [
                'id' => $export->id,
                'status' => $export->status,
                'status_label' => $export->statusLabel(),
                'cards_count' => $export->cards_count,
                'file_name' => $export->file_name,
                'error' => $export->error,
                'created_at' => $export->created_at?->format('d/m/Y H:i'),
                'download_url' => $export->status === PdfExport::STATUS_COMPLETED
                    ? route('games.pdf-exports.download', [$game, $export])
                    : null,
                'destroy_url' => route('games.pdf-exports.destroy', [$game, $export]),
            ])->all(),
        ]);
    }

    public function download(Game $game, PdfExport $export): StreamedResponse
    {
        if ($export->game_id !== $game->id
            || $export->status !== PdfExport::STATUS_COMPLETED
            || ! $export->file_path
            || ! Storage::disk('local')->exists($export->file_path)) {
            abort(404, 'Arquivo de exportação não encontrado.');
        }

        return response()->streamDownload(function () use ($export) {
            echo Storage::disk('local')->get($export->file_path);
        }, $export->file_name ?? 'cartelas', [
            'Content-Type' => Storage::disk('local')->mimeType($export->file_path),
        ]);
    }

    public function destroy(Request $request, Game $game, PdfExport $export)
    {
        if ($export->game_id !== $game->id) {
            abort(404);
        }

        $export->deleteFile();
        $export->delete();

        return back()->with('success', 'Exportação de PDF removida.');
    }
}

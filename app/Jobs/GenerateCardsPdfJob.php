<?php

namespace App\Jobs;

use App\Models\Game;
use App\Models\PdfExport;
use App\Services\CardPrintService;
use App\Services\PdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Gera o PDF A4 de um lote grande de cartelas fora da requisição HTTP.
 * O dompdf carrega o documento inteiro na memória, então a renderização
 * é feita em blocos pequenos: cada bloco vira um PDF e o resultado é
 * empacotado num ZIP (storage/app/pdf-exports), mantendo o consumo de
 * memória constante mesmo com centenas de cartelas.
 */
class GenerateCardsPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    /** Cartelas renderizadas por vez — limita o pico de memória do dompdf. */
    public const RENDER_CHUNK_SIZE = 10;

    public function __construct(public PdfExport $export) {}

    public function handle(CardPrintService $prints, PdfService $pdf): void
    {
        $this->export->update(['status' => PdfExport::STATUS_PROCESSING]);

        /** @var Game $game */
        $game = $this->export->game()->firstOrFail();

        $cards = $prints->cardsForFilters($game, $this->export->filters ?? []);

        if ($cards->isEmpty()) {
            $this->export->update([
                'status' => PdfExport::STATUS_FAILED,
                'error' => 'Nenhuma cartela encontrada com os filtros informados.',
            ]);

            return;
        }

        $prints->issue($game, $cards);

        $filename = 'cartelas-'.$game->id.'-'.now()->format('Ymd-His').'.zip';
        $path = $this->export->storePath($filename);

        Storage::disk('local')->makeDirectory(dirname($path));

        $zip = new ZipArchive;
        $zipOpened = $zip->open(Storage::disk('local')->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($zipOpened !== true) {
            $this->export->update([
                'status' => PdfExport::STATUS_FAILED,
                'error' => 'Não foi possível criar o arquivo ZIP (código '.$zipOpened.').',
            ]);

            return;
        }

        try {
            foreach ($cards->chunk(self::RENDER_CHUNK_SIZE) as $index => $chunk) {
                $data = $prints->data($game, $this->export->filters ?? [], $chunk->values(), $this->export->base_url);

                $zip->addFromString(
                    sprintf('%03d', $index + 1).'-cartelas.pdf',
                    $pdf->output('cards.pdf', $data),
                );
            }
        } finally {
            $zip->close();
        }

        $this->export->update([
            'status' => PdfExport::STATUS_COMPLETED,
            'cards_count' => $cards->count(),
            'file_path' => $path,
            'file_name' => $filename,
            'error' => null,
        ]);
    }

    /**
     * Qualquer falha (dompdf, disco, view) fica registrada no painel —
     * o operador vê o motivo em vez de um pedido que nunca termina.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->export->update([
            'status' => PdfExport::STATUS_FAILED,
            'error' => mb_substr($exception?->getMessage() ?? 'Erro desconhecido', 0, 500),
        ]);
    }
}

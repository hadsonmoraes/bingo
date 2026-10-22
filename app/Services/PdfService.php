<?php

namespace App\Services;

use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfService
{
    /**
     * Gera o PDF A4 a partir de uma view Blade e devolve os bytes —
     * usado pelo job da fila para gravar o arquivo em disco.
     */
    public function output(string $view, array $data): string
    {
        $dompdf = $this->dompdf($view, $data);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Gera um PDF A4 a partir de uma view Blade — renderizado no servidor,
     * com margens e alinhamento controlados.
     */
    public function render(string $view, array $data, string $filename): StreamedResponse
    {
        $dompdf = $this->dompdf($view, $data);
        $dompdf->render();

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function dompdf(string $view, array $data): Dompdf
    {
        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->setPaper('a4', 'portrait');

        return $dompdf;
    }
}

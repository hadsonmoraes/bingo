<?php

namespace App\Services;

use Dompdf\Dompdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfService
{
    /**
     * Gera um PDF A4 a partir de uma view Blade — renderizado no servidor,
     * com margens e alinhamento controlados.
     */
    public function render(string $view, array $data, string $filename): StreamedResponse
    {
        $dompdf = new Dompdf(['isRemoteEnabled' => false]);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}

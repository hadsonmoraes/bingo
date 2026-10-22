<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\CardGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gera um lote grande de cartelas fora da requisição HTTP. Cada cartela
 * exige verificação de duplicidade contra todas as já existentes no
 * evento (custo quadrático), então lotes grandes são processados pela
 * fila para não bloquear o servidor web.
 */
class GenerateCardsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public Game $game,
        public int $quantity,
        public int $batch,
    ) {}

    public function handle(CardGenerator $generator): void
    {
        $generator->generateBatch($this->game, $this->quantity, $this->batch);
    }
}

<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\CardPublicController;
use App\Http\Controllers\CardTemplateController;
use App\Http\Controllers\DrawController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\PdfExportController;
use App\Http\Controllers\PrizeController;
use App\Http\Controllers\WinnerController;
use Illuminate\Support\Facades\Route;

Route::scopeBindings()->group(function () {
    // Painel / histórico de partidas
    Route::get('/', [GameController::class, 'index'])->name('games.index');
    Route::get('/games/create', [GameController::class, 'create'])->name('games.create');
    Route::post('/games', [GameController::class, 'store'])->name('games.store');
    Route::get('/games/{game}/edit', [GameController::class, 'edit'])->name('games.edit');
    Route::put('/games/{game}', [GameController::class, 'update'])->name('games.update');
    Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::get('/games/{game}/report', [GameController::class, 'report'])->name('games.report');

    // Ciclo de vida da partida
    Route::post('/games/{game}/start', [GameController::class, 'start'])->name('games.start');
    Route::post('/games/{game}/pause', [GameController::class, 'pause'])->name('games.pause');
    Route::post('/games/{game}/resume', [GameController::class, 'resume'])->name('games.resume');
    Route::post('/games/{game}/finish', [GameController::class, 'finish'])->name('games.finish');

    // Estado da partida (polling)
    Route::get('/games/{game}/state', [GameController::class, 'state'])->name('games.state');
    Route::post('/games/{game}/na-boa-threshold', [GameController::class, 'threshold'])->name('games.threshold');

    // Tela do operador (registro manual das pedras do globo físico)
    Route::get('/games/{game}/draw', [GameController::class, 'draw'])->name('games.draw');
    Route::post('/games/{game}/draws', [DrawController::class, 'store'])->name('draws.store');
    Route::post('/games/{game}/draws/undo', [DrawController::class, 'undo'])->name('draws.undo');

    // Tela pública (TV / projetor)
    Route::get('/games/{game}/screen', [GameController::class, 'screen'])->name('games.screen');

    // Acompanhamento público de uma cartela (destino do QR Code impresso)
    Route::get('/games/{game}/card/{code}', [CardPublicController::class, 'show'])->name('games.cards.public');
    Route::get('/games/{game}/card/{code}/state', [CardPublicController::class, 'state'])->name('games.cards.public.state');

    // Prêmios
    Route::get('/games/{game}/prizes', [PrizeController::class, 'index'])->name('games.prizes.index');
    Route::post('/games/{game}/prizes', [PrizeController::class, 'store'])->name('games.prizes.store');
    Route::post('/games/{game}/prizes/advance', [PrizeController::class, 'advance'])->name('games.prizes.advance');
    Route::get('/games/{game}/prizes/{prize}/edit', [PrizeController::class, 'edit'])->name('games.prizes.edit');
    Route::put('/games/{game}/prizes/{prize}', [PrizeController::class, 'update'])->name('games.prizes.update');
    Route::delete('/games/{game}/prizes/{prize}', [PrizeController::class, 'destroy'])->name('games.prizes.destroy');

    // Cartelas
    Route::get('/games/{game}/cards', [CardController::class, 'index'])->name('games.cards.index');
    Route::post('/games/{game}/cards/generate', [CardController::class, 'generate'])->name('games.cards.generate');
    Route::get('/games/{game}/cards/generation-status', [CardController::class, 'generationStatus'])->name('games.cards.generation-status');
    Route::post('/games/{game}/cards/{card}/buyer', [CardController::class, 'storeBuyer'])->name('games.cards.buyer');
    Route::post('/games/{game}/cards/check', [CardController::class, 'check'])->name('games.cards.check');
    Route::get('/games/{game}/check', [CardController::class, 'checkPage'])->name('games.check');
    Route::post('/games/{game}/cards/{card}/cancel', [CardController::class, 'cancel'])->name('games.cards.cancel');
    Route::get('/games/{game}/cards/print', [CardController::class, 'print'])->name('games.cards.print');
    Route::get('/games/{game}/cards/pdf', [CardController::class, 'pdf'])->name('games.cards.pdf');

    // Exportações de PDF em fila (lotes grandes de cartelas)
    Route::get('/games/{game}/pdf-exports', [PdfExportController::class, 'index'])->name('games.pdf-exports.index');
    Route::get('/games/{game}/pdf-exports/{pdfExport}/download', [PdfExportController::class, 'download'])->name('games.pdf-exports.download');
    Route::delete('/games/{game}/pdf-exports/{pdfExport}', [PdfExportController::class, 'destroy'])->name('games.pdf-exports.destroy');

    // Arte para edição externa ("Baixar arte para editar")
    Route::get('/games/{game}/cards/art', [CardController::class, 'artSvg'])->name('games.cards.art');
    Route::get('/games/{game}/cards/art/model', [CardController::class, 'artModelBase'])->name('games.cards.art-model');

    // Modelos de arte personalizada ("Importar arte personalizada")
    Route::get('/games/{game}/card-templates', [CardTemplateController::class, 'index'])->name('games.templates.index');
    Route::post('/games/{game}/card-templates', [CardTemplateController::class, 'store'])->name('games.templates.store');
    Route::get('/games/{game}/card-templates/{cardTemplate}/edit', [CardTemplateController::class, 'edit'])->name('games.templates.edit');
    Route::put('/games/{game}/card-templates/{cardTemplate}', [CardTemplateController::class, 'update'])->name('games.templates.update');
    Route::post('/games/{game}/card-templates/{cardTemplate}/duplicate', [CardTemplateController::class, 'duplicate'])->name('games.templates.duplicate');
    Route::post('/games/{game}/card-templates/{cardTemplate}/default', [CardTemplateController::class, 'setDefault'])->name('games.templates.default');
    Route::delete('/games/{game}/card-templates/{cardTemplate}', [CardTemplateController::class, 'destroy'])->name('games.templates.destroy');

    // Validação de ganhador
    Route::post('/games/{game}/winners/validate', [WinnerController::class, 'validate'])->name('winners.validate');
    Route::post('/games/{game}/winners/{winner}/undo', [WinnerController::class, 'undo'])->name('winners.undo');
});

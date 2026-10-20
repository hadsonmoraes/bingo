<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameEvent extends Model
{
    public const TYPE_DRAW_REGISTERED = 'draw_registered';

    public const TYPE_DRAW_REVERTED = 'draw_reverted';

    public const TYPE_GAME_STARTED = 'game_started';

    public const TYPE_GAME_PAUSED = 'game_paused';

    public const TYPE_GAME_RESUMED = 'game_resumed';

    public const TYPE_GAME_FINISHED = 'game_finished';

    public const TYPE_WINNER_VALIDATED = 'winner_validated';

    public const TYPE_WINNER_REVERTED = 'winner_reverted';

    public const TYPE_BUYER_UPDATED = 'buyer_updated';

    public const TYPE_CARD_CANCELLED = 'card_cancelled';

    public const TYPE_PRIZE_ADVANCED = 'prize_advanced';

    public const TYPE_LABELS = [
        self::TYPE_DRAW_REGISTERED => 'Pedra registrada',
        self::TYPE_DRAW_REVERTED => 'Correção (registro desfeito)',
        self::TYPE_GAME_STARTED => 'Partida iniciada',
        self::TYPE_GAME_PAUSED => 'Partida pausada',
        self::TYPE_GAME_RESUMED => 'Partida retomada',
        self::TYPE_GAME_FINISHED => 'Partida encerrada',
        self::TYPE_WINNER_VALIDATED => 'Ganhador validado',
        self::TYPE_WINNER_REVERTED => 'Ganhador desfeito (correção)',
        self::TYPE_BUYER_UPDATED => 'Comprador de cartela registrado',
        self::TYPE_CARD_CANCELLED => 'Cartela cancelada',
        self::TYPE_PRIZE_ADVANCED => 'Prêmio avançado manualmente',
    ];

    protected $fillable = ['game_id', 'type', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Winner extends Model
{
    protected $fillable = ['game_id', 'prize_id', 'card_id', 'card_block_id', 'winner_name', 'validated_at'];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(CardBlock::class, 'card_block_id');
    }
}

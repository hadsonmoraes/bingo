<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Draw extends Model
{
    protected $fillable = ['game_id', 'number', 'letter', 'sequence'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}

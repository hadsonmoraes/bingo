<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CardBlock extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_WINNER = 'winner';

    protected $fillable = [
        'card_id',
        'prize_id',
        'prize_name',
        'prize_rule_label',
        'rule',
        'pattern',
        'position',
        'grid',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'grid' => 'array',
            'pattern' => 'array',
            'position' => 'integer',
        ];
    }

    /**
     * Identificador público do bloco: código da cartela + posição.
     * Ex.: C-0001/B2 (segundo bloco da cartela C-0001).
     */
    public function label(): string
    {
        return 'B'.$this->position;
    }

    public function fullLabel(): string
    {
        return $this->card?->code.'/'.$this->label();
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    public function winner(): HasOne
    {
        return $this->hasOne(Winner::class);
    }
}

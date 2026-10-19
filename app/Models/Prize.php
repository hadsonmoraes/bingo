<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prize extends Model
{
    public const RULE_FULL_CARD = 'full_card';

    public const RULE_ONE_LINE = 'one_line';

    public const RULE_TWO_LINES = 'two_lines';

    public const RULE_FOUR_CORNERS = 'four_corners';

    public const RULE_CUSTOM_PATTERN = 'custom_pattern';

    public const RULES = [
        self::RULE_FULL_CARD => 'Cartela cheia',
        self::RULE_ONE_LINE => 'Uma linha completa',
        self::RULE_TWO_LINES => 'Duas linhas completas',
        self::RULE_FOUR_CORNERS => 'Quatro cantos',
        self::RULE_CUSTOM_PATTERN => 'Padrão personalizado',
    ];

    protected $fillable = [
        'game_id',
        'name',
        'description',
        'rule',
        'pattern',
        'sort_order',
        'estimated_value',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'pattern' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function winner(): HasOne
    {
        return $this->hasOne(Winner::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CardBlock::class);
    }

    public function ruleLabel(): string
    {
        return self::RULES[$this->rule] ?? $this->rule;
    }
}

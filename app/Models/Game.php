<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Game extends Model
{
    public const STATUS_SETUP = 'setup';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_FINISHED = 'finished';

    public const LETTERS = ['B', 'I', 'N', 'G', 'O'];

    protected $fillable = [
        'name',
        'number_min',
        'number_max',
        'use_letters',
        'card_format',
        'status',
        'started_at',
        'finished_at',
        'organization_name',
        'logo_path',
        'event_date',
        'card_price',
        'theme_color',
        'na_boa_threshold',
        'current_prize_id',
    ];

    protected function casts(): array
    {
        return [
            'number_min' => 'integer',
            'number_max' => 'integer',
            'use_letters' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'event_date' => 'date',
            'card_price' => 'decimal:2',
            'na_boa_threshold' => 'integer',
            'current_prize_id' => 'integer',
        ];
    }

    public const THEME_COLORS = [
        'amber' => 'Âmbar',
        'emerald' => 'Verde',
        'sky' => 'Azul',
        'rose' => 'Rosa',
        'violet' => 'Violeta',
        'slate' => 'Grafite',
    ];

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class)->orderBy('sequence');
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(Prize::class)->orderBy('sort_order');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class)->latest('id');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(Winner::class)->oldest('id');
    }

    public function cardTemplates(): HasMany
    {
        return $this->hasMany(CardTemplate::class)->latest('updated_at');
    }

    public function pdfExports(): HasMany
    {
        return $this->hasMany(PdfExport::class)->latest('id');
    }

    public function totalNumbers(): int
    {
        return $this->number_max - $this->number_min + 1;
    }

    public function letterFor(int $number): ?string
    {
        if (! $this->use_letters) {
            return null;
        }

        $perLetter = max(1, intdiv($this->totalNumbers(), count(self::LETTERS)));
        $index = min(count(self::LETTERS) - 1, intdiv(max(0, $number - $this->number_min), $perLetter));

        return self::LETTERS[$index];
    }

    public function isPlaying(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }
}

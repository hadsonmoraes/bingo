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

    /** Código impresso no canhoto destacável das cartelas. */
    public const STUB_CODE_TYPES = [
        'barcode' => 'Código de barras',
        'qrcode' => 'QR Code',
        'none' => 'Somente o número da cartela',
    ];

    protected $fillable = [
        'name',
        'number_min',
        'number_max',
        'use_letters',
        'letters_word',
        'card_format',
        'status',
        'started_at',
        'finished_at',
        'organization_name',
        'logo_path',
        'event_date',
        'card_price',
        'theme_color',
        'stub_code_type',
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

    /**
     * Tipo de código impresso no canhoto, normalizado (padrão: barcode).
     */
    public function stubCodeType(): string
    {
        return array_key_exists($this->stub_code_type, self::STUB_CODE_TYPES)
            ? $this->stub_code_type
            : 'barcode';
    }

    /**
     * Palavra exibida no lugar de "BINGO" (painel de números e
     * cabeçalho das grades das cartelas). Padrão: BINGO.
     */
    public function lettersWord(): string
    {
        return mb_strtoupper($this->letters_word ?: implode('', self::LETTERS));
    }

    /**
     * Letras do cabeçalho das grades — uma por faixa de números.
     */
    public function letters(): array
    {
        if (! $this->use_letters) {
            return [];
        }

        return mb_str_split($this->lettersWord());
    }

    public function letterFor(int $number): ?string
    {
        $letters = $this->letters();
        if ($letters === []) {
            return null;
        }

        $perLetter = max(1, intdiv($this->totalNumbers(), count($letters)));
        $index = min(count($letters) - 1, intdiv(max(0, $number - $this->number_min), $perLetter));

        return $letters[$index];
    }

    public function isPlaying(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }
}

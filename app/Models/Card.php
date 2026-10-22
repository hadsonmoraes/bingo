<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_SOLD = 'sold';

    public const STATUS_IN_PLAY = 'in_play';

    public const STATUS_WINNER = 'winner';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_AVAILABLE => 'Disponível',
        self::STATUS_SOLD => 'Vendida',
        self::STATUS_IN_PLAY => 'Em jogo',
        self::STATUS_WINNER => 'Vencedora',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    protected $fillable = [
        'game_id',
        'code',
        'grid',
        'status',
        'buyer_name',
        'buyer_phone',
        'batch',
        'printed_at',
        'prize_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'grid' => 'array',
            'prize_snapshot' => 'array',
            'printed_at' => 'datetime',
            'batch' => 'integer',
        ];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Normaliza a entrada usada para localizar uma cartela: aceita o
     * código puro (C-0001), o conteúdo do código de barras do canhoto
     * ou a URL completa do QR Code impresso.
     */
    public static function normalizeCodeInput(string $input): string
    {
        $code = trim($input);

        if (str_contains($code, '/')) {
            $path = parse_url($code, PHP_URL_PATH) ?: $code;
            $code = substr($path, (int) strrpos($path, '/') + 1);
        }

        return strtoupper(rawurldecode(trim($code)));
    }

    /**
     * Prêmios exibidos na cartela impressa: usa o snapshot capturado na
     * emissão, garantindo que cartelas já emitidas nunca mudem em silêncio.
     */
    public function prizesForPrint(): array
    {
        if (! empty($this->prize_snapshot)) {
            return $this->prize_snapshot;
        }

        return $this->game->prizes()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($prize) => [
                'name' => $prize->name,
                'description' => $prize->description,
                'estimated_value' => $prize->estimated_value,
                'rule_label' => $prize->ruleLabel(),
                'sort_order' => $prize->sort_order,
            ])->all();
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CardBlock::class)->orderBy('position');
    }

    public function winners(): HasMany
    {
        return $this->hasMany(Winner::class);
    }

    /**
     * Blocos numéricos da cartela: um bloco por prêmio registrado no
     * evento. Cartelas legadas (emitidas antes dos blocos) usam a grade
     * original como bloco único, preservando os números já emitidos.
     *
     * @return Collection<int, CardBlock>
     */
    public function effectiveBlocks(): Collection
    {
        // Usa a relação carregada (eager load) quando disponível — a
        // relação já vem ordenada por position.
        $blocks = $this->blocks;

        if ($blocks->isNotEmpty()) {
            return $blocks;
        }

        // Cartela legada sem blocos: a grade original vira o bloco 1,
        // associado ao primeiro prêmio do evento (quando existir).
        $prize = $this->game->prizes()->orderBy('sort_order')->orderBy('id')->first();

        $block = new CardBlock([
            'card_id' => $this->id,
            'prize_id' => $prize?->id,
            'prize_name' => $prize?->name ?? 'Bingo',
            'prize_rule_label' => $prize?->ruleLabel() ?? '',
            'rule' => $prize?->rule ?? '',
            'pattern' => $prize?->pattern,
            'position' => 1,
            'grid' => $this->grid,
            'status' => CardBlock::STATUS_ACTIVE,
        ]);

        return new Collection([$block]);
    }
}

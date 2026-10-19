<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Modelo de arte personalizada: a arte importada define a aparência
 * visual; os números, identificadores e prêmios continuam controlados
 * pelo sistema e são inseridos nas áreas mapeadas.
 */
class CardTemplate extends Model
{
    /** Áreas mapeáveis na arte (coordenadas em mm dentro do A4). */
    public const AREA_KEYS = ['header', 'code', 'prizes', 'buyer', 'stub'];

    protected $fillable = [
        'game_id',
        'name',
        'art_path',
        'art_mime',
        'areas',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'areas' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function artUrl(): string
    {
        return Storage::disk('public')->url($this->art_path);
    }

    public function artDataUri(): ?string
    {
        if (! Storage::disk('public')->exists($this->art_path)) {
            return null;
        }

        $extension = strtolower(pathinfo($this->art_path, PATHINFO_EXTENSION));
        $mime = in_array($extension, ['jpg', 'jpeg']) ? 'jpeg' : $extension;

        return 'data:image/'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($this->art_path));
    }

    /**
     * A arte pode ser embutida no PDF gerado pelo sistema (dompdf
     * suporta PNG/JPEG embutidos; SVG e PDF não são rasterizáveis aqui).
     */
    public function supportsPdfEmbed(): bool
    {
        return in_array($this->art_mime, ['image/png', 'image/jpeg', 'image/jpg']);
    }

    /**
     * Área mapeada normalizada: sempre um retângulo em mm dentro do A4.
     */
    public function area(string $key, int $index = 0): ?array
    {
        $areas = $this->areas ?? [];

        if ($key === 'blocks') {
            $block = $areas['blocks'][$index] ?? null;
        } else {
            $block = $areas[$key] ?? null;
        }

        if (! is_array($block)) {
            return null;
        }

        return [
            'x' => max(0, (float) ($block['x'] ?? 0)),
            'y' => max(0, (float) ($block['y'] ?? 0)),
            'w' => max(1, (float) ($block['w'] ?? 10)),
            'h' => max(1, (float) ($block['h'] ?? 10)),
        ];
    }

    /**
     * Verifica se todas as áreas necessárias têm espaço para a
     * quantidade de blocos/prêmios da cartela.
     */
    public function missingAreas(int $blocksCount): array
    {
        $missing = [];
        $areas = $this->areas ?? [];

        foreach (self::AREA_KEYS as $key) {
            if (empty($areas[$key])) {
                $missing[] = $key;
            }
        }

        $mappedBlocks = count($areas['blocks'] ?? []);
        if ($mappedBlocks < $blocksCount) {
            $missing[] = 'blocks ('.$mappedBlocks.' de '.$blocksCount.')';
        }

        return $missing;
    }
}

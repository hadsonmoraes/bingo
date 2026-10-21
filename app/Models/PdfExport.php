<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PdfExport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Na fila',
        self::STATUS_PROCESSING => 'Gerando…',
        self::STATUS_COMPLETED => 'Concluído',
        self::STATUS_FAILED => 'Falhou',
    ];

    protected $fillable = [
        'game_id',
        'status',
        'cards_count',
        'filters',
        'base_url',
        'file_path',
        'file_name',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'cards_count' => 'integer',
        ];
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Caminho do arquivo dentro do disco "local" (storage/app).
     */
    public static function storePath(string $filename): string
    {
        return 'pdf-exports/'.$filename;
    }

    public function deleteFile(): void
    {
        if ($this->file_path) {
            Storage::disk('local')->delete($this->file_path);
        }
    }
}

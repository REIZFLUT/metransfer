<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transfer_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 * @property int $received_bytes
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['original_name', 'path', 'mime_type', 'size'])]
class TransferFile extends Model
{
    protected $attributes = [
        'received_bytes' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'received_bytes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}

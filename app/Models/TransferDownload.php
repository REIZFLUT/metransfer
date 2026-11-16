<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transfer_id
 * @property int|null $transfer_file_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['transfer_file_id', 'ip_address', 'user_agent'])]
class TransferDownload extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * @return BelongsTo<TransferFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(TransferFile::class, 'transfer_file_id');
    }
}

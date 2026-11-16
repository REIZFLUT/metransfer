<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string|null $title
 * @property string|null $message
 * @property string|null $password
 * @property TransferStatus $status
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $published_at
 * @property int $download_count
 * @property bool $notify_on_download
 * @property CarbonImmutable|null $last_notified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'message', 'password', 'expires_at', 'notify_on_download'])]
#[Hidden(['password'])]
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'download_count' => 0,
        'notify_on_download' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (Transfer $transfer) {
            $transfer->token ??= Str::random(40);
        });

        static::deleting(function (Transfer $transfer) {
            Storage::disk(static::disk())->deleteDirectory($transfer->storageDirectory());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => TransferStatus::class,
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'last_notified_at' => 'datetime',
            'notify_on_download' => 'boolean',
            'download_count' => 'integer',
        ];
    }

    public static function disk(): string
    {
        return config('transfers.disk');
    }

    public function storageDirectory(): string
    {
        return 'transfers/'.$this->id;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TransferFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(TransferFile::class);
    }

    /**
     * @return HasMany<TransferRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(TransferRecipient::class);
    }

    /**
     * @return HasMany<TransferDownload, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(TransferDownload::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPublished(): bool
    {
        return $this->status === TransferStatus::Ready;
    }

    public function isAvailable(): bool
    {
        return $this->isPublished() && ! $this->isExpired();
    }

    public function isPasswordProtected(): bool
    {
        return $this->password !== null;
    }

    public function totalSize(): int
    {
        return (int) $this->files->sum('size');
    }

    public function publicUrl(): string
    {
        return route('public.transfers.show', $this->token);
    }

    public function displayTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }

        $count = $this->files->count();

        return match ($count) {
            0 => 'Transfer',
            1 => $this->files->first()->original_name,
            default => "{$count} Dateien",
        };
    }

    /**
     * @param  Builder<Transfer>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /**
     * @param  Builder<Transfer>  $query
     */
    public function scopeStaleDrafts(Builder $query): void
    {
        $query->where('status', TransferStatus::Draft)
            ->where('created_at', '<=', now()->subHours((int) config('transfers.draft_lifetime_hours')));
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'display_title' => $this->displayTitle(),
            'message' => $this->message,
            'status' => $this->status->value,
            'url' => $this->publicUrl(),
            'has_password' => $this->isPasswordProtected(),
            'notify_on_download' => $this->notify_on_download,
            'download_count' => $this->download_count,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'files_count' => $this->files->count(),
            'total_size' => $this->totalSize(),
            'files' => $this->whenLoaded('files', fn () => TransferFileResource::collection($this->files)->resolve($request)),
            'recipients' => $this->whenLoaded('recipients', fn () => $this->recipients->map(fn ($recipient) => [
                'id' => $recipient->id,
                'email' => $recipient->email,
                'sent_at' => $recipient->sent_at?->toIso8601String(),
            ])),
            'downloads' => $this->whenLoaded('downloads', fn () => $this->downloads->map(fn ($download) => [
                'id' => $download->id,
                'file_name' => $download->file?->original_name,
                'ip_address' => $download->ip_address,
                'user_agent' => $download->user_agent,
                'created_at' => $download->created_at?->toIso8601String(),
            ])),
        ];
    }
}

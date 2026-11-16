<?php

namespace App\Http\Resources;

use App\Models\TransferFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransferFile
 */
class TransferFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'size' => $this->size,
            'mime_type' => $this->mime_type,
            'received_bytes' => $this->received_bytes,
            'complete' => $this->isComplete(),
        ];
    }
}

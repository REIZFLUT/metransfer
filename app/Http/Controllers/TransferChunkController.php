<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Models\TransferFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TransferChunkController extends Controller
{
    /**
     * Append a raw chunk to a file. The client sends the byte offset of the
     * chunk in the "Upload-Offset" header; if it does not match the number
     * of bytes already received, a 409 with the expected offset is returned
     * so the client can resume from there.
     */
    public function __invoke(Request $request, Transfer $transfer, TransferFile $file): JsonResponse
    {
        Gate::authorize('update', $transfer);

        abort_if($transfer->isPublished(), 409, 'Der Transfer ist bereits veröffentlicht.');

        $offset = $request->header('Upload-Offset');

        if ($offset === null || ! ctype_digit((string) $offset)) {
            return response()->json(['message' => 'Header "Upload-Offset" fehlt oder ist ungültig.'], 400);
        }

        $offset = (int) $offset;
        $maxChunk = (int) config('transfers.chunk_size');
        $length = (int) $request->header('Content-Length', '0');

        if ($length <= 0 || $length > $maxChunk) {
            return response()->json(['message' => 'Ungültige Chunk-Größe.'], 413);
        }

        return Cache::lock('transfer-file-upload:'.$file->id, 120)->block(30, function () use ($request, $file, $offset, $length) {
            $file->refresh();

            if ($file->isComplete() || $offset !== $file->received_bytes) {
                return $this->state($file, 409);
            }

            if ($offset + $length > $file->size) {
                return response()->json(['message' => 'Chunk überschreitet die angekündigte Dateigröße.'], 422);
            }

            $path = Storage::disk(Transfer::disk())->path($file->path);
            $target = fopen($path, 'c');
            $input = $request->getContent(true);

            if ($target === false || ! is_resource($input)) {
                return response()->json(['message' => 'Datei konnte nicht geschrieben werden.'], 500);
            }

            try {
                fseek($target, $offset);
                $written = stream_copy_to_stream($input, $target, $length);
                // Drop anything a previously aborted chunk might have left behind.
                ftruncate($target, max(0, $offset + (int) $written));
            } finally {
                fclose($target);
            }

            if ($written !== $length) {
                return response()->json(['message' => 'Chunk unvollständig empfangen.', 'offset' => $file->received_bytes], 400);
            }

            $file->received_bytes = $offset + $written;

            if ($file->received_bytes >= $file->size) {
                $file->completed_at = now();
            }

            $file->save();

            return $this->state($file);
        });
    }

    protected function state(TransferFile $file, int $status = 200): JsonResponse
    {
        return response()->json([
            'offset' => $file->received_bytes,
            'complete' => $file->isComplete(),
        ], $status);
    }
}

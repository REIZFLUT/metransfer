<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transfers\StoreTransferFileRequest;
use App\Http\Resources\TransferFileResource;
use App\Models\Transfer;
use App\Models\TransferFile;
use App\Support\SecureDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransferFileController extends Controller
{
    /**
     * Register a file that is about to be uploaded in chunks.
     */
    public function store(StoreTransferFileRequest $request, Transfer $transfer): JsonResponse
    {
        abort_if($transfer->isPublished(), 409, 'Zu einem veröffentlichten Transfer können keine Dateien hinzugefügt werden.');

        $file = $transfer->files()->create([
            'original_name' => $this->sanitizeName($request->validated('name')),
            'mime_type' => $request->validated('mime_type'),
            'size' => (int) $request->validated('size'),
            'path' => $transfer->storageDirectory().'/'.Str::uuid()->toString(),
        ]);

        $disk = Storage::disk(Transfer::disk());
        $disk->put($file->path, '');

        if ($file->size === 0) {
            $file->forceFill(['completed_at' => now()])->save();
        }

        return response()->json((new TransferFileResource($file))->resolve($request), 201);
    }

    /**
     * Return the upload state of a file (used to resume interrupted uploads).
     */
    public function show(Request $request, Transfer $transfer, TransferFile $file): JsonResponse
    {
        Gate::authorize('view', $transfer);

        return response()->json((new TransferFileResource($file))->resolve($request));
    }

    public function destroy(Transfer $transfer, TransferFile $file): JsonResponse
    {
        Gate::authorize('update', $transfer);

        abort_if($transfer->isPublished(), 409);

        Storage::disk(Transfer::disk())->delete($file->path);
        $file->delete();

        return response()->json(status: 204);
    }

    public function download(Transfer $transfer, TransferFile $file): BinaryFileResponse
    {
        Gate::authorize('view', $transfer);

        abort_unless($file->isComplete(), 404);

        return SecureDownload::file(
            Storage::disk(Transfer::disk())->path($file->path),
            $file->original_name,
        );
    }

    protected function sanitizeName(string $name): string
    {
        // Strip any path components and control characters; keep unicode letters.
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = trim($name, " .\t\n\r\0\x0B");

        return $name !== '' ? Str::limit($name, 255, '') : 'datei';
    }
}

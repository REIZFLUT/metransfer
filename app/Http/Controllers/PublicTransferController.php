<?php

namespace App\Http\Controllers;

use App\Actions\Transfers\RecordTransferDownload;
use App\Http\Resources\TransferFileResource;
use App\Models\Transfer;
use App\Models\TransferFile;
use App\Support\SecureDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

class PublicTransferController extends Controller
{
    public function __construct(protected RecordTransferDownload $recordDownload) {}

    public function show(Request $request, string $token): Response
    {
        $transfer = $this->findTransfer($token);

        if (! $transfer || ! $transfer->isAvailable()) {
            return Inertia::render('public/unavailable')
                ->toResponse($request)
                ->setStatusCode($transfer ? 410 : 404);
        }

        if (! $this->isUnlocked($request, $transfer)) {
            return Inertia::render('public/password', [
                'token' => $transfer->token,
                'sender' => $transfer->user->name,
            ])->toResponse($request);
        }

        $transfer->load(['files' => fn ($query) => $query->orderBy('original_name'), 'user']);

        return Inertia::render('public/transfer', [
            'transfer' => [
                'token' => $transfer->token,
                'title' => $transfer->displayTitle(),
                'message' => $transfer->message,
                'sender' => $transfer->user->name,
                'expires_at' => $transfer->expires_at?->toIso8601String(),
                'total_size' => $transfer->totalSize(),
                'files' => TransferFileResource::collection($transfer->files)->resolve($request),
            ],
        ])->toResponse($request);
    }

    public function unlock(Request $request, string $token): RedirectResponse
    {
        $transfer = $this->findTransfer($token);

        abort_unless($transfer && $transfer->isAvailable(), 404);

        $request->validate(['password' => ['required', 'string']]);

        if (! $transfer->password || ! Hash::check($request->string('password'), $transfer->password)) {
            throw ValidationException::withMessages(['password' => 'Das Passwort ist nicht korrekt.']);
        }

        $request->session()->put($this->sessionKey($transfer), true);

        return to_route('public.transfers.show', $transfer->token);
    }

    public function download(Request $request, string $token, int $file): BinaryFileResponse
    {
        $transfer = $this->accessibleTransfer($request, $token);

        /** @var TransferFile $transferFile */
        $transferFile = $transfer->files()->whereKey($file)->whereNotNull('completed_at')->firstOrFail();

        $this->recordDownload->handle($transfer, $request, $transferFile);

        return SecureDownload::file(
            Storage::disk(Transfer::disk())->path($transferFile->path),
            $transferFile->original_name,
        );
    }

    public function zip(Request $request, string $token): StreamedResponse
    {
        $transfer = $this->accessibleTransfer($request, $token);
        $files = $transfer->files()->whereNotNull('completed_at')->orderBy('original_name')->get();

        abort_if($files->isEmpty(), 404);

        $this->recordDownload->handle($transfer, $request);

        $disk = Storage::disk(Transfer::disk());
        $zipName = Str::slug($transfer->displayTitle()) ?: 'transfer';

        return response()->streamDownload(function () use ($files, $disk) {
            @set_time_limit(0);

            $zip = new ZipStream(
                defaultCompressionMethod: CompressionMethod::STORE,
                sendHttpHeaders: false,
                flushOutput: true,
            );

            $used = [];

            foreach ($files as $file) {
                $zip->addFileFromPath(
                    fileName: $this->uniqueName($file->original_name, $used),
                    path: $disk->path($file->path),
                );
            }

            $zip->finish();
        }, $zipName.'.zip', [
            'Content-Type' => 'application/zip',
            'X-Accel-Buffering' => 'no',
            ...SecureDownload::headers(),
        ]);
    }

    protected function findTransfer(string $token): ?Transfer
    {
        return Transfer::query()->with('user')->where('token', $token)->first();
    }

    protected function accessibleTransfer(Request $request, string $token): Transfer
    {
        $transfer = $this->findTransfer($token);

        abort_unless($transfer && $transfer->isAvailable(), 404);
        abort_unless($this->isUnlocked($request, $transfer), 403);

        return $transfer;
    }

    protected function isUnlocked(Request $request, Transfer $transfer): bool
    {
        return ! $transfer->isPasswordProtected()
            || $request->session()->get($this->sessionKey($transfer)) === true
            || $request->user()?->id === $transfer->user_id;
    }

    protected function sessionKey(Transfer $transfer): string
    {
        return 'transfers.unlocked.'.$transfer->id;
    }

    /**
     * @param  array<string, true>  $used
     */
    protected function uniqueName(string $name, array &$used): string
    {
        $candidate = $name;
        $info = pathinfo($name);
        $counter = 2;

        while (isset($used[mb_strtolower($candidate)])) {
            $candidate = $info['filename'].' ('.$counter++.')'.(isset($info['extension']) ? '.'.$info['extension'] : '');
        }

        $used[mb_strtolower($candidate)] = true;

        return $candidate;
    }
}

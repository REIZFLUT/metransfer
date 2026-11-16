<?php

namespace App\Http\Controllers;

use App\Actions\Transfers\SendTransferToRecipients;
use App\Enums\TransferStatus;
use App\Http\Requests\Transfers\PublishTransferRequest;
use App\Http\Requests\Transfers\UpdateTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(Request $request): Response
    {
        $transfers = $request->user()->transfers()
            ->where('status', TransferStatus::Ready)
            ->with('files')
            ->latest('published_at')
            ->get();

        $all = $request->user()->transfers()->where('status', TransferStatus::Ready);

        return Inertia::render('dashboard', [
            'transfers' => TransferResource::collection($transfers)->resolve($request),
            'stats' => [
                'active' => (clone $all)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
                'downloads' => (int) (clone $all)->sum('download_count'),
                'storage' => (int) $request->user()->transfers()
                    ->join('transfer_files', 'transfer_files.transfer_id', '=', 'transfers.id')
                    ->sum('transfer_files.received_bytes'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('transfers/create', [
            'chunkSize' => (int) config('transfers.chunk_size'),
            'maxFileSize' => (int) config('transfers.max_file_size'),
            'expiryOptions' => config('transfers.expiry_options'),
            'defaultExpiry' => (int) config('transfers.default_expiry_days'),
        ]);
    }

    /**
     * Create an empty draft transfer that files can be uploaded into.
     */
    public function store(Request $request): JsonResponse
    {
        $transfer = $request->user()->transfers()->create();

        return response()->json([
            'id' => $transfer->id,
            'chunk_size' => (int) config('transfers.chunk_size'),
        ], 201);
    }

    public function show(Request $request, Transfer $transfer): Response
    {
        Gate::authorize('view', $transfer);

        abort_unless($transfer->isPublished(), 404);

        $transfer->load([
            'files' => fn ($query) => $query->orderBy('original_name'),
            'recipients',
            'downloads' => fn ($query) => $query->with('file')->latest('id')->limit(100),
        ]);

        return Inertia::render('transfers/show', [
            'transfer' => (new TransferResource($transfer))->resolve($request),
            'expiryOptions' => config('transfers.expiry_options'),
        ]);
    }

    public function publish(PublishTransferRequest $request, Transfer $transfer, SendTransferToRecipients $sendToRecipients): RedirectResponse
    {
        if ($transfer->isPublished()) {
            return to_route('transfers.show', $transfer);
        }

        $files = $transfer->files()->get();

        if ($files->isEmpty() || $files->contains(fn ($file) => ! $file->isComplete())) {
            throw ValidationException::withMessages([
                'files' => 'Es wurden noch nicht alle Dateien vollständig hochgeladen.',
            ]);
        }

        $days = (int) $request->integer('expires_in_days');

        $transfer->fill([
            'title' => $request->validated('title'),
            'message' => $request->validated('message'),
            'password' => $request->validated('password'),
            'notify_on_download' => $request->boolean('notify_on_download', true),
            'expires_at' => $days > 0 ? now()->addDays($days) : null,
        ]);
        $transfer->status = TransferStatus::Ready;
        $transfer->published_at = now();
        $transfer->save();

        $sent = $sendToRecipients->handle($transfer, $request->validated('recipients', []));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $sent > 0
                ? "Transfer erstellt und an {$sent} Empfänger verschickt."
                : 'Transfer erstellt. Link kopieren und teilen!',
        ]);

        return to_route('transfers.show', $transfer);
    }

    public function update(UpdateTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $data = $request->safe()->only(['title', 'message', 'notify_on_download']);

        if ($request->has('expires_in_days')) {
            $days = $request->integer('expires_in_days');
            $data['expires_at'] = $days > 0 ? now()->addDays($days) : null;
        }

        if ($request->boolean('remove_password')) {
            $data['password'] = null;
        } elseif ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $transfer->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer aktualisiert.']);

        return back();
    }

    public function destroy(Request $request, Transfer $transfer): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $transfer);

        $transfer->delete();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(status: 204);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Transfer gelöscht.']);

        return to_route('dashboard');
    }
}

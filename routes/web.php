<?php

use App\Http\Controllers\PublicTransferController;
use App\Http\Controllers\TransferChunkController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TransferFileController;
use App\Http\Controllers\TransferRecipientController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [TransferController::class, 'index'])->name('dashboard');

    Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
    Route::patch('transfers/{transfer}', [TransferController::class, 'update'])->name('transfers.update');
    Route::delete('transfers/{transfer}', [TransferController::class, 'destroy'])->name('transfers.destroy');
    Route::post('transfers/{transfer}/publish', [TransferController::class, 'publish'])->name('transfers.publish');

    Route::post('transfers/{transfer}/recipients', [TransferRecipientController::class, 'store'])->name('transfers.recipients.store');

    Route::scopeBindings()->group(function () {
        Route::post('transfers/{transfer}/files', [TransferFileController::class, 'store'])->name('transfers.files.store');
        Route::get('transfers/{transfer}/files/{file}', [TransferFileController::class, 'show'])->name('transfers.files.show');
        Route::delete('transfers/{transfer}/files/{file}', [TransferFileController::class, 'destroy'])->name('transfers.files.destroy');
        Route::get('transfers/{transfer}/files/{file}/download', [TransferFileController::class, 'download'])->name('transfers.files.download');
        Route::post('transfers/{transfer}/files/{file}/chunks', TransferChunkController::class)->name('transfers.files.chunks.store');
    });
});

Route::prefix('t/{token}')->name('public.transfers.')->group(function () {
    Route::get('/', [PublicTransferController::class, 'show'])->name('show');
    Route::post('unlock', [PublicTransferController::class, 'unlock'])->middleware('throttle:transfer-unlock')->name('unlock');
    Route::get('zip', [PublicTransferController::class, 'zip'])->name('zip');
    Route::get('files/{file}', [PublicTransferController::class, 'download'])->whereNumber('file')->name('download');
});

require __DIR__.'/settings.php';

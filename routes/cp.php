<?php

use Reach\StatamicResrvVouchers\Http\Controllers\VoucherCpController;

Route::middleware('can:use resrv')
    ->name('resrv-vouchers.')
    ->prefix('resrv-vouchers')
    ->group(function () {
        Route::get('/', [VoucherCpController::class, 'indexCp'])->name('index');
        Route::get('/list', [VoucherCpController::class, 'index'])->name('index.json');
        Route::get('/scan', [VoucherCpController::class, 'scanCp'])->name('scan');
        Route::post('/lookup', [VoucherCpController::class, 'lookup'])->name('lookup');
        Route::patch('/mark-used', [VoucherCpController::class, 'markUsed'])->name('mark-used');
        Route::patch('/un-mark', [VoucherCpController::class, 'unMark'])->name('un-mark');
        Route::post('/resend/{voucher}', [VoucherCpController::class, 'resend'])->name('resend');
    });

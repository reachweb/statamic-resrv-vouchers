<?php

use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Models\VoucherScan;

it('generates a uuid id on create', function () {
    $voucher = Voucher::create([
        'reservation_id' => 1,
        'token' => 'token-1',
        'status' => VoucherStatus::Issued,
        'expires_at' => now()->addDay(),
    ]);

    expect($voucher->id)->toBeString()
        ->and(strlen($voucher->id))->toBe(36);
});

it('preserves a manually assigned id', function () {
    $voucher = Voucher::create([
        'id' => '11111111-1111-1111-1111-111111111111',
        'reservation_id' => 2,
        'token' => 'token-2',
        'status' => VoucherStatus::Issued,
        'expires_at' => now()->addDay(),
    ]);

    expect($voucher->id)->toBe('11111111-1111-1111-1111-111111111111');
});

it('casts status to VoucherStatus enum', function () {
    $voucher = Voucher::create([
        'reservation_id' => 3,
        'token' => 'token-3',
        'status' => 'used',
        'expires_at' => now()->addDay(),
        'used_at' => now(),
    ]);

    expect($voucher->fresh()->status)->toBe(VoucherStatus::Used);
});

it('reports expired when expires_at is in the past', function () {
    $voucher = Voucher::create([
        'reservation_id' => 4,
        'token' => 'token-4',
        'status' => VoucherStatus::Issued,
        'expires_at' => now()->subSecond(),
    ]);

    expect($voucher->isExpired())->toBeTrue();
});

it('reports not expired when expires_at is in the future', function () {
    $voucher = Voucher::create([
        'reservation_id' => 5,
        'token' => 'token-5',
        'status' => VoucherStatus::Issued,
        'expires_at' => now()->addDay(),
    ]);

    expect($voucher->isExpired())->toBeFalse();
});

it('relates to its scans', function () {
    $voucher = Voucher::create([
        'reservation_id' => 6,
        'token' => 'token-6',
        'status' => VoucherStatus::Issued,
        'expires_at' => now()->addDay(),
    ]);

    VoucherScan::create([
        'voucher_id' => $voucher->id,
        'action' => 'scan',
        'result' => 'success',
    ]);

    expect($voucher->scans)->toHaveCount(1);
});

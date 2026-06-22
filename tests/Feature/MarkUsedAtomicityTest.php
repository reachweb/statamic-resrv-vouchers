<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Exceptions\InvalidVoucherTransitionException;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\VoucherStateMachine;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
});

it('rejects a second redemption when the row was already marked used concurrently', function () {
    Mail::fake();

    $voucher = $this->makeIssuedVoucher();

    // Simulate the winning scan: the DB row is already Used, while this request still holds a
    // stale model that reads Issued (so guardTransition passes and only the conditional UPDATE
    // can catch the race).
    Voucher::query()->whereKey($voucher->id)->update([
        'status' => VoucherStatus::Used->value,
        'used_at' => now(),
        'used_by_user_id' => 'first-scanner',
    ]);

    expect($voucher->status)->toBe(VoucherStatus::Issued);

    expect(fn () => app(VoucherStateMachine::class)->markUsed($voucher, 'second-scanner'))
        ->toThrow(InvalidVoucherTransitionException::class);

    // The first redeemer's audit record is preserved, not overwritten by the loser.
    expect($voucher->fresh()->used_by_user_id)->toBe('first-scanner');

    // The losing request must not dispatch VoucherUsed, so no duplicate attended email.
    Mail::assertNothingQueued();
});

it('names the real current status when the row was concurrently invalidated', function () {
    $voucher = $this->makeIssuedVoucher();

    // A cancel/refund invalidated the row after this request loaded it as Issued.
    Voucher::query()->whereKey($voucher->id)->update([
        'status' => VoucherStatus::Invalidated->value,
        'invalidated_reason' => 'cancelled',
    ]);

    expect(fn () => app(VoucherStateMachine::class)->markUsed($voucher, 'scanner'))
        ->toThrow(InvalidVoucherTransitionException::class, 'Cannot transition voucher from invalidated to used.');
});

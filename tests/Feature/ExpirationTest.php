<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Exceptions\InvalidVoucherTransitionException;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\VoucherStateMachine;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
});

it('reports an Issued voucher as Expired without mutating the database when expires_at has passed', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    $voucher->forceFill(['expires_at' => now()->subDay()])->save();

    $stateMachine = app(VoucherStateMachine::class);

    expect($stateMachine->statusOf($voucher))->toBe(VoucherStatus::Expired);

    $fresh = Voucher::find($voucher->id);
    expect($fresh->status)->toBe(VoucherStatus::Issued);
});

it('keeps Issued status when expires_at is in the future', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();

    expect(app(VoucherStateMachine::class)->statusOf($voucher))->toBe(VoucherStatus::Issued);
});

it('refuses to mark a lazy-expired voucher as used', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    $voucher->forceFill(['expires_at' => now()->subDay()])->save();

    expect(fn () => app(VoucherStateMachine::class)->markUsed($voucher->fresh(), 'admin'))
        ->toThrow(InvalidVoucherTransitionException::class);

    expect($voucher->fresh()->status)->toBe(VoucherStatus::Issued);
    expect($voucher->fresh()->used_at)->toBeNull();
});

it('allows invalidating a lazy-expired voucher', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    $voucher->forceFill(['expires_at' => now()->subDay()])->save();

    app(VoucherStateMachine::class)->invalidate($voucher->fresh(), 'manual-cleanup');

    $voucher = $voucher->fresh();
    expect($voucher->status)->toBe(VoucherStatus::Invalidated);
    expect($voucher->invalidated_reason)->toBe('manual-cleanup');
});

it('refuses to un-mark a voucher that was never marked used', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();

    expect(fn () => app(VoucherStateMachine::class)->unMark($voucher->fresh(), 'admin'))
        ->toThrow(InvalidVoucherTransitionException::class);
});

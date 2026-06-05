<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Reach\StatamicResrv\Events\ReservationCancelled;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrv\Events\ReservationExpired;
use Reach\StatamicResrv\Events\ReservationRefunded;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Events\VoucherInvalidated;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\VoucherStateMachine;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
});

it('invalidates the voucher when the reservation is cancelled', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    Event::fake([VoucherInvalidated::class]);

    ReservationCancelled::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    expect($voucher->status)->toBe(VoucherStatus::Invalidated);
    expect($voucher->invalidated_reason)->toBe('cancelled');

    Event::assertDispatched(VoucherInvalidated::class);
});

it('invalidates the voucher when the reservation is refunded', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    ReservationRefunded::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    expect($voucher->status)->toBe(VoucherStatus::Invalidated);
    expect($voucher->invalidated_reason)->toBe('refunded');
});

it('invalidates the voucher when the reservation expires', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    ReservationExpired::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    expect($voucher->status)->toBe(VoucherStatus::Invalidated);
    expect($voucher->invalidated_reason)->toBe('expired-reservation');
});

it('does nothing if no voucher exists for the reservation', function () {
    Config::set('resrv-vouchers.enabled_collections', []);

    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->count())->toBe(0);

    ReservationCancelled::dispatch($reservation);

    expect(Voucher::query()->count())->toBe(0);
});

it('keeps a used voucher used when the reservation is refunded', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    app(VoucherStateMachine::class)->markUsed($voucher->fresh(), 'admin-id');

    expect($voucher->fresh()->status)->toBe(VoucherStatus::Used);

    // The reservation was refunded after the voucher had already been used —
    // we deliberately preserve the Used status (the customer attended).
    ReservationRefunded::dispatch($reservation);

    $voucher = $voucher->fresh();
    expect($voucher->status)->toBe(VoucherStatus::Used);
    expect($voucher->invalidated_reason)->toBeNull();
});

it('does not change an already-invalidated voucher on a second cancellation event', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    ReservationCancelled::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();
    expect($voucher->status)->toBe(VoucherStatus::Invalidated);
    expect($voucher->invalidated_reason)->toBe('cancelled');

    ReservationRefunded::dispatch($reservation);

    $voucher = $voucher->fresh();
    expect($voucher->status)->toBe(VoucherStatus::Invalidated);
    expect($voucher->invalidated_reason)->toBe('cancelled');
});

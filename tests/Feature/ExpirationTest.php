<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
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

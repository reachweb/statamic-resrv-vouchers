<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
    Config::set('resrv-vouchers.grace_days', 1);
});

it('creates a voucher when a confirmed reservation belongs to an enabled collection', function () {
    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->status)->toBe(VoucherStatus::Issued);
    expect(app(VoucherTokenSigner::class)->verify($voucher->token))->toBe($voucher->id);
});

it('is idempotent — re-firing the event does not create a duplicate voucher', function () {
    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);
    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->where('reservation_id', $reservation->id)->count())->toBe(1);
});

it('does not create a voucher when the collection is not enabled', function () {
    Config::set('resrv-vouchers.enabled_collections', ['other-collection']);

    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->count())->toBe(0);
});

it('sets expires_at to reservation.date_end + grace_days', function () {
    Config::set('resrv-vouchers.grace_days', 2);

    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();

    expect($voucher->expires_at->equalTo($reservation->date_end->copy()->addDays(2)))->toBeTrue();
});

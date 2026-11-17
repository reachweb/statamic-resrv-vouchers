<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Reach\StatamicResrv\Enums\ReservationStatus;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrv\Models\Reservation;
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
    Log::spy();
    Config::set('resrv-vouchers.enabled_collections', ['other-collection']);

    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->count())->toBe(0);
    Log::shouldNotHaveReceived('error');
});

it('sets expires_at to reservation.date_end + grace_days', function () {
    Config::set('resrv-vouchers.grace_days', 2);

    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();

    expect($voucher->expires_at->equalTo($reservation->date_end->copy()->addDays(2)))->toBeTrue();
});

it('does not issue a voucher once the reservation row is no longer live', function () {
    // The event carries a copy loaded before a concurrent cancel committed: the row decides.
    $reservation = $this->makeConfirmedReservation();
    Reservation::query()->whereKey($reservation->id)->update(['status' => ReservationStatus::CANCELLED->value]);

    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->count())->toBe(0);
});

it('issues the voucher while the event is dispatched, without waiting for a queue worker', function () {
    // Resrv builds the confirmation email right after the response; a queued voucher would miss it.
    Queue::fake();

    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->where('reservation_id', $reservation->id)->exists())->toBeTrue();
});

it('never lets a generation failure escape into Resrv\'s confirmation flow, and logs it', function () {
    Log::spy();
    // A malformed key makes building the generator throw, so this also fails if the generator
    // is injected into the listener, where the container builds it outside handle()'s try.
    Config::set('resrv-vouchers.signing_key', 'base64:!!!');
    $this->app->forgetInstance(VoucherTokenSigner::class);

    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->count())->toBe(0);
    Log::shouldHaveReceived('error')
        ->with('Failed to generate voucher for reservation', Mockery::on(fn ($context) => $context['reservation_id'] === $reservation->id))
        ->once();
});

<?php

use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\QrRenderer;

it('renders a png starting with the png signature', function () {
    $bytes = (new QrRenderer)->png('hello-world');

    expect($bytes)->not->toBeEmpty()
        ->and(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1A\n");
});

it('renders a pdf starting with the %PDF- header', function () {
    $reservation = $this->makeConfirmedReservation();

    $voucher = Voucher::create([
        'reservation_id' => $reservation->id,
        'token' => 'pdf-token',
        'status' => VoucherStatus::Issued,
        'expires_at' => now()->addDay(),
    ]);

    $bytes = (new QrRenderer)->pdf($voucher);

    expect($bytes)->not->toBeEmpty()
        ->and(substr($bytes, 0, 5))->toBe('%PDF-');
});

<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrv\Mail\ReservationConfirmed as ReservationConfirmedMail;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\QrRenderer;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
});

it('attaches a PDF voucher and embeds the QR PNG when the confirmation email builds', function () {
    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->firstOrFail();

    $mailable = new ReservationConfirmedMail($reservation);
    $mailable->build();

    $hasPdf = collect($mailable->rawAttachments)->contains(
        fn ($attachment) => ($attachment['name'] ?? null) === "voucher-{$voucher->id}.pdf"
            && str_starts_with($attachment['data'] ?? '', '%PDF-')
            && ($attachment['options']['mime'] ?? null) === 'application/pdf'
    );

    expect($hasPdf)->toBeTrue();

    $email = new Email;
    foreach ($mailable->callbacks as $callback) {
        $callback($email);
    }

    $cids = collect($email->getAttachments())->map(function ($part) {
        $headers = $part->getPreparedHeaders();

        return [
            'content_id' => $headers->getHeaderBody('Content-ID'),
            'disposition' => $headers->getHeaderBody('Content-Disposition'),
            'filename' => $part->getFilename(),
        ];
    })->all();

    $hasCid = collect($cids)->contains(
        fn ($entry) => $entry['filename'] === 'voucher-qr' || $entry['content_id'] === 'voucher-qr'
    );

    expect($hasCid)->toBeTrue();
});

it('still sends the confirmation email when voucher rendering fails', function () {
    $reservation = $this->makeConfirmedReservation();
    ReservationConfirmed::dispatch($reservation);

    // A rendering failure must never abort Resrv's confirmation email.
    $this->app->bind(QrRenderer::class, fn () => new class extends QrRenderer
    {
        public function png(string $payload, int $size = 320): string
        {
            throw new \RuntimeException('render boom');
        }
    });

    $mailable = new ReservationConfirmedMail($reservation);
    $mailable->build();

    expect($mailable->rawAttachments)->toBeEmpty();
});

it('sends the email without a voucher attachment when collection is not enabled', function () {
    Config::set('resrv-vouchers.enabled_collections', ['other-collection']);

    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->count())->toBe(0);

    $mailable = new ReservationConfirmedMail($reservation);
    $mailable->build();

    expect($mailable->rawAttachments)->toBeEmpty();
});

<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Reach\StatamicResrv\Enums\ReservationStatus;
use Reach\StatamicResrv\Events\BuildingReservationEmail;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrv\Mail\ReservationConfirmed as ReservationConfirmedMail;
use Reach\StatamicResrv\Mail\ReservationPaymentRequest;
use Reach\StatamicResrv\Models\Reservation;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\QrRenderer;
use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;
use Symfony\Component\Mime\Email;

// By path: a published copy of the snippet must not stand in for the package's own.
const QR_SNIPPET_PATH = __DIR__.'/../../resources/views/email/vouchers/partials/qr.blade.php';

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

    expect(view()->file(QR_SNIPPET_PATH, $mailable->buildViewData())->render())->toContain('src="cid:voucher-qr"');
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

    expect($mailable->rawAttachments)->toBeEmpty()
        ->and(view()->file(QR_SNIPPET_PATH, $mailable->buildViewData())->render())->not->toContain('<img');
});

it('sends the email without a voucher attachment when collection is not enabled', function () {
    Config::set('resrv-vouchers.enabled_collections', ['other-collection']);

    $reservation = $this->makeConfirmedReservation();

    ReservationConfirmed::dispatch($reservation);

    expect(Voucher::query()->count())->toBe(0);

    $mailable = new ReservationConfirmedMail($reservation);
    $mailable->build();

    expect($mailable->rawAttachments)->toBeEmpty()
        ->and(view()->file(QR_SNIPPET_PATH, $mailable->buildViewData())->render())->not->toContain('<img');
});

it('does not attach the voucher to other Resrv emails that fire the build hook', function () {
    $voucher = $this->makeIssuedVoucher();

    $mailable = new ReservationPaymentRequest($voucher->reservation);
    BuildingReservationEmail::dispatch($mailable, $voucher->reservation);

    expect($mailable->rawAttachments)->toBeEmpty()
        ->and($mailable->callbacks)->toBeEmpty();
});

it('issues a missing voucher when the confirmation email builds', function () {
    // No ReservationConfirmed dispatch: as if issuing it at confirmation had failed.
    $reservation = $this->makeConfirmedReservation();

    $mailable = new ReservationConfirmedMail($reservation);
    $mailable->build();

    $voucher = Voucher::query()->where('reservation_id', $reservation->id)->first();

    expect($voucher)->not->toBeNull()
        ->and(collect($mailable->rawAttachments)->pluck('name')->all())->toBe(["voucher-{$voucher->id}.pdf"]);
});

it('does not issue a missing voucher for a booking that is no longer live', function () {
    // The email holds a copy loaded before a concurrent cancel committed: the row decides.
    $reservation = $this->makeConfirmedReservation();
    Reservation::query()->whereKey($reservation->id)->update(['status' => ReservationStatus::CANCELLED->value]);

    $mailable = new ReservationConfirmedMail($reservation);
    $mailable->build();

    expect(Voucher::query()->count())->toBe(0)
        ->and($mailable->rawAttachments)->toBeEmpty();
});

it('still sends the confirmation email when a missing voucher cannot be issued', function () {
    Config::set('resrv-vouchers.signing_key', 'base64:!!!');
    $this->app->forgetInstance(VoucherTokenSigner::class);

    $reservation = $this->makeConfirmedReservation();

    $mailable = new ReservationConfirmedMail($reservation);
    $mailable->build();

    expect($mailable->rawAttachments)->toBeEmpty()
        ->and(view()->file(QR_SNIPPET_PATH, $mailable->buildViewData())->render())->not->toContain('<img');
});

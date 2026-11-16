<?php

namespace Reach\StatamicResrvVouchers\Listeners;

use Illuminate\Support\Facades\Log;
use Reach\StatamicResrv\Events\BuildingReservationEmail;
use Reach\StatamicResrv\Mail\ReservationConfirmed;
use Reach\StatamicResrv\Models\Reservation;
use Reach\StatamicResrvVouchers\Exceptions\ShouldNotIssueVoucherException;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\QrRenderer;
use Reach\StatamicResrvVouchers\Services\VoucherGenerator;
use Symfony\Component\Mime\Email;
use Throwable;

class AttachVoucherToReservationEmail
{
    public function __construct(private readonly QrRenderer $renderer) {}

    public function handle(BuildingReservationEmail $event): void
    {
        // Resrv fires this hook for other emails too (e.g. the payment request); the voucher
        // belongs only in the confirmation.
        if (! $event->mailable instanceof ReservationConfirmed || ! $event->reservation) {
            return;
        }

        $voucher = null;

        // This runs synchronously inside Resrv's email build. A failure must never abort the
        // confirmation email — log it and let the email go out without the voucher.
        try {
            $voucher = Voucher::query()->where('reservation_id', $event->reservation->id)->first()
                ?? $this->issueMissingVoucher($event->reservation);

            if (! $voucher) {
                return;
            }

            $pngBytes = $this->renderer->png($voucher->token);
            $pdfBytes = $this->renderer->pdf($voucher, $pngBytes);

            $event->mailable->attachData(
                $pdfBytes,
                "voucher-{$voucher->id}.pdf",
                ['mime' => 'application/pdf'],
            );

            $event->mailable->withSymfonyMessage(function (Email $email) use ($pngBytes) {
                $email->embed($pngBytes, 'voucher-qr', 'image/png');
            });

            // The QR snippet renders only when this is set, so it never prints a broken image.
            $event->mailable->with('voucherQrEmbedded', true);
        } catch (Throwable $e) {
            Log::error('Failed to attach voucher to reservation email', [
                'voucher_id' => $voucher?->id,
                'reservation_id' => $event->reservation->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    // Issuing the voucher at confirmation can fail; any later confirmation email (e.g. Resrv's
    // "Resend confirmation") then issues it, unless the booking is no longer live — a fresh,
    // valid QR for a cancelled booking would scan as usable. Resolved here, not injected:
    // building the generator can throw (a bad signing key), which must stay inside the try.
    private function issueMissingVoucher(Reservation $reservation): ?Voucher
    {
        if (! $reservation->isLive()) {
            return null;
        }

        try {
            return app(VoucherGenerator::class)->generateFor($reservation);
        } catch (ShouldNotIssueVoucherException) {
            return null;
        }
    }
}

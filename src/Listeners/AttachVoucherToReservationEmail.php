<?php

namespace Reach\StatamicResrvVouchers\Listeners;

use Illuminate\Support\Facades\Log;
use Reach\StatamicResrv\Events\BuildingReservationEmail;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\QrRenderer;
use Symfony\Component\Mime\Email;
use Throwable;

class AttachVoucherToReservationEmail
{
    public function __construct(private readonly QrRenderer $renderer) {}

    public function handle(BuildingReservationEmail $event): void
    {
        if (! $event->reservation) {
            return;
        }

        $voucher = Voucher::query()->where('reservation_id', $event->reservation->id)->first();

        if (! $voucher) {
            return;
        }

        // This runs synchronously inside Resrv's email build. A rendering failure must never
        // abort the confirmation email — log it and let the email go out without the voucher.
        try {
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
        } catch (Throwable $e) {
            Log::error('Failed to attach voucher to reservation email', [
                'voucher_id' => $voucher->id,
                'reservation_id' => $event->reservation->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

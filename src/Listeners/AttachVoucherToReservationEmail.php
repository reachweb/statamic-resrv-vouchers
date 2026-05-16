<?php

namespace Reach\StatamicResrvVouchers\Listeners;

use Reach\StatamicResrv\Events\BuildingReservationEmail;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\QrRenderer;
use Symfony\Component\Mime\Email;

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
    }
}

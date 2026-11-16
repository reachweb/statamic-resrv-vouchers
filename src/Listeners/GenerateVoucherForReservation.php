<?php

namespace Reach\StatamicResrvVouchers\Listeners;

use Illuminate\Support\Facades\Log;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrvVouchers\Exceptions\ShouldNotIssueVoucherException;
use Reach\StatamicResrvVouchers\Services\VoucherGenerator;
use Throwable;

// Synchronous on purpose: Resrv sends the confirmation email right after the response, before a
// queue worker would have issued a queued voucher, so the email went out without it. This runs
// inside Resrv's confirmation flows (payment webhooks included), so a failure is logged, never thrown.
// The generator is resolved inside the try, not injected: building it can throw (a bad signing key).
class GenerateVoucherForReservation
{
    public function handle(ReservationConfirmed $event): void
    {
        try {
            app(VoucherGenerator::class)->generateFor($event->reservation);
        } catch (ShouldNotIssueVoucherException) {
        } catch (Throwable $e) {
            Log::error('Failed to generate voucher for reservation', [
                'reservation_id' => $event->reservation->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

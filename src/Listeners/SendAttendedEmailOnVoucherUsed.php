<?php

namespace Reach\StatamicResrvVouchers\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Reach\StatamicResrvVouchers\Events\VoucherUsed;
use Reach\StatamicResrvVouchers\Mail\VoucherAttended;

class SendAttendedEmailOnVoucherUsed implements ShouldQueue
{
    public function handle(VoucherUsed $event): void
    {
        $email = $event->voucher->reservation?->customer?->email;

        if (! is_string($email) || $email === '') {
            return;
        }

        Mail::to($email)->queue(new VoucherAttended($event->voucher));
    }
}

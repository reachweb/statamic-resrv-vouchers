<?php

namespace Reach\StatamicResrvVouchers\Listeners;

use Reach\StatamicResrv\Events\ReservationCancelled;
use Reach\StatamicResrv\Events\ReservationExpired;
use Reach\StatamicResrv\Events\ReservationRefunded;
use Reach\StatamicResrvVouchers\Exceptions\InvalidVoucherTransitionException;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Reach\StatamicResrvVouchers\Services\VoucherStateMachine;

class InvalidateVoucherOnCancellation
{
    public function __construct(private readonly VoucherStateMachine $stateMachine) {}

    public function handle(ReservationCancelled|ReservationRefunded|ReservationExpired $event): void
    {
        $voucher = Voucher::query()->where('reservation_id', $event->reservation->id)->first();

        if (! $voucher) {
            return;
        }

        try {
            $this->stateMachine->invalidate($voucher, $this->reasonFor($event));
        } catch (InvalidVoucherTransitionException) {
        }
    }

    private function reasonFor(ReservationCancelled|ReservationRefunded|ReservationExpired $event): string
    {
        return match (true) {
            $event instanceof ReservationCancelled => 'cancelled',
            $event instanceof ReservationRefunded => 'refunded',
            $event instanceof ReservationExpired => 'expired-reservation',
        };
    }
}

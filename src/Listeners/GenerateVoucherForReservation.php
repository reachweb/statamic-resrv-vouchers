<?php

namespace Reach\StatamicResrvVouchers\Listeners;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrvVouchers\Exceptions\ShouldNotIssueVoucherException;
use Reach\StatamicResrvVouchers\Services\VoucherGenerator;
use Throwable;

class GenerateVoucherForReservation implements ShouldBeUnique, ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(private readonly VoucherGenerator $generator) {}

    public function handle(ReservationConfirmed $event): void
    {
        try {
            $this->generator->generateFor($event->reservation);
        } catch (ShouldNotIssueVoucherException) {
        }
    }

    public function uniqueId(ReservationConfirmed $event): string
    {
        return (string) $event->reservation->id;
    }

    public function failed(ReservationConfirmed $event, Throwable $exception): void
    {
        Log::error('Failed to generate voucher for reservation', [
            'reservation_id' => $event->reservation->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}

<?php

namespace Reach\StatamicResrvVouchers\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Reach\StatamicResrv\Enums\ReservationStatus;
use Reach\StatamicResrv\Models\Entry;
use Reach\StatamicResrv\Models\Reservation;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Exceptions\ShouldNotIssueVoucherException;
use Reach\StatamicResrvVouchers\Models\Voucher;

class VoucherGenerator
{
    public function __construct(private readonly VoucherTokenSigner $signer) {}

    public function generateFor(Reservation $reservation): Voucher
    {
        $this->ensureEligible($reservation);

        // Under the row lock Resrv's transitionTo() takes: a cancel or refund either commits first
        // and is seen here, or waits for this insert, so InvalidateVoucherOnCancellation finds the
        // voucher. The caller's copy may predate the cancel, so only the row decides.
        return DB::transaction(function () use ($reservation) {
            $status = Reservation::query()->lockForUpdate()->whereKey($reservation->id)->value('status');

            if (! in_array($status, ReservationStatus::live(), true)) {
                throw new ShouldNotIssueVoucherException("Reservation {$reservation->id} is no longer live.");
            }

            $id = (string) Str::uuid();

            return Voucher::firstOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'id' => $id,
                    'token' => $this->signer->sign($id),
                    'status' => VoucherStatus::Issued,
                    'expires_at' => $this->expiresAt($reservation),
                ],
            );
        });
    }

    private function ensureEligible(Reservation $reservation): void
    {
        $collection = Entry::query()
            ->where('item_id', $reservation->item_id)
            ->value('collection');

        $enabled = (array) config('resrv-vouchers.enabled_collections', []);

        if (! $collection || ! in_array($collection, $enabled, true)) {
            throw new ShouldNotIssueVoucherException(
                "Reservation {$reservation->id} is not in a voucher-enabled collection."
            );
        }
    }

    private function expiresAt(Reservation $reservation): Carbon
    {
        $graceDays = (int) config('resrv-vouchers.grace_days', 1);

        return $reservation->date_end->copy()->addDays($graceDays);
    }
}

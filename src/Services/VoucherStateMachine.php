<?php

namespace Reach\StatamicResrvVouchers\Services;

use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Events\VoucherInvalidated;
use Reach\StatamicResrvVouchers\Events\VoucherUsed;
use Reach\StatamicResrvVouchers\Exceptions\InvalidVoucherTransitionException;
use Reach\StatamicResrvVouchers\Models\Voucher;

class VoucherStateMachine
{
    public function statusOf(Voucher $voucher): VoucherStatus
    {
        if ($voucher->status === VoucherStatus::Issued && $voucher->isExpired()) {
            return VoucherStatus::Expired;
        }

        return $voucher->status;
    }

    public function markUsed(Voucher $voucher, ?string $userId): void
    {
        // Guard first for the lazy-expiry case and a clear error message; the conditional
        // UPDATE below is the actual concurrency control — only the first of two simultaneous
        // scans matches `status = 'issued'`, so the voucher can be redeemed exactly once.
        $this->guardTransition($voucher, [VoucherStatus::Issued], VoucherStatus::Used);

        $affected = Voucher::query()
            ->whereKey($voucher->getKey())
            ->where('status', VoucherStatus::Issued->value)
            ->update([
                'status' => VoucherStatus::Used->value,
                'used_at' => now(),
                'used_by_user_id' => $userId,
            ]);

        if ($affected === 0) {
            throw new InvalidVoucherTransitionException(
                'Cannot transition voucher from used to used.'
            );
        }

        $voucher->refresh();

        VoucherUsed::dispatch($voucher, $userId);
    }

    public function invalidate(Voucher $voucher, string $reason): void
    {
        $this->guardTransition(
            $voucher,
            [VoucherStatus::Issued, VoucherStatus::Expired],
            VoucherStatus::Invalidated,
        );

        $voucher->forceFill([
            'status' => VoucherStatus::Invalidated,
            'invalidated_reason' => $reason,
        ])->save();

        VoucherInvalidated::dispatch($voucher, $reason);
    }

    private function guardTransition(Voucher $voucher, array $allowedFrom, VoucherStatus $to): void
    {
        $current = $this->statusOf($voucher);

        if (! in_array($current, $allowedFrom, true)) {
            throw new InvalidVoucherTransitionException(
                "Cannot transition voucher from {$current->value} to {$to->value}."
            );
        }
    }
}

<?php

namespace Reach\StatamicResrvVouchers\Services;

use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Events\VoucherInvalidated;
use Reach\StatamicResrvVouchers\Events\VoucherUnmarked;
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
        $this->guardTransition($voucher, [VoucherStatus::Issued], VoucherStatus::Used);

        $voucher->forceFill([
            'status' => VoucherStatus::Used,
            'used_at' => now(),
            'used_by_user_id' => $userId,
        ])->save();

        VoucherUsed::dispatch($voucher, $userId);
    }

    public function unMark(Voucher $voucher, ?string $userId): void
    {
        $this->guardTransition($voucher, [VoucherStatus::Used], VoucherStatus::Issued);

        $voucher->forceFill([
            'status' => VoucherStatus::Issued,
            'used_at' => null,
            'used_by_user_id' => null,
        ])->save();

        VoucherUnmarked::dispatch($voucher, $userId);
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

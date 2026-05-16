<?php

namespace Reach\StatamicResrvVouchers\Enums;

enum VoucherStatus: string
{
    case Issued = 'issued';
    case Used = 'used';
    case Invalidated = 'invalidated';
    case Expired = 'expired';

    public function resultKey(): string
    {
        return match ($this) {
            self::Issued => 'success',
            self::Used => 'already-used',
            self::Invalidated => 'invalidated',
            self::Expired => 'expired',
        };
    }

    public function banner(): array
    {
        return match ($this) {
            self::Issued => ['tone' => 'success', 'message' => 'Voucher is valid.'],
            self::Used => ['tone' => 'warning', 'message' => 'Voucher has already been used.'],
            self::Invalidated => ['tone' => 'danger', 'message' => 'Voucher has been invalidated.'],
            self::Expired => ['tone' => 'danger', 'message' => 'Voucher has expired.'],
        };
    }
}

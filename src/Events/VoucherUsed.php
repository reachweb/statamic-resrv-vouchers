<?php

namespace Reach\StatamicResrvVouchers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Reach\StatamicResrvVouchers\Models\Voucher;

class VoucherUsed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Voucher $voucher,
        public ?string $userId = null,
    ) {}
}

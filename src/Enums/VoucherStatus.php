<?php

namespace Reach\StatamicResrvVouchers\Enums;

enum VoucherStatus: string
{
    case Issued = 'issued';
    case Used = 'used';
    case Invalidated = 'invalidated';
    case Expired = 'expired';
}

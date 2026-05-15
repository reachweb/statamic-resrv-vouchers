<?php

namespace Reach\StatamicResrvVouchers;

use Illuminate\Support\AggregateServiceProvider;
use Reach\StatamicResrvVouchers\Providers\VouchersProvider;

class StatamicResrvVouchersServiceProvider extends AggregateServiceProvider
{
    protected $providers = [
        VouchersProvider::class,
    ];
}

<?php

namespace Reach\StatamicResrvVouchers\Providers;

use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;
use Statamic\Providers\AddonServiceProvider;

class VouchersProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../../routes/cp.php',
    ];

    protected $listen = [];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(VoucherTokenSigner::class, fn () => VoucherTokenSigner::fromConfig());
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'statamic-resrv-vouchers');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'statamic-resrv-vouchers');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->mergeConfigFrom(__DIR__.'/../../config/resrv-vouchers.php', 'resrv-vouchers');

        $this->publishes([
            __DIR__.'/../../config/resrv-vouchers.php' => config_path('resrv-vouchers.php'),
        ], 'resrv-vouchers-config');
    }
}

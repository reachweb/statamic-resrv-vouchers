<?php

namespace Reach\StatamicResrvVouchers\Providers;

use Reach\StatamicResrv\Events\BuildingReservationEmail;
use Reach\StatamicResrv\Events\ReservationCancelled;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrv\Events\ReservationExpired;
use Reach\StatamicResrv\Events\ReservationRefunded;
use Reach\StatamicResrvVouchers\Listeners\AttachVoucherToReservationEmail;
use Reach\StatamicResrvVouchers\Listeners\GenerateVoucherForReservation;
use Reach\StatamicResrvVouchers\Listeners\InvalidateVoucherOnCancellation;
use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;
use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;

class VouchersProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../../routes/cp.php',
    ];

    protected $listen = [
        ReservationConfirmed::class => [
            GenerateVoucherForReservation::class,
        ],
        BuildingReservationEmail::class => [
            AttachVoucherToReservationEmail::class,
        ],
        ReservationCancelled::class => [
            InvalidateVoucherOnCancellation::class,
        ],
        ReservationRefunded::class => [
            InvalidateVoucherOnCancellation::class,
        ],
        ReservationExpired::class => [
            InvalidateVoucherOnCancellation::class,
        ],
    ];

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

        $this->createNavigation();
    }

    private function createNavigation(): void
    {
        Nav::extend(function ($nav) {
            $nav->create(__('Vouchers'))
                ->section('Resrv')
                ->can('use resrv')
                ->route('resrv-vouchers.index')
                ->children([
                    $nav->item(__('List'))
                        ->route('resrv-vouchers.index')
                        ->can('use resrv'),
                    $nav->item(__('Scan'))
                        ->route('resrv-vouchers.scan')
                        ->can('use resrv'),
                ]);
        });
    }
}

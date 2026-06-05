<?php

namespace Reach\StatamicResrvVouchers\Providers;

use Reach\StatamicResrv\Events\BuildingReservationEmail;
use Reach\StatamicResrv\Events\ReservationCancelled;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrv\Events\ReservationExpired;
use Reach\StatamicResrv\Events\ReservationRefunded;
use Reach\StatamicResrvVouchers\Console\Commands\InstallVouchers;
use Reach\StatamicResrvVouchers\Events\VoucherUsed;
use Reach\StatamicResrvVouchers\Listeners\AttachVoucherToReservationEmail;
use Reach\StatamicResrvVouchers\Listeners\GenerateVoucherForReservation;
use Reach\StatamicResrvVouchers\Listeners\InvalidateVoucherOnCancellation;
use Reach\StatamicResrvVouchers\Listeners\SendAttendedEmailOnVoucherUsed;
use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;
use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;

class VouchersProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../../routes/cp.php',
    ];

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../../resources/dist/hot',
    ];

    protected $commands = [
        InstallVouchers::class,
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
        VoucherUsed::class => [
            SendAttendedEmailOnVoucherUsed::class,
        ],
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(VoucherTokenSigner::class, fn () => VoucherTokenSigner::fromConfig());
    }

    public function bootAddon(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'statamic-resrv-vouchers');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'statamic-resrv-vouchers');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->mergeConfigFrom(__DIR__.'/../../config/resrv-vouchers.php', 'resrv-vouchers');

        $this->publishes([
            __DIR__.'/../../config/resrv-vouchers.php' => config_path('resrv-vouchers.php'),
        ], 'resrv-vouchers-config');

        $this->publishes([
            __DIR__.'/../../resources/views/email' => resource_path('views/vendor/statamic-resrv-vouchers/email'),
        ], 'resrv-vouchers-emails');

        $this->publishes([
            __DIR__.'/../../resources/lang' => lang_path('vendor/statamic-resrv-vouchers'),
        ], 'resrv-vouchers-language');

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

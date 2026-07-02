<?php

namespace Reach\StatamicResrvVouchers\Providers;

use Reach\StatamicResrv\Events\BuildingReservationEmail;
use Reach\StatamicResrv\Events\ReservationCancelled;
use Reach\StatamicResrv\Events\ReservationConfirmed;
use Reach\StatamicResrv\Events\ReservationExpired;
use Reach\StatamicResrv\Events\ReservationRefunded;
use Reach\StatamicResrvVouchers\Console\Commands\InstallVouchers;
use Reach\StatamicResrvVouchers\Events\VoucherUsed;
use Reach\StatamicResrvVouchers\Filters\VoucherEntry;
use Reach\StatamicResrvVouchers\Filters\VoucherStatus;
use Reach\StatamicResrvVouchers\Listeners\AttachVoucherToReservationEmail;
use Reach\StatamicResrvVouchers\Listeners\GenerateVoucherForReservation;
use Reach\StatamicResrvVouchers\Listeners\InvalidateVoucherOnCancellation;
use Reach\StatamicResrvVouchers\Listeners\SendAttendedEmailOnVoucherUsed;
use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;
use Reach\StatamicResrvVouchers\Widgets\Vouchers;
use RuntimeException;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
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

    protected $scopes = [
        VoucherStatus::class,
        VoucherEntry::class,
    ];

    protected $widgets = [
        Vouchers::class,
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
        // Without this event the addon still issues vouchers but silently sends confirmation
        // emails with no QR attached — fail loudly instead (a stale Resrv did exactly that).
        if (! class_exists(BuildingReservationEmail::class)) {
            throw new RuntimeException(
                'statamic-resrv-vouchers requires reachweb/statamic-resrv v6+ (the BuildingReservationEmail event is missing). Run: composer update reachweb/statamic-resrv'
            );
        }

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

        $this->bootPermissions();

        $this->createNavigation();
    }

    private function bootPermissions(): void
    {
        // Via Permission::extend, never $this->app->booted() — in Statamic 6 bootAddon()
        // runs while the booted callbacks are iterating, so booted() fires the callback
        // immediately AND re-queues it, registering the permission twice.
        Permission::extend(function () {
            Permission::group('statamic-resrv-vouchers', 'Resrv Vouchers Permissions', function () {
                Permission::register('use resrv vouchers', function ($permission) {
                    $permission
                        ->label(__('Use Resrv Vouchers'))
                        ->description(__('Allow usage of the Resrv Vouchers addon'));
                });
            });
        });
    }

    private function createNavigation(): void
    {
        Nav::extend(function ($nav) {
            $nav->create(__('Vouchers'))
                ->section('Resrv')
                ->can('use resrv vouchers')
                ->route('resrv-vouchers.index')
                ->children([
                    $nav->item(__('List'))
                        ->route('resrv-vouchers.index')
                        ->can('use resrv vouchers'),
                    $nav->item(__('Scan'))
                        ->route('resrv-vouchers.scan')
                        ->can('use resrv vouchers'),
                ]);
        });
    }
}

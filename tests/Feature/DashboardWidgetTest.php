<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Reach\StatamicResrvVouchers\Widgets\Vouchers;

it('registers the vouchers dashboard widget under its handle', function () {
    expect(app('statamic.widgets')->has('vouchers'))->toBeTrue();
    expect(app('statamic.widgets')->get('vouchers'))->toBe(Vouchers::class);
});

it('renders the widget with scan and list links for a permitted user', function () {
    $this->signInWithPermissions(['access cp', 'use resrv vouchers']);

    $component = app(Vouchers::class)->component()->toArray();

    expect($component['name'])->toBe('resrv-vouchers-widget');
    expect($component['props']['scanUrl'])->toBe(cp_route('resrv-vouchers.scan'));
    expect($component['props']['listUrl'])->toBe(cp_route('resrv-vouchers.index'));
});

it('honors a configured title override', function () {
    $this->signInWithPermissions(['access cp', 'use resrv vouchers']);

    $widget = app(Vouchers::class);
    $widget->setConfig(['title' => 'Door check-in']);

    expect($widget->component()->toArray()['props']['title'])->toBe('Door check-in');
});

it('renders nothing for a user without the vouchers permission', function () {
    $this->signInUserWithoutPermissions();

    expect(app(Vouchers::class)->component())->toBeNull();
});

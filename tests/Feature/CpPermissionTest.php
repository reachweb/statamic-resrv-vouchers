<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Permission;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
    Mail::fake();

    // The guard throws AuthorizationException; exception handling must be on to get an HTTP 403.
    $this->withExceptionHandling();
});

it('allows a role with only the vouchers permission to reach the voucher CP routes', function () {
    $this->signInWithPermissions(['access cp', 'use resrv vouchers']);
    $voucher = $this->makeIssuedVoucher();

    $this->get(cp_route('resrv-vouchers.index'))->assertOk();
    $this->get(cp_route('resrv-vouchers.scan'))->assertOk();
    $this->getJson(cp_route('resrv-vouchers.index.json'))->assertOk();
    $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => $voucher->token])->assertOk();
});

it('denies a role with only the use resrv permission access to the voucher CP routes', function () {
    $this->signInWithPermissions(['access cp', 'use resrv']);

    // HTML GETs to forbidden CP routes redirect to Statamic's unauthorized page,
    // so assert the 403 through JSON requests like Resrv's CpRoutePermissionTest.
    $this->getJson(cp_route('resrv-vouchers.index'))->assertForbidden();
    $this->getJson(cp_route('resrv-vouchers.scan'))->assertForbidden();
    $this->getJson(cp_route('resrv-vouchers.index.json'))->assertForbidden();
    $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => 'anything'])->assertForbidden();
    $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => 'anything'])->assertForbidden();
});

it('allows super admins to reach the voucher CP routes', function () {
    $this->signInAdmin();

    $this->get(cp_route('resrv-vouchers.index'))->assertOk();
    $this->get(cp_route('resrv-vouchers.scan'))->assertOk();
});

it('registers the use resrv vouchers permission exactly once', function () {
    $group = collect(Permission::boot()->tree())->firstWhere('handle', 'statamic-resrv-vouchers');

    expect($group)->not->toBeNull();
    expect(collect($group['permissions'])->where('value', 'use resrv vouchers'))->toHaveCount(1);
});

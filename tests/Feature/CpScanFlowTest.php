<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Reach\StatamicResrv\Mail\ReservationConfirmed as ReservationConfirmedMail;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Models\VoucherScan;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
    Mail::fake();
});

it('returns 200 for lookup with a valid token', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $response = $this->postJson(cp_route('resrv-vouchers.lookup'), ['token' => $voucher->token]);

    $response->assertOk()
        ->assertJsonPath('voucher.id', $voucher->id)
        ->assertJsonPath('status', VoucherStatus::Issued->value);

    expect(VoucherScan::query()->where('voucher_id', $voucher->id)
        ->where('action', 'scan')->where('result', 'success')->exists())->toBeTrue();
});

it('returns 422 for lookup with an invalid token', function () {
    $this->signInAdmin();

    $response = $this->postJson(cp_route('resrv-vouchers.lookup'), ['token' => 'not-a-valid-token']);

    $response->assertStatus(422);

    expect(VoucherScan::query()->where('action', 'scan')->where('result', 'not-found')->exists())->toBeTrue();
});

it('returns 422 for lookup when no token is provided', function () {
    $this->signInAdmin();

    $response = $this->withExceptionHandling()
        ->postJson(cp_route('resrv-vouchers.lookup'), []);

    $response->assertStatus(422);
});

it('returns 403 for lookup when the user lacks the use resrv permission', function () {
    $this->signInUserWithoutResrvPermission();
    $voucher = $this->makeIssuedVoucher();

    $this->withExceptionHandling()
        ->postJson(cp_route('resrv-vouchers.lookup'), ['token' => $voucher->token])
        ->assertStatus(403);
});

it('marks a voucher as used and audit-logs the action', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $response = $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $voucher->token]);

    $response->assertOk();
    expect($voucher->fresh()->status)->toBe(VoucherStatus::Used);

    expect(VoucherScan::query()->where('voucher_id', $voucher->id)
        ->where('action', 'mark-used')->where('result', 'success')->exists())->toBeTrue();
});

it('returns 422 when marking used a voucher that is already used', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $voucher->token])->assertOk();
    $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $voucher->token])->assertStatus(422);
});

it('un-marks a used voucher and audit-logs the action', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();
    $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $voucher->token])->assertOk();

    $response = $this->patchJson(cp_route('resrv-vouchers.un-mark'), ['token' => $voucher->token]);

    $response->assertOk();
    expect($voucher->fresh()->status)->toBe(VoucherStatus::Issued);

    expect(VoucherScan::query()->where('voucher_id', $voucher->id)
        ->where('action', 'un-mark')->where('result', 'success')->exists())->toBeTrue();
});

it('returns 403 for mark-used when the user lacks the permission', function () {
    $this->signInUserWithoutResrvPermission();
    $voucher = $this->makeIssuedVoucher();

    $this->withExceptionHandling()
        ->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $voucher->token])
        ->assertStatus(403);
});

it('resends the confirmation email and audit-logs the action', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $response = $this->postJson(cp_route('resrv-vouchers.resend', $voucher->id));

    $response->assertOk();
    Mail::assertSent(ReservationConfirmedMail::class);

    expect(VoucherScan::query()->where('voucher_id', $voucher->id)
        ->where('action', 'resend')->where('result', 'success')->exists())->toBeTrue();
});

it('returns 200 for the CP index page', function () {
    $this->signInAdmin();

    $this->get(cp_route('resrv-vouchers.index'))->assertOk();
});

it('renders the CP index page as Inertia with list bootstrap props', function () {
    $this->signInAdmin();

    $this->get(cp_route('resrv-vouchers.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('resrv-vouchers::Vouchers/Index')
            ->where('listUrl', cp_route('resrv-vouchers.index.json'))
            ->where('defaultPerPage', 25)
            ->has('statuses', count(VoucherStatus::cases()))
        );
});

it('returns 200 for the CP scan page', function () {
    $this->signInAdmin();

    $this->get(cp_route('resrv-vouchers.scan'))->assertOk();
});

it('renders the CP scan page as Inertia with mutation URLs as props', function () {
    $this->signInAdmin();

    $this->get(cp_route('resrv-vouchers.scan'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('resrv-vouchers::Vouchers/Scan')
            ->where('lookupUrl', cp_route('resrv-vouchers.lookup'))
            ->where('markUsedUrl', cp_route('resrv-vouchers.mark-used'))
            ->where('unMarkUrl', cp_route('resrv-vouchers.un-mark'))
        );
});

it('lists vouchers as JSON', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $response = $this->getJson(cp_route('resrv-vouchers.index.json'));

    $response->assertOk()->assertJsonFragment(['id' => $voucher->id]);
});

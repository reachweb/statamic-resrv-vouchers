<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Reach\StatamicResrv\Mail\ReservationConfirmed as ReservationConfirmedMail;
use Reach\StatamicResrv\Models\Rate;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;
use Reach\StatamicResrvVouchers\Models\VoucherScan;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
    Mail::fake();
});

it('returns 200 for lookup with a valid token', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $response = $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => $voucher->token]);

    $response->assertOk()
        ->assertJsonPath('voucher.id', $voucher->id)
        ->assertJsonPath('token', $voucher->token)
        ->assertJsonPath('status', VoucherStatus::Issued->value)
        ->assertJsonPath('status_banner.tone', 'success')
        ->assertJsonPath('entry_title', 'Test Item')
        ->assertJsonPath('rate', null)
        ->assertJsonPath('dates.start', $voucher->reservation->date_start->format('Y-m-d'))
        ->assertJsonPath('dates.end', $voucher->reservation->date_end->format('Y-m-d'));

    expect(VoucherScan::query()->where('voucher_id', $voucher->id)
        ->where('action', 'scan')->where('result', 'success')->exists())->toBeTrue();
});

it('finds a voucher by reservation code', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();
    $this->makeIssuedVoucher();

    $response = $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => (string) $voucher->reservation_id]);

    $response->assertOk()
        ->assertJsonPath('voucher.id', $voucher->id)
        ->assertJsonPath('token', $voucher->token);

    expect(VoucherScan::query()->where('voucher_id', $voucher->id)
        ->where('action', 'scan')->where('result', 'success')->exists())->toBeTrue();
});

it('includes the rate label when the reservation was booked with a rate', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $rate = Rate::create(['collection' => 'pages', 'title' => 'Early Bird', 'slug' => 'early-bird']);
    $voucher->reservation->update(['rate_id' => $rate->id]);

    $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => $voucher->token])
        ->assertOk()
        ->assertJsonPath('rate', 'Early Bird');
});

it('finds a voucher by booking reference ignoring case and whitespace', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();
    $this->makeIssuedVoucher();

    $response = $this->postJson(cp_route('resrv-vouchers.lookup'), [
        'query' => ' '.mb_strtolower($voucher->reservation->reference).' ',
    ]);

    $response->assertOk()->assertJsonPath('voucher.id', $voucher->id);
});

it('marks a voucher used with the token returned by a reservation-code lookup', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $token = $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => (string) $voucher->reservation_id])
        ->assertOk()
        ->json('token');

    $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $token])
        ->assertOk()
        ->assertJsonPath('status', VoucherStatus::Used->value);
});

it('returns 422 with a distinct message when the reservation exists but has no voucher', function () {
    $this->signInAdmin();
    $reservation = $this->makeConfirmedReservation();

    $response = $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => (string) $reservation->id]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Reservation found, but it has no voucher.');

    expect(VoucherScan::query()->where('action', 'scan')->where('result', 'not-found')->exists())->toBeTrue();
});

it('returns 422 for lookup with an invalid token', function () {
    $this->signInAdmin();

    $response = $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => 'not-a-valid-token']);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'No voucher matches that token, reservation code, or booking reference.');

    expect(VoucherScan::query()->where('action', 'scan')->where('result', 'not-found')->exists())->toBeTrue();
});

it('returns 422 when no reservation matches a numeric query', function () {
    $this->signInAdmin();

    $this->postJson(cp_route('resrv-vouchers.lookup'), ['query' => '999999'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'No voucher matches that token, reservation code, or booking reference.');
});

it('returns 422 for lookup when no query is provided', function () {
    $this->signInAdmin();

    $response = $this->withExceptionHandling()
        ->postJson(cp_route('resrv-vouchers.lookup'), []);

    $response->assertStatus(422);
});

it('returns 403 for lookup when the user lacks the vouchers permission', function () {
    $this->signInUserWithoutPermissions();
    $voucher = $this->makeIssuedVoucher();

    $this->withExceptionHandling()
        ->postJson(cp_route('resrv-vouchers.lookup'), ['query' => $voucher->token])
        ->assertStatus(403);
});

it('marks a voucher as used and audit-logs the action', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $response = $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $voucher->token]);

    // The scan card refreshes from this response — it must carry the full lookup payload,
    // and the action must not add a 'scan' audit row on top of its own.
    $response->assertOk()
        ->assertJsonPath('status', VoucherStatus::Used->value)
        ->assertJsonPath('status_banner.tone', 'warning')
        ->assertJsonPath('voucher.id', $voucher->id);
    expect($voucher->fresh()->status)->toBe(VoucherStatus::Used);

    expect(VoucherScan::query()->where('voucher_id', $voucher->id)
        ->where('action', 'mark-used')->where('result', 'success')->exists())->toBeTrue();
    expect(VoucherScan::query()->where('voucher_id', $voucher->id)
        ->where('action', 'scan')->exists())->toBeFalse();
});

it('returns 422 when marking used a voucher that is already used', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $voucher->token])->assertOk();
    $this->patchJson(cp_route('resrv-vouchers.mark-used'), ['token' => $voucher->token])->assertStatus(422);
});

it('returns 403 for mark-used when the user lacks the permission', function () {
    $this->signInUserWithoutPermissions();
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

it('renders the CP index page as Inertia with scope filters and list URL props', function () {
    $this->signInAdmin();

    $this->get(cp_route('resrv-vouchers.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('resrv-vouchers::Vouchers/Index')
            ->where('listUrl', cp_route('resrv-vouchers.index.json'))
            ->has('filters', 1, fn (AssertableInertia $filter) => $filter
                ->where('handle', 'voucher_status')
                ->where('auto_apply', [])
                ->etc()
            )
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
        );
});

it('lists vouchers as JSON in the Listing protocol shape', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();

    $response = $this->getJson(cp_route('resrv-vouchers.index.json'));

    $response->assertOk()
        ->assertJsonPath('data.0.id', $voucher->id)
        ->assertJsonPath('data.0.reference', $voucher->reservation->reference)
        ->assertJsonPath('data.0.customer.email', 'guest@example.com')
        ->assertJsonPath('meta.columns.0.field', 'id')
        ->assertJsonPath('meta.activeFilterBadges', []);
});

it('filters the voucher list by status and returns a filter badge', function () {
    $this->signInAdmin();
    $this->makeIssuedVoucher();
    $used = $this->makeIssuedVoucher();
    $used->update(['status' => VoucherStatus::Used, 'used_at' => now()]);

    $filters = base64_encode(json_encode(['voucher_status' => ['status' => ['used']]]));

    $this->getJson(cp_route('resrv-vouchers.index.json', ['filters' => $filters]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $used->id)
        ->assertJsonPath('meta.activeFilterBadges.voucher_status', 'Used');
});

it('sorts the voucher list by a whitelisted column and falls back on unknown sort fields', function () {
    $this->signInAdmin();
    $first = $this->makeIssuedVoucher();
    $second = $this->makeIssuedVoucher();
    $first->update(['expires_at' => now()->addDay()]);
    $second->update(['expires_at' => now()->addDays(5)]);

    $this->getJson(cp_route('resrv-vouchers.index.json', ['sort' => 'expires_at', 'order' => 'asc']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $first->id);

    $this->getJson(cp_route('resrv-vouchers.index.json', ['sort' => 'token', 'order' => 'asc']))
        ->assertOk();
});

it('clamps perPage and paginates the voucher list', function () {
    $this->signInAdmin();
    $this->makeIssuedVoucher();
    $this->makeIssuedVoucher();
    $this->makeIssuedVoucher();

    $this->getJson(cp_route('resrv-vouchers.index.json', ['perPage' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson(cp_route('resrv-vouchers.index.json', ['perPage' => 500]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('searches the voucher list by reservation reference', function () {
    $this->signInAdmin();
    $voucher = $this->makeIssuedVoucher();
    $this->makeIssuedVoucher();

    $this->getJson(cp_route('resrv-vouchers.index.json', ['search' => $voucher->reservation->reference]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $voucher->id);
});

<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Reach\StatamicResrvVouchers\Mail\VoucherAttended;
use Reach\StatamicResrvVouchers\Services\VoucherStateMachine;

beforeEach(function () {
    Config::set('resrv-vouchers.enabled_collections', ['pages']);
});

it('queues the attended email to the customer when a voucher is marked used', function () {
    Mail::fake();

    $voucher = $this->makeIssuedVoucher();
    $customerEmail = $voucher->reservation->customer->email;

    app(VoucherStateMachine::class)->markUsed($voucher->fresh(), 'admin-id');

    Mail::assertQueued(
        VoucherAttended::class,
        fn (VoucherAttended $mail) => $mail->hasTo($customerEmail)
            && $mail->voucher->id === $voucher->id,
    );
});

it('renders the attended email markdown with customer name and reference', function () {
    Mail::fake();

    $voucher = $this->makeIssuedVoucher();
    $reference = $voucher->reservation->reference;

    app(VoucherStateMachine::class)->markUsed($voucher->fresh(), 'admin-id');

    $rendered = (new VoucherAttended($voucher->fresh()))->render();

    expect($rendered)->toContain('Test Guest');
    expect($rendered)->toContain($reference);
});

it('skips the attended email when the reservation customer has no email', function () {
    Mail::fake();

    $voucher = $this->makeIssuedVoucher();
    $voucher->reservation->customer->update(['email' => '']);

    app(VoucherStateMachine::class)->markUsed($voucher->fresh(), 'admin-id');

    Mail::assertNothingQueued();
});

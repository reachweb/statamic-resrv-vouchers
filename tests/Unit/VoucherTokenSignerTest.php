<?php

use Reach\StatamicResrvVouchers\Services\VoucherTokenSigner;

it('round-trips a uuid through sign and verify', function () {
    $signer = new VoucherTokenSigner('test-key');
    $uuid = '11111111-1111-1111-1111-111111111111';

    $token = $signer->sign($uuid);

    expect($signer->verify($token))->toBe($uuid);
});

it('rejects a tampered token', function () {
    $signer = new VoucherTokenSigner('test-key');
    $token = $signer->sign('22222222-2222-2222-2222-222222222222');

    [$uuidPart, $sigPart] = explode('.', $token, 2);
    $tampered = $uuidPart.'.'.strrev($sigPart);

    expect($signer->verify($tampered))->toBeNull();
});

it('rejects a token signed with a different key', function () {
    $a = new VoucherTokenSigner('key-a');
    $b = new VoucherTokenSigner('key-b');

    $token = $a->sign('33333333-3333-3333-3333-333333333333');

    expect($b->verify($token))->toBeNull();
});

it('returns null for malformed input without throwing', function () {
    $signer = new VoucherTokenSigner('test-key');

    expect($signer->verify('no-dot-here'))->toBeNull()
        ->and($signer->verify(''))->toBeNull()
        ->and($signer->verify('@@@@.@@@@'))->toBeNull();
});

it('rejects an empty key', function () {
    new VoucherTokenSigner('');
})->throws(InvalidArgumentException::class);

it('resolves a signer from the configured signing key', function () {
    config(['resrv-vouchers.signing_key' => 'config-key']);

    $signer = VoucherTokenSigner::fromConfig();
    $token = $signer->sign('44444444-4444-4444-4444-444444444444');

    expect((new VoucherTokenSigner('config-key'))->verify($token))
        ->toBe('44444444-4444-4444-4444-444444444444');
});

it('falls back to the app key when no signing key is configured', function () {
    config(['resrv-vouchers.signing_key' => null]);
    config(['app.key' => 'app-key-fallback']);

    $signer = VoucherTokenSigner::fromConfig();
    $token = $signer->sign('55555555-5555-5555-5555-555555555555');

    expect((new VoucherTokenSigner('app-key-fallback'))->verify($token))
        ->toBe('55555555-5555-5555-5555-555555555555');
});

it('resolves a configured signer from the container', function () {
    config(['resrv-vouchers.signing_key' => 'container-key']);

    $signer = app(VoucherTokenSigner::class);
    $token = $signer->sign('66666666-6666-6666-6666-666666666666');

    expect((new VoucherTokenSigner('container-key'))->verify($token))
        ->toBe('66666666-6666-6666-6666-666666666666');
});

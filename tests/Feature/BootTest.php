<?php

it('boots the package without error', function () {
    expect(app()->bound('config'))->toBeTrue();
});

it('exposes the resrv-vouchers config with defaults', function () {
    expect(config('resrv-vouchers.grace_days'))->toBe(1)
        ->and(config('resrv-vouchers.enabled_collections'))->toBe([]);
});

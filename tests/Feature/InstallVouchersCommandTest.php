<?php

namespace Reach\StatamicResrvVouchers\Tests\Feature;

it('registers the resrv-vouchers:install command', function () {
    expect(collect(\Artisan::all())->keys())->toContain('resrv-vouchers:install');
});

it('publishes the config file and accepts no for the optional publish prompts', function () {
    $configTarget = config_path('resrv-vouchers.php');
    if (file_exists($configTarget)) {
        unlink($configTarget);
    }

    $this->artisan('resrv-vouchers:install')
        ->expectsQuestion('Publish the email templates? (only needed if you wish to edit them)', false)
        ->expectsQuestion('Publish the language files? (only needed if you wish to edit them)', false)
        ->expectsOutputToContain('Installation finished.')
        ->assertSuccessful();

    expect(file_exists($configTarget))->toBeTrue();

    unlink($configTarget);
});

it('publishes email templates and language files when confirmed', function () {
    $emailTarget = resource_path('views/vendor/statamic-resrv-vouchers/email/vouchers/attended.blade.php');
    $langTarget = lang_path('vendor/statamic-resrv-vouchers/en/email.php');

    foreach ([$emailTarget, $langTarget] as $path) {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    $this->artisan('resrv-vouchers:install')
        ->expectsQuestion('Publish the email templates? (only needed if you wish to edit them)', true)
        ->expectsQuestion('Publish the language files? (only needed if you wish to edit them)', true)
        ->assertSuccessful();

    expect(file_exists($emailTarget))->toBeTrue();
    expect(file_exists($langTarget))->toBeTrue();

    @unlink($emailTarget);
    @unlink($langTarget);
});

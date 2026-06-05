<?php

namespace Reach\StatamicResrvVouchers\Tests\Unit;

use Reach\StatamicResrvVouchers\Enums\VoucherStatus;

it('maps each status to a stable audit-log result key', function (VoucherStatus $status, string $expected) {
    expect($status->resultKey())->toBe($expected);
})->with([
    'issued → success' => [VoucherStatus::Issued, 'success'],
    'used → already-used' => [VoucherStatus::Used, 'already-used'],
    'invalidated → invalidated' => [VoucherStatus::Invalidated, 'invalidated'],
    'expired → expired' => [VoucherStatus::Expired, 'expired'],
]);

it('emits a status banner with tone + message for each status', function (VoucherStatus $status, string $tone) {
    $banner = $status->banner();

    expect($banner)->toHaveKeys(['tone', 'message']);
    expect($banner['tone'])->toBe($tone);
    expect($banner['message'])->toBeString()->not->toBe('');
})->with([
    'issued tone' => [VoucherStatus::Issued, 'success'],
    'used tone' => [VoucherStatus::Used, 'warning'],
    'invalidated tone' => [VoucherStatus::Invalidated, 'danger'],
    'expired tone' => [VoucherStatus::Expired, 'danger'],
]);

it('covers every enum case in both methods', function () {
    foreach (VoucherStatus::cases() as $case) {
        expect($case->resultKey())->toBeString();
        expect($case->banner())->toBeArray();
    }
});

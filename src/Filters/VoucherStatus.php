<?php

namespace Reach\StatamicResrvVouchers\Filters;

use Reach\StatamicResrvVouchers\Enums\VoucherStatus as VoucherStatusEnum;
use Statamic\Query\Scopes\Filter;

class VoucherStatus extends Filter
{
    protected $pinned = true;

    public static function title()
    {
        return __('Status');
    }

    public function fieldItems()
    {
        return [
            'status' => [
                'type' => 'checkboxes',
                'options' => collect(VoucherStatusEnum::cases())
                    ->mapWithKeys(fn (VoucherStatusEnum $status) => [$status->value => __(ucfirst($status->value))])
                    ->all(),
            ],
        ];
    }

    public function autoApply()
    {
        return [];
    }

    public function apply($query, $values)
    {
        $statuses = $values['status'] ?? [];

        if (empty($statuses)) {
            return;
        }

        // Lazy expiry never writes 'expired' to the column, so map the status options onto
        // expires_at predicates instead of matching the raw column (otherwise the "Expired"
        // option finds nothing and "Issued" wrongly includes already-expired vouchers).
        $query->where(function ($query) use ($statuses) {
            foreach ($statuses as $status) {
                $query->orWhere(function ($query) use ($status) {
                    match ($status) {
                        VoucherStatusEnum::Expired->value => $query
                            ->where('status', VoucherStatusEnum::Issued->value)
                            ->where('expires_at', '<', now()),
                        VoucherStatusEnum::Issued->value => $query
                            ->where('status', VoucherStatusEnum::Issued->value)
                            ->where(fn ($query) => $query
                                ->whereNull('expires_at')
                                ->orWhere('expires_at', '>=', now())),
                        default => $query->where('status', $status),
                    };
                });
            }
        });
    }

    public function badge($values)
    {
        return implode(', ', array_map('ucwords', $values['status'] ?? []));
    }

    public function visibleTo($key)
    {
        return $key === 'resrv-vouchers';
    }
}

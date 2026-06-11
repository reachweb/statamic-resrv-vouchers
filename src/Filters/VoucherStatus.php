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
        if (empty($values['status'])) {
            return;
        }

        $query->whereIn('status', $values['status']);
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

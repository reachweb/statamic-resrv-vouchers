<?php

namespace Reach\StatamicResrvVouchers\Filters;

use Illuminate\Support\Collection;
use Reach\StatamicResrv\Models\Entry;
use Reach\StatamicResrv\Models\Reservation;
use Reach\StatamicResrvVouchers\Models\Voucher;
use Statamic\Query\Scopes\Filter;

class VoucherEntry extends Filter
{
    protected $pinned = true;

    protected $entries;

    public static function title()
    {
        return __('Entry');
    }

    public function fieldItems()
    {
        return [
            'entry' => [
                'type' => 'checkboxes',
                'options' => $this->entries()->all(),
            ],
        ];
    }

    public function apply($query, $values)
    {
        if (empty($values['entry'])) {
            return;
        }

        $query->whereHas('reservation', fn ($reservation) => $reservation->whereIn('item_id', $values['entry']));
    }

    public function badge($values)
    {
        // Fall back to the raw id: a saved filter can reference a since-deleted entry.
        return collect($values['entry'] ?? [])
            ->map(fn ($entry) => $this->entries()->get($entry, $entry))
            ->implode(', ');
    }

    public function visibleTo($key)
    {
        return $key === 'resrv-vouchers';
    }

    // Titles come from the resrv_entries mirror (keyed by item_id) — every voucher-eligible entry is
    // mirrored, so this is a single query that matches the value the list column shows. Only entries
    // that actually have a voucher are offered as options. Built lazily: Statamic reconstructs the
    // filter on every filtered fetch just to call apply(), which never needs the option list.
    protected function entries(): Collection
    {
        return $this->entries ??= Entry::query()
            ->whereIn('item_id', Reservation::query()
                ->whereIn('id', Voucher::query()->select('reservation_id'))
                ->select('item_id'))
            ->orderBy('title')
            ->pluck('title', 'item_id');
    }
}

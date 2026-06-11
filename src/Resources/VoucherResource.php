<?php

namespace Reach\StatamicResrvVouchers\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Reach\StatamicResrvVouchers\Blueprints\VoucherBlueprint;
use Statamic\Http\Resources\CP\Concerns\HasRequestedColumns;

class VoucherResource extends ResourceCollection
{
    use HasRequestedColumns;

    protected $blueprint;

    protected $columns;

    protected $columnPreferenceKey;

    protected $dateFieldtypes = [];

    public function __construct($resource)
    {
        parent::__construct($resource);
        $voucherBlueprint = new VoucherBlueprint;
        $this->blueprint = $voucherBlueprint();
    }

    public function columnPreferenceKey($key)
    {
        $this->columnPreferenceKey = $key;

        return $this;
    }

    public function toArray($request)
    {
        $this->setColumns();

        return [
            'data' => $this->collection->transform(fn ($voucher) => [
                'id' => $voucher->id,
                'status' => $voucher->status->value,
                'reference' => $voucher->reservation?->reference,
                'customer' => ['email' => $voucher->reservation?->customer?->email],
                'expires_at' => $this->dateIndexValue('expires_at', $voucher->expires_at),
                'used_at' => $this->dateIndexValue('used_at', $voucher->used_at),
                'created_at' => $this->dateIndexValue('created_at', $voucher->created_at),
            ]),

            'meta' => [
                'columns' => $this->visibleColumns(),
            ],
        ];
    }

    private function setColumns()
    {
        $columns = $this->blueprint->columns();

        if ($key = $this->columnPreferenceKey) {
            $columns->setPreferred($key);
        }

        $this->columns = $columns->rejectUnlisted()->values();
    }

    // The Listing component renders date columns with DateIndexFieldtype, which expects the
    // Date fieldtype's preProcessIndex() payload — a pre-formatted string would render as "now".
    private function dateIndexValue(string $handle, ?Carbon $date): ?array
    {
        $fieldtype = $this->dateFieldtypes[$handle] ??= $this->blueprint->field($handle)->fieldtype();

        return $fieldtype->preProcessIndex($date);
    }
}

<?php

namespace Reach\StatamicResrvVouchers\Blueprints;

use Statamic\Facades\Blueprint;

class VoucherBlueprint
{
    public function __invoke()
    {
        return Blueprint::make()->setContents([
            'sections' => [
                'main' => [
                    'fields' => [
                        [
                            'handle' => 'id',
                            'field' => [
                                'type' => 'text',
                                'listable' => true,
                                'display' => 'ID',
                            ],
                        ],
                        [
                            'handle' => 'status',
                            'field' => [
                                'type' => 'text',
                                'listable' => true,
                                'display' => 'Status',
                            ],
                        ],
                        [
                            'handle' => 'reference',
                            'field' => [
                                'type' => 'text',
                                'listable' => true,
                                'display' => 'Reservation',
                                'sortable' => false,
                            ],
                        ],
                        [
                            'handle' => 'customer',
                            'field' => [
                                'type' => 'text',
                                'listable' => true,
                                'display' => 'Customer',
                                'sortable' => false,
                            ],
                        ],
                        [
                            'handle' => 'expires_at',
                            'field' => [
                                'type' => 'date',
                                'time_enabled' => true,
                                'listable' => true,
                                'display' => 'Expires',
                            ],
                        ],
                        [
                            'handle' => 'used_at',
                            'field' => [
                                'type' => 'date',
                                'time_enabled' => true,
                                'listable' => true,
                                'display' => 'Used at',
                            ],
                        ],
                        [
                            'handle' => 'created_at',
                            'field' => [
                                'type' => 'date',
                                'time_enabled' => true,
                                'listable' => true,
                                'display' => 'Issued at',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}

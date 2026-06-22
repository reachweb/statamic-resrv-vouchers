<?php

namespace Reach\StatamicResrvVouchers\Widgets;

use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

class Vouchers extends Widget
{
    public function component()
    {
        // Mirror Statamic's own Collection widget: hide the card entirely from anyone who
        // can't use the addon, even if the dashboard config omitted the `can` key.
        if (! User::current()?->can('use resrv vouchers')) {
            return;
        }

        return VueComponent::render('resrv-vouchers-widget', [
            'title' => $this->config('title', __('Vouchers')),
            'scanUrl' => cp_route('resrv-vouchers.scan'),
            'listUrl' => cp_route('resrv-vouchers.index'),
        ]);
    }
}

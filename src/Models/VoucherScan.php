<?php

namespace Reach\StatamicResrvVouchers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Statamic\Facades\User;

class VoucherScan extends Model
{
    protected $table = 'resrv_voucher_scans';

    protected $guarded = [];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function getUserAttribute()
    {
        return $this->user_id ? User::find($this->user_id) : null;
    }
}

<?php

namespace Reach\StatamicResrvVouchers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Reach\StatamicResrv\Models\Reservation;
use Reach\StatamicResrvVouchers\Enums\VoucherStatus;

class Voucher extends Model
{
    use HasUuids;

    protected $table = 'resrv_vouchers';

    protected $guarded = [];

    protected $hidden = ['token'];

    protected $casts = [
        'status' => VoucherStatus::class,
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(VoucherScan::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

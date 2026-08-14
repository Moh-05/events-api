<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'vendor_id',
        'booking_id',
        'type',   // credit | refund | withdrawal | commission
        'amount', // signed: credit > 0, refund/withdrawal/commission < 0
        'paid_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}

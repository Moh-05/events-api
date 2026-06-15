<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'vendor_id',
        'booking_id',
        'type',   // credit | refund | withdrawal
        'amount', // signed: credit > 0, refund < 0, withdrawal < 0
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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

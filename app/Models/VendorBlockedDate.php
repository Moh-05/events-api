<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorBlockedDate extends Model
{
    protected $fillable = [
        'vendor_id',
        'date',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}

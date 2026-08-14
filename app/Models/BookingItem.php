<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id',
        'vendor_product_id',
        'quantity',
        'unit_price',
        'original_unit_price',
        'selected_options',
    ];

    protected $casts = [
        'quantity'            => 'integer',
        'unit_price'          => 'decimal:2',
        'original_unit_price' => 'decimal:2',
        'selected_options'    => 'array',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function product()
    {
        return $this->belongsTo(VendorProduct::class, 'vendor_product_id');
    }
}

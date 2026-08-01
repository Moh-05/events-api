<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProduct extends Model
{
    protected $fillable = [
        'vendor_id',
        'name',
        'description',
        'price',
        'meta',
        'is_available',
        'stock',
    ];

    // deposit_percent is intentionally NOT fillable — the deposit is a fixed
    // platform rule (20%), set by the DB default, not editable by vendors.
    protected $casts = [
        'meta' => 'array',
        'is_available' => 'boolean',
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function images()
    {
        return $this->hasMany(VendorProductImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(VendorProductImage::class)->where('is_primary', true);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'vendor_product_id');
    }

    public function reports()
    {
        return $this->hasMany(ContentReport::class, 'reportable_id')
            ->where('reportable_type', 'product');
    }
}
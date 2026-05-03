<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProductImage extends Model
{
    protected $fillable = [
        'vendor_product_id',
        'image_path',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(VendorProduct::class, 'vendor_product_id');
    }
}
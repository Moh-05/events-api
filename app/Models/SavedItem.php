<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedItem extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_product_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // The saved product/service. FK column is vendor_product_id; method
    // named product() to match Booking::product().
    public function product()
    {
        return $this->belongsTo(VendorProduct::class, 'vendor_product_id');
    }
}

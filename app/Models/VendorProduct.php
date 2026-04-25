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
        'image',
        'meta',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'meta'         => 'array',
            'is_available' => 'boolean',
            'price'        => 'decimal:2',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
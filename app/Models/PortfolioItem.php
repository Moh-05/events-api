<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioItem extends Model
{
    protected $fillable = [
        'vendor_id',
        'title',
        'description',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function images()
    {
        return $this->hasMany(PortfolioItemImage::class);
    }

    public function primaryImage()
    {
        return $this->hasOne(PortfolioItemImage::class)->where('is_primary', true);
    }

    public function reports()
    {
        return $this->hasMany(ContentReport::class, 'reportable_id')
            ->where('reportable_type', 'portfolio_item');
    }
}

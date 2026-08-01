<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioItem extends Model
{
    // HasFactory only enables PortfolioItem::factory(), used by the local demo
    // seeder (HaflatiDemoSeeder). No behaviour change — see smart-search.md.
    use HasFactory;

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
}

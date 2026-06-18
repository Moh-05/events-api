<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioItemImage extends Model
{
    protected $fillable = [
        'portfolio_item_id',
        'image_path',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function portfolioItem()
    {
        return $this->belongsTo(PortfolioItem::class);
    }
}

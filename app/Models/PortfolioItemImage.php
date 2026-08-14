<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    // Full public URL to the stored image, so the app uses it directly.
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('supabase');

        return $this->image_path ? $disk->url($this->image_path) : null;
    }

    public function portfolioItem()
    {
        return $this->belongsTo(PortfolioItem::class);
    }
}

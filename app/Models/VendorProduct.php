<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorProduct extends Model
{
    // HasFactory only enables VendorProduct::factory(), used by the local demo
    // seeder (HaflatiDemoSeeder). No behaviour change — see smart-search.md.
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'name',
        'description',
        'price',
        'meta',
        'is_available',
        'is_hidden',
        'stock',
        'max_delivery_days',
        'low_stock_alerted_at',
        'discount_percent',
        'discount_ends_at',
        'discount_last_ended_at',
    ];

    // deposit_percent is intentionally NOT fillable — the deposit is a fixed
    // platform rule (20%), set by the DB default, not editable by vendors.
    // The discount_* fields ARE fillable, but only the dedicated discount
    // endpoints ever write them (with validation) — product create/update never
    // accept them from the request.
    protected $casts = [
        'meta' => 'array',
        'is_available' => 'boolean',
        'is_hidden' => 'boolean',
        'price' => 'decimal:2',
        'stock' => 'integer',
        'max_delivery_days' => 'integer',
        'low_stock_alerted_at' => 'datetime',
        'discount_percent'       => 'decimal:2',
        'discount_ends_at'       => 'datetime',
        'discount_last_ended_at' => 'datetime',
    ];

    // Expose the derived offer fields in every product JSON, so the app can draw
    // the strikethrough price + "-25%" badge without doing the math itself.
    protected $appends = ['is_on_offer', 'discounted_price'];

    // An offer is live only while it has a percent AND hasn't expired.
    public function getIsOnOfferAttribute(): bool
    {
        return $this->discount_percent !== null
            && $this->discount_ends_at !== null
            && $this->discount_ends_at->isFuture();
    }

    // Price the customer actually pays. Equals the original when no live offer.
    public function getDiscountedPriceAttribute(): ?string
    {
        if (! $this->is_on_offer) {
            return $this->price;
        }

        return number_format(
            (float) $this->price * (1 - (float) $this->discount_percent / 100),
            2, '.', ''
        );
    }

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
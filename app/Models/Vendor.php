<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, Notifiable;

    // Local scope: Vendor::active() returns only non-banned vendors.
    // Used on public/customer-facing queries (browse, search). Admin queries
    // omit it on purpose so admins can still see and manage banned vendors.
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'city',
        'birth_date',
        'business_name',
        'vendor_type',
        'booking_style',
        'vendor_style',
        'profile_image',
        'cover_image',
        'latitude',
        'longitude',
        'address',
        'bio',
        'rating_avg',
        'is_approved',
        'is_active',
        'rejection_reason',
        'fcm_token',
    ];

    protected $hidden = [
        'remember_token',
    ];

    // Full public URLs to the stored images, so the app uses them directly.
    protected $appends = ['profile_image_url', 'cover_image_url'];

    public function getProfileImageUrlAttribute(): ?string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('supabase');

        return $this->profile_image ? $disk->url($this->profile_image) : null;
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('supabase');

        return $this->cover_image ? $disk->url($this->cover_image) : null;
    }

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_active'   => 'boolean',
            'rating_avg'  => 'decimal:2',
            'birth_date'  => 'date',
            'latitude'    => 'decimal:8',
            'longitude'   => 'decimal:8',
        ];
    }

    public function products()
    {
        return $this->hasMany(VendorProduct::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function portfolioItems()
    {
        return $this->hasMany(PortfolioItem::class);
    }
}

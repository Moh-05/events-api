<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'latitude',
        'longitude',
        'address',
        'bio',
        'rating_avg',
        'is_approved',
        'is_active',
        'winding_down',
        'rejection_reason',
        'fcm_token',
    ];

    protected $hidden = [
        'remember_token',
    ];

    // Expose the readable account state in API responses.
    protected $appends = ['account_status'];

    protected function casts(): array
    {
        return [
            'is_approved'  => 'boolean',
            'is_active'    => 'boolean',
            'winding_down' => 'boolean',
            'rating_avg'   => 'decimal:2',
            'birth_date'   => 'date',
            'latitude'     => 'decimal:8',
            'longitude'    => 'decimal:8',
        ];
    }

    // Human-readable state derived from the two flags:
    //   active        → normal
    //   winding_down  → banned, but still finishing existing bookings
    //   banned        → fully blocked
    public function getAccountStatusAttribute(): ?string
    {
        // A column-limited select (vendor:id,business_name) doesn't load the
        // two source flags — computing on their null would fabricate "banned"
        // for a perfectly active vendor. No data → no status.
        if (! array_key_exists('is_active', $this->attributes)
            || ! array_key_exists('winding_down', $this->attributes)) {
            return null;
        }

        if ($this->is_active) {
            return 'active';
        }

        return $this->winding_down ? 'winding_down' : 'banned';
    }

    // Called after a booking ends. If this vendor was winding down and has no
    // more active bookings, the ban becomes final (fully banned).
    public function finalizeBanIfCleared(): void
    {
        if (! $this->winding_down) {
            return;
        }

        $stillHasActive = $this->bookings()
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if (! $stillHasActive) {
            $this->update(['winding_down' => false]); // is_active already false → now fully banned
        }
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

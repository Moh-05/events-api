<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    // HasFactory only enables Vendor::factory(), used by the local demo seeder
    // (HaflatiDemoSeeder). It changes no existing behaviour — see smart-search.md.
    use HasApiTokens, Notifiable, HasFactory;

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
        'winding_down',
        'is_accepting_bookings',
        'rejection_reason',
        'fcm_token',
    ];

    protected $hidden = [
        'remember_token',
        'fcm_token', // device token — never exposed in any API response
    ];

    // profile_image_url -> full public URL, used by the app directly.
    // cover_image_url   -> full public URL of the cover, used by the app directly.
    // account_status    -> readable account state for API responses.
    protected $appends = ['profile_image_url', 'cover_image_url', 'account_status'];

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
            'is_approved'           => 'boolean',
            'is_active'             => 'boolean',
            'winding_down'          => 'boolean',
            'is_accepting_bookings' => 'boolean',
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
    public function getAccountStatusAttribute(): string
    {
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

    public function blockedDates()
    {
        return $this->hasMany(VendorBlockedDate::class);
    }
}

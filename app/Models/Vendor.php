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

    // A freshly registered vendor is is_approved=false → it lands in the KYC
    // queue. Ping the reviewers (both roles do KYC) via the admin bell. Hooked
    // on the model so the vendor registration flow stays untouched.
    protected static function booted(): void
    {
        static::created(function (Vendor $vendor): void {
            if (! $vendor->is_approved) {
                app(\App\Services\NotificationService::class)->notifyAdmins(
                    ['super_admin', 'support'],
                    'New vendor awaiting KYC',
                    trim(($vendor->business_name ?: "{$vendor->first_name} {$vendor->last_name}"))
                        . ' registered and needs review.',
                    ['type' => 'vendor_kyc', 'vendor_id' => (string) $vendor->id]
                );
            }
        });
    }

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
        'avg_response_minutes',
        'response_count',
        'is_approved',
        'is_active',
        'winding_down',
        'is_accepting_bookings',
        'shamcash_account',
        'rejection_reason',
        'fcm_token',
        'language',
    ];

    protected $hidden = [
        'remember_token',
        'fcm_token', // device token — never exposed in any API response
    ];

    // profile_image_url -> full public URL, used by the app directly.
    // cover_image_url   -> full public URL of the cover, used by the app directly.
    // account_status    -> readable account state for API responses.
    protected $appends = ['profile_image_url', 'cover_image_url', 'account_status', 'response_time'];

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
            'avg_response_minutes' => 'integer',
            'response_count'       => 'integer',
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

    // How quickly this vendor answers a paid booking, as a moderated range
    // ("usually replies in 1-2 hours") rather than an exact number, which
    // would be misleading from a small sample.
    //
    // Read straight off the stored columns, so this costs nothing on a browse
    // list of 15 vendors. Maintained by BookingController::touchResponseTime()
    // whenever the vendor approves or declines.
    //
    // Shown to BOTH sides: the vendor sees their own on their dashboard, and
    // customers see it on every vendor card and profile.
    public function getResponseTimeAttribute(): ?array
    {
        // A column-limited select that didn't load these can't compute — return
        // null rather than fabricate "New" for a vendor who answers instantly.
        if (! array_key_exists('avg_response_minutes', $this->attributes)) {
            return null;
        }

        $minutes = $this->attributes['avg_response_minutes'];

        if ($minutes === null) {
            return [
                'is_new'          => true, // never answered a paid booking yet
                'label'           => null,
                'average_minutes' => null,
                'based_on'        => 0,
            ];
        }

        return [
            'is_new'          => false,
            'label'           => self::responseTimeLabel((float) $minutes),
            'average_minutes' => (int) $minutes,
            'based_on'        => (int) ($this->attributes['response_count'] ?? 0),
        ];
    }

    // Moderates a raw minute average into a human range.
    public static function responseTimeLabel(float $minutes): string
    {
        return match (true) {
            $minutes < 30   => 'under 30 minutes',
            $minutes < 60   => '30-60 minutes',
            $minutes < 120  => '1-2 hours',
            $minutes < 360  => '2-6 hours',
            $minutes < 1440 => '6-24 hours',
            default         => 'over a day',
        };
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

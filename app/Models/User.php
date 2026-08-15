<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'city',
        'birth_date',
        'profile_image',
        'latitude',
        'longitude',
        'address',
        'fcm_token',
        'language',
        'is_active',
    ];

    protected $hidden = [
        'remember_token',
    ];

    // Full public URL to the profile image, so the app uses it directly.
    protected $appends = ['profile_image_url'];

    public function getProfileImageUrlAttribute(): ?string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('supabase');

        return $this->profile_image ? $disk->url($this->profile_image) : null;
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'latitude'   => 'decimal:8',
            'longitude'  => 'decimal:8',
            'is_active'  => 'boolean',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function savedItems()
    {
        return $this->hasMany(SavedItem::class);
    }
}

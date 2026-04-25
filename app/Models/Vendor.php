<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Vendor extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'business_name',
        'vendor_type',
        'booking_style',
        'profile_image',
        'location',
        'bio',
        'rating_avg',
        'is_approved',
        'is_active',
        'birth_date',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_active'   => 'boolean',
            'rating_avg'  => 'decimal:2',
            'birth_date' => 'date',
        ];
    }

    // علاقة الـ products
   public function products()
    {
        return $this->hasMany(VendorProduct::class);
    }

    // علاقة الـ bookings
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
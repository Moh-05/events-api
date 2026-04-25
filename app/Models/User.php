<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // ضروري للـ Token

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * الحقول التي يسمح بتعبئتها (Mass Assignable)
     */
    protected $fillable = [
        'name',
        'phone',
        'birth_date',
        'profile_image',
        'location'
        // 'password', // اتركها فقط إذا كنت ستستخدم باسوورد لليوزر لاحقاً
    ];

    /**
     * الحقول المخفية عند تحويل الموديل لـ JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * تحويل أنواع البيانات (Casting)
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date', // ليتعامل معها لارافيل كـ Carbon object
            'password' => 'hashed',
        ];
    }
}
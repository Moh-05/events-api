<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Vendor extends Model
{
    use HasFactory, Notifiable;

    /**
     * الحقول التي يسمح بتعبئتها (Mass Assignable)
     */
    protected $fillable = [
        'name',
        'phone',
        'user_id',      // لربط البائع بحساب المستخدم الخاص به
        'store_name',
        'description',
    ];

    /**
     * الحقول المخفية عند تحويل الموديل لـ JSON
     */
    protected $hidden = [
        // أضف الحقول الحساسة هنا إذا وجدت
    ];

    /**
     * تحويل أنواع البيانات (Casting)
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            // يمكنك إضافة أي تحويلات أخرى هنا، مثل:
            // 'is_active' => 'boolean',
        ];
    }
}
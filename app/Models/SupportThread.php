<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportThread extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'booking_id',
        'subject',
        'category',
        'status',
        'handled_by',
        'last_message_at',
        'resolved_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'resolved_at'     => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function handler()
    {
        return $this->belongsTo(Admin::class, 'handled_by');
    }

    // The user or vendor who owns this thread (plain lookup — owner_type is a
    // 'user'|'vendor' string, same convention as the notifications table).
    public function owner(): User|Vendor|null
    {
        return $this->owner_type === 'vendor'
            ? Vendor::find($this->owner_id)
            : User::find($this->owner_id);
    }
}

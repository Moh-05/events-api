<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    // The single most recent message — cheap to eager-load for the chat LIST
    // preview ("last message" line) without pulling every message.
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}

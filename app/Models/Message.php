<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}

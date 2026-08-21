<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// One entry in a customer's address book ("Home", "Work", ...). Separate from
// the single latitude/longitude/address on the users table, which is the
// profile's own location.
class SavedAddress extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'latitude',
        'longitude',
        'address',
        'details',
        'is_default',
    ];

    protected $casts = [
        'latitude'   => 'decimal:8',
        'longitude'  => 'decimal:8',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

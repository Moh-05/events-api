<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentReport extends Model
{
    protected $fillable = [
        'reporter_type',
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'status',
    ];
}

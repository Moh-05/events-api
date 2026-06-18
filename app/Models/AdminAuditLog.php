<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    // Convenience helper used by admin actions to record what happened.
    public static function record(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, array $meta = []): void
    {
        static::create([
            'admin_id'    => $adminId,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'meta'        => $meta ?: null,
        ]);
    }
}

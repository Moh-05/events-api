<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'title',
        'body',
        'title_key',
        'body_key',
        'params',
        'data',
        'read_at',
    ];

    protected $casts = [
        'params'  => 'array',
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    // The inbox row is stored with its translation KEY, so the same row can be
    // read back in Arabic or English depending on the request's locale. The
    // stored title/body are the fallback: rows created before the keys existed,
    // and notifications that never had a key (e.g. a chat push).
    //
    // Called by NotificationController when listing, so the app always gets the
    // history in the language it is currently running in — switching language
    // no longer leaves old notifications frozen in the previous one.
    public function localized(?string $locale = null): static
    {
        $locale = $locale === 'en' ? 'en' : 'ar';

        if ($this->title_key) {
            $this->title = __($this->title_key, $this->params ?? [], $locale);
        }

        if ($this->body_key) {
            $this->body = __($this->body_key, $this->params ?? [], $locale);
        }

        return $this;
    }
}

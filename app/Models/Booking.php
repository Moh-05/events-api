<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'vendor_product_id',
        'booking_style',
        'status',
        'event_date',
        'old_event_date',
        'event_type',
        'event_location',
        'duration_hours',
        'details',
        'delivery_date',
        'delivery_address',
        'notes',
        'price_agreed',
        'selected_options',
        'responded_at',
        'refund_amount',
        'refund_paid_at',
        'refund_waived_at',
    ];

    protected function casts(): array
    {
        return [
            'details'          => 'array',
            'selected_options' => 'array',
            'event_date'     => 'datetime',
            'delivery_date'    => 'datetime',
            'old_event_date'   => 'datetime',
            'price_agreed'     => 'decimal:2',
            'refund_amount'    => 'decimal:2',
            'refund_paid_at'   => 'datetime',
            'responded_at'     => 'datetime',
            'refund_waived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // When a booking is cancelled or declined, free its calendar slot for
        // rebooking: move event_date into old_event_date (kept for display) and
        // null event_date — which also nulls the generated event_day, dropping
        // this row out of the (vendor_id, event_day) unique index.
        static::saving(function (Booking $booking): void {
            if ($booking->isDirty('status')
                && in_array($booking->status, ['cancelled', 'declined'], true)
                && $booking->event_date !== null) {
                $booking->old_event_date = $booking->event_date;
                $booking->event_date     = null;
            }
        });

        // When a booking reaches a terminal state, a winding-down vendor may have
        // just cleared their last obligation → finalize the ban automatically.
        // NOTE: bulk updates (e.g. bookings:auto-complete) bypass model events,
        // so that command finalizes winding-down vendors itself.
        static::updated(function (Booking $booking): void {
            if ($booking->wasChanged('status')
                && in_array($booking->status, ['completed', 'cancelled', 'declined'], true)) {
                $booking->vendor?->finalizeBanIfCleared();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function product()
    {
        return $this->belongsTo(VendorProduct::class, 'vendor_product_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}

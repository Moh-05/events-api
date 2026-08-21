<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validation for creating an order booking (seller vendors, cart-style).
// items[] is the only shape — even a single product is a one-item list.
// Appointment fields are rejected outright.
class StoreOrderBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:sanctum already
    }

    public function rules(): array
    {
        return [
            'items'                     => 'required|array|min:1',
            'items.*.vendor_product_id' => 'required|exists:vendor_products,id',
            'items.*.quantity'          => 'sometimes|integer|min:1',
            'items.*.selected_options'  => 'sometimes|nullable|array', // customer's picks per item, from the product meta
            'notes'                     => 'sometimes|nullable|string',
            'details'                   => 'sometimes|nullable|array',
            // delivery_date is NO LONGER customer input — the vendor sets a
            // max_delivery_days policy on each product, and the backend
            // computes the real delivery_date from that at order time.
            // See BookingController::deriveDeliveryDate().
            'delivery_date'             => 'prohibited',
            'delivery_address'          => 'sometimes|nullable|string',
            // Optionally book to one of the customer's saved addresses. The
            // controller SNAPSHOTS its text + coordinates onto the booking —
            // the booking never references the saved row, so editing or
            // deleting it later can't rewrite a past order.
            'saved_address_id'          => 'sometimes|nullable|integer',
            // A one-off pin, for a customer who didn't save the place.
            'location_latitude'         => 'sometimes|nullable|numeric|between:-90,90',
            'location_longitude'        => 'sometimes|nullable|numeric|between:-180,180',

            // Appointment fields don't belong on an order.
            'event_date'     => 'prohibited',
            'event_location' => 'prohibited',
            'duration_hours' => 'prohibited',
        ];
    }
}

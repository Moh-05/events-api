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
            'notes'                     => 'sometimes|nullable|string',
            'details'                   => 'sometimes|nullable|array',
            'delivery_date'             => 'sometimes|nullable|date',
            'delivery_address'          => 'sometimes|nullable|string',

            // Appointment fields don't belong on an order.
            'event_date'     => 'prohibited',
            'event_location' => 'prohibited',
            'duration_hours' => 'prohibited',
        ];
    }
}

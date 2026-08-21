<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validation for creating an appointment booking (service vendors).
// One package (vendor_product_id), quantity is always 1.
// Cart/delivery fields are rejected outright.
class StoreAppointmentBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is behind auth:sanctum already
    }

    public function rules(): array
    {
        return [
            'vendor_product_id' => 'required|exists:vendor_products,id',
            'notes'             => 'sometimes|nullable|string',
            'selected_options'  => 'sometimes|nullable|array', // customer's picks from the product meta
            'event_date'        => 'required|date|after:now',
            'event_location'    => 'sometimes|nullable|string',
            // Same as the order shape: optionally point at a saved address and
            // the controller snapshots it, or drop a one-off pin.
            'saved_address_id'   => 'sometimes|nullable|integer',
            'location_latitude'  => 'sometimes|nullable|numeric|between:-90,90',
            'location_longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'duration_hours'    => 'sometimes|nullable|integer',

            // Order fields don't belong on an appointment.
            'items'            => 'prohibited',
            'details'          => 'prohibited',
            'delivery_date'    => 'prohibited',
            'delivery_address' => 'prohibited',
        ];
    }
}

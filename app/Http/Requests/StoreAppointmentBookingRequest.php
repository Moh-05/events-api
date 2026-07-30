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
            'event_date'        => 'required|date|after:now',
            'event_location'    => 'sometimes|nullable|string',
            'duration_hours'    => 'sometimes|nullable|integer',

            // Order fields don't belong on an appointment.
            'items'            => 'prohibited',
            'details'          => 'prohibited',
            'delivery_date'    => 'prohibited',
            'delivery_address' => 'prohibited',
        ];
    }
}

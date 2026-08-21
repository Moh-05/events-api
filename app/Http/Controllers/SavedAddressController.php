<?php

namespace App\Http\Controllers;

use App\Models\SavedAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// The customer's address book. Lets them save a place once ("Home", "Work")
// and reuse it later — to sort Explore by distance from it, or to fill in
// where an order should be delivered / a service performed.
//
// The map pin is the source of truth: the customer picks a spot, Flutter
// reverse-geocodes it into readable text, and both are stored. lat/lng drives
// distance; `address` is what the vendor actually reads.
//
// Bookings do NOT reference these rows — they copy the values at booking time
// (see BookingController), so editing or deleting a saved address never
// rewrites what a past booking agreed to.
class SavedAddressController extends Controller
{
    private const RULES = [
        'label'     => 'required|string|max:60',
        'latitude'  => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'address'   => 'required|string|max:255',
        'details'   => 'sometimes|nullable|string|max:255',
        'is_default' => 'sometimes|boolean',
    ];

    // All of the caller's saved addresses, default first then newest.
    public function index(Request $request)
    {
        return response()->json([
            'status'    => 'success',
            'addresses' => $request->user()->savedAddresses,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(self::RULES);
        $user = $request->user();

        // The very first address a customer saves becomes their default
        // automatically — otherwise they'd have a list with nothing selected.
        $isFirst = ! $user->savedAddresses()->exists();

        $address = DB::transaction(function () use ($user, $data, $isFirst) {
            $makeDefault = $isFirst || ($data['is_default'] ?? false);

            if ($makeDefault) {
                $this->clearDefault($user->id);
            }

            return $user->savedAddresses()->create([
                'label'      => $data['label'],
                'latitude'   => $data['latitude'],
                'longitude'  => $data['longitude'],
                'address'    => $data['address'],
                'details'    => $data['details'] ?? null,
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'address' => $address,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        // Same rules, but every field optional on an edit.
        $data = $request->validate(
            collect(self::RULES)
                ->map(fn ($rule) => str_replace('required', 'sometimes', $rule))
                ->all()
        );

        $user    = $request->user();
        $address = $user->savedAddresses()->findOrFail($id);

        DB::transaction(function () use ($user, $address, $data) {
            if ($data['is_default'] ?? false) {
                $this->clearDefault($user->id);
                $data['is_default'] = true;
            }

            $address->update($data);
        });

        return response()->json([
            'status'  => 'success',
            'address' => $address->refresh(),
        ]);
    }

    // Promote one address to be the default (the one the app preselects).
    public function setDefault(Request $request, int $id)
    {
        $user    = $request->user();
        $address = $user->savedAddresses()->findOrFail($id);

        DB::transaction(function () use ($user, $address) {
            $this->clearDefault($user->id);
            $address->update(['is_default' => true]);
        });

        return response()->json([
            'status'  => 'success',
            'address' => $address->refresh(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $user    = $request->user();
        $address = $user->savedAddresses()->findOrFail($id);
        $wasDefault = $address->is_default;

        DB::transaction(function () use ($user, $address, $wasDefault) {
            $address->delete();

            // Deleting the default shouldn't leave the customer with none —
            // promote whichever address is now newest.
            if ($wasDefault) {
                $next = $user->savedAddresses()->first();
                $next?->update(['is_default' => true]);
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.address_deleted'),
        ]);
    }

    // Exactly one default per customer — enforced here rather than by a DB
    // constraint, since "at most one true per user" isn't a unique index.
    private function clearDefault(int $userId): void
    {
        SavedAddress::where('user_id', $userId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}

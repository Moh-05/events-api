<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BanEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function makeVendor(bool $active = true): Vendor
    {
        static $n = 0;
        $n++;
        return Vendor::create([
            'phone'         => '09000000' . $n,
            'business_name' => 'Vendor ' . $n,
            'vendor_type'   => 'photographer',
            'booking_style' => 'appointment',
            'is_approved'   => true,
            'is_active'     => $active,
        ]);
    }

    private function makeUser(): User
    {
        static $n = 0;
        $n++;
        return User::create([
            'first_name' => 'User',
            'last_name'  => (string) $n,
            'phone'      => '09111111' . $n,
            'is_active'  => true,
        ]);
    }

    // ── Usage enforcement (middleware) ────────────────────

    public function test_banned_vendor_cannot_use_protected_route(): void
    {
        $vendor = $this->makeVendor(active: false);

        $this->actingAs($vendor, 'vendors')
            ->getJson('/api/vendor/profile')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account has been suspended. Please contact support.');
    }

    public function test_active_vendor_can_use_protected_route(): void
    {
        $vendor = $this->makeVendor(active: true);

        $this->actingAs($vendor, 'vendors')
            ->getJson('/api/vendor/profile')
            ->assertOk();
    }

    public function test_banned_user_cannot_use_protected_route(): void
    {
        $user = $this->makeUser();
        $user->update(['is_active' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile')
            ->assertStatus(403);
    }

    // ── Hiding the vendor's work from customers ───────────

    public function test_banned_vendor_products_hidden_from_search(): void
    {
        $vendor = $this->makeVendor(active: false);
        VendorProduct::create(['vendor_id' => $vendor->id, 'name' => 'Gold Package', 'price' => 100]);

        $res = $this->getJson("/api/vendors/{$vendor->id}/products/search");

        $res->assertOk()
            ->assertJsonPath('note', 'Vendor unavailable')
            ->assertJsonCount(0, 'products');
    }

    public function test_active_vendor_products_visible_in_search(): void
    {
        $vendor = $this->makeVendor(active: true);
        VendorProduct::create(['vendor_id' => $vendor->id, 'name' => 'Gold Package', 'price' => 100]);

        $this->getJson("/api/vendors/{$vendor->id}/products/search")
            ->assertOk()
            ->assertJsonCount(1, 'products');
    }

    public function test_cannot_book_a_banned_vendor(): void
    {
        $vendor  = $this->makeVendor(active: false);
        $product = VendorProduct::create(['vendor_id' => $vendor->id, 'name' => 'Gold Package', 'price' => 100]);
        $user    = $this->makeUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', ['vendor_product_id' => $product->id])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This vendor is currently unavailable');
    }

    // ── Reversibility: unban restores everything ──────────

    public function test_unbanning_restores_access_and_data(): void
    {
        $vendor  = $this->makeVendor(active: false);
        $product = VendorProduct::create(['vendor_id' => $vendor->id, 'name' => 'Gold Package', 'price' => 100]);

        // While banned: blocked + product hidden, but data still in DB.
        $this->actingAs($vendor, 'vendors')->getJson('/api/vendor/profile')->assertStatus(403);
        $this->assertDatabaseHas('vendor_products', ['id' => $product->id]); // data preserved

        // Unban (flip the flag back).
        $vendor->update(['is_active' => true]);

        // Everything works again.
        $this->actingAs($vendor->fresh(), 'vendors')->getJson('/api/vendor/profile')->assertOk();
        $this->getJson("/api/vendors/{$vendor->id}/products/search")->assertJsonCount(1, 'products');
    }
}

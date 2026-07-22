<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Booking;
use App\Models\PortfolioItem;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFullTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): Admin
    {
        return Admin::create(['name' => 'Super', 'email' => 'super'.rand(1, 1e6).'@h.com', 'password' => 'secret123', 'role' => 'super_admin']);
    }

    private function support(): Admin
    {
        return Admin::create(['name' => 'Sup', 'email' => 'sup'.rand(1, 1e6).'@h.com', 'password' => 'secret123', 'role' => 'support']);
    }

    private function makeVendor(array $o = []): Vendor
    {
        return Vendor::create(array_merge([
            'phone' => '09'.rand(10000000, 99999999), 'business_name' => 'V'.rand(1, 1e6),
            'vendor_type' => 'photographer', 'booking_style' => 'appointment',
            'is_approved' => true, 'is_active' => true,
        ], $o));
    }

    private function makeUser(): User
    {
        return User::create(['first_name' => 'U', 'last_name' => (string) rand(1, 1e6), 'phone' => '09'.rand(10000000, 99999999), 'is_active' => true]);
    }

    private function makeProduct(Vendor $v): VendorProduct
    {
        return VendorProduct::create(['vendor_id' => $v->id, 'name' => 'Pkg', 'price' => 100000]);
    }

    private function booking(Vendor $v, User $u, VendorProduct $p, string $status): Booking
    {
        return Booking::create([
            'vendor_id' => $v->id, 'user_id' => $u->id, 'vendor_product_id' => $p->id,
            'status' => $status, 'booking_style' => 'appointment',
        ]);
    }

    private function credit(Booking $b, float $amount): void
    {
        WalletTransaction::create(['vendor_id' => $b->vendor_id, 'booking_id' => $b->id, 'type' => 'credit', 'amount' => $amount]);
    }

    // ── Role split ────────────────────────────────────────

    public function test_support_can_view_and_do_kyc(): void
    {
        $sup = $this->support();
        $v   = $this->makeVendor(['is_approved' => false]);

        $this->actingAs($sup, 'admins')->getJson('/api/admin/dashboard')->assertOk();
        $this->actingAs($sup, 'admins')->getJson('/api/admin/vendors')->assertOk();
        $this->actingAs($sup, 'admins')->getJson('/api/admin/reviews')->assertOk();
        $this->actingAs($sup, 'admins')->postJson("/api/admin/vendors/{$v->id}/approve")->assertOk();
    }

    public function test_support_is_blocked_from_super_admin_actions(): void
    {
        $sup = $this->support();
        $v   = $this->makeVendor();

        $this->actingAs($sup, 'admins')->postJson("/api/admin/vendors/{$v->id}/ban")->assertStatus(403);
        $this->actingAs($sup, 'admins')->postJson("/api/admin/vendors/{$v->id}/ban-gradual")->assertStatus(403);
        $this->actingAs($sup, 'admins')->postJson("/api/admin/vendors/{$v->id}/unban")->assertStatus(403);
        $this->actingAs($sup, 'admins')->getJson("/api/admin/vendors/{$v->id}/wallet")->assertStatus(403);
        $this->actingAs($sup, 'admins')->getJson('/api/admin/payments')->assertStatus(403);
        $this->actingAs($sup, 'admins')->getJson('/api/admin/audit-logs')->assertStatus(403);
        $this->actingAs($sup, 'admins')->getJson('/api/admin/admins')->assertStatus(403);
        $this->actingAs($sup, 'admins')->deleteJson('/api/admin/products/1')->assertStatus(403);
        $this->actingAs($sup, 'admins')->deleteJson('/api/admin/portfolio/1')->assertStatus(403);
    }

    // ── Immediate ban ─────────────────────────────────────

    public function test_immediate_ban_cancels_and_refunds_all_active_bookings(): void
    {
        $v = $this->makeVendor();
        $u = $this->makeUser();
        $p = $this->makeProduct($v);
        $pending  = $this->booking($v, $u, $p, 'pending');
        $approved = $this->booking($v, $u, $p, 'approved');
        $this->credit($approved, 50000);

        $this->actingAs($this->superAdmin(), 'admins')
            ->postJson("/api/admin/vendors/{$v->id}/ban", ['reason' => 'fraud'])
            ->assertOk()
            ->assertJsonPath('account_status', 'banned')
            ->assertJsonPath('cancelled_bookings', 2);

        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('cancelled', $approved->fresh()->status);
        // Credit reversed → net 0 in the ledger.
        $this->assertEqualsWithDelta(0, WalletTransaction::where('booking_id', $approved->id)->sum('amount'), 0.01);
    }

    // ── Gradual ban ───────────────────────────────────────

    public function test_gradual_ban_refunds_pending_keeps_approved_then_auto_finalizes(): void
    {
        $v = $this->makeVendor();
        $u = $this->makeUser();
        $p = $this->makeProduct($v);
        $pending  = $this->booking($v, $u, $p, 'pending');
        $approved = $this->booking($v, $u, $p, 'approved');
        $this->credit($approved, 50000);

        $this->actingAs($this->superAdmin(), 'admins')
            ->postJson("/api/admin/vendors/{$v->id}/ban-gradual")
            ->assertOk()
            ->assertJsonPath('account_status', 'winding_down')
            ->assertJsonPath('active_bookings', 1);

        $this->assertSame('cancelled', $pending->fresh()->status);   // pending refunded
        $this->assertSame('approved', $approved->fresh()->status);   // commitment kept
        $this->assertSame('winding_down', $v->fresh()->account_status);

        // Vendor finishes the last obligation → auto fully banned.
        $approved->update(['status' => 'completed']);
        $this->assertSame('banned', $v->fresh()->account_status);
    }

    public function test_gradual_ban_with_no_commitments_bans_immediately(): void
    {
        $v = $this->makeVendor();

        $this->actingAs($this->superAdmin(), 'admins')
            ->postJson("/api/admin/vendors/{$v->id}/ban-gradual")
            ->assertOk()
            ->assertJsonPath('account_status', 'banned');
    }

    // ── Winding-down access ───────────────────────────────

    public function test_winding_down_vendor_can_still_use_panel_but_banned_cannot(): void
    {
        $winding = $this->makeVendor(['is_active' => false, 'winding_down' => true]);
        $banned  = $this->makeVendor(['is_active' => false, 'winding_down' => false]);

        $this->actingAs($winding, 'vendors')->getJson('/api/vendor/profile')->assertOk();
        $this->actingAs($banned, 'vendors')->getJson('/api/vendor/profile')->assertStatus(403);
    }

    // ── Dispute resolution ────────────────────────────────

    public function test_dispute_cancels_one_booking_and_keeps_vendor_active(): void
    {
        $v = $this->makeVendor();
        $u = $this->makeUser();
        $p = $this->makeProduct($v);
        $b = $this->booking($v, $u, $p, 'approved');
        $this->credit($b, 50000);

        $this->actingAs($this->superAdmin(), 'admins')
            ->postJson("/api/admin/bookings/{$b->id}/cancel", ['reason' => 'no-show'])
            ->assertOk()
            ->assertJsonPath('refund_percent', 100);

        $this->assertSame('cancelled', $b->fresh()->status);
        $this->assertTrue($v->fresh()->is_active); // vendor untouched
    }

    public function test_dispute_rejects_a_finished_booking(): void
    {
        $v = $this->makeVendor();
        $u = $this->makeUser();
        $p = $this->makeProduct($v);
        $b = $this->booking($v, $u, $p, 'completed');

        $this->actingAs($this->superAdmin(), 'admins')
            ->postJson("/api/admin/bookings/{$b->id}/cancel", ['reason' => 'x'])
            ->assertStatus(422);
    }

    // ── Unban ─────────────────────────────────────────────

    public function test_unban_reinstates_a_vendor(): void
    {
        $v = $this->makeVendor(['is_active' => false, 'winding_down' => true]);

        $this->actingAs($this->superAdmin(), 'admins')
            ->postJson("/api/admin/vendors/{$v->id}/unban")
            ->assertOk()
            ->assertJsonPath('account_status', 'active');

        $this->assertTrue($v->fresh()->is_active);
        $this->assertFalse($v->fresh()->winding_down);
    }

    // ── Search / details ──────────────────────────────────

    public function test_vendor_search_filters_by_name(): void
    {
        $this->makeVendor(['business_name' => 'Sunrise Studio']);
        $this->makeVendor(['business_name' => 'Moonlight Hall']);

        $res = $this->actingAs($this->superAdmin(), 'admins')->getJson('/api/admin/vendors?search=Sunrise');
        $res->assertOk()->assertJsonCount(1, 'vendors.data');
    }

    public function test_user_detail_includes_their_bookings(): void
    {
        $v = $this->makeVendor();
        $u = $this->makeUser();
        $p = $this->makeProduct($v);
        $this->booking($v, $u, $p, 'approved');

        $this->actingAs($this->superAdmin(), 'admins')
            ->getJson("/api/admin/users/{$u->id}")
            ->assertOk()
            ->assertJsonCount(1, 'user.bookings');
    }

    public function test_booking_detail_includes_payment_relation(): void
    {
        $v = $this->makeVendor();
        $u = $this->makeUser();
        $p = $this->makeProduct($v);
        $b = $this->booking($v, $u, $p, 'approved');

        $this->actingAs($this->superAdmin(), 'admins')
            ->getJson("/api/admin/bookings/{$b->id}")
            ->assertOk()
            ->assertJsonPath('booking.id', $b->id);
    }

    // ── Moderation ────────────────────────────────────────

    public function test_delete_review_recomputes_rating(): void
    {
        $v = $this->makeVendor(['rating_avg' => 5]);
        $u = $this->makeUser();
        $p = $this->makeProduct($v);
        $b = $this->booking($v, $u, $p, 'completed');
        $review = Review::create(['booking_id' => $b->id, 'user_id' => $u->id, 'vendor_id' => $v->id, 'rating' => 5]);

        $this->actingAs($this->superAdmin(), 'admins')
            ->deleteJson("/api/admin/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertEquals(0, $v->fresh()->rating_avg); // no reviews left → 0
    }

    public function test_delete_product_and_portfolio(): void
    {
        $v = $this->makeVendor();
        $product   = $this->makeProduct($v);
        $portfolio = PortfolioItem::create(['vendor_id' => $v->id, 'title' => 'Bad']);
        $admin     = $this->superAdmin();

        $this->actingAs($admin, 'admins')->deleteJson("/api/admin/products/{$product->id}")->assertOk();
        $this->actingAs($admin, 'admins')->deleteJson("/api/admin/portfolio/{$portfolio->id}")->assertOk();

        $this->assertDatabaseMissing('vendor_products', ['id' => $product->id]);
        $this->assertDatabaseMissing('portfolio_items', ['id' => $portfolio->id]);
    }

    // ── Refunds due & withdrawals oversight ───────────────

    public function test_platform_cancel_records_a_refund_due_and_can_be_marked_paid(): void
    {
        $v = $this->makeVendor();
        $u = $this->makeUser();
        $p = $this->makeProduct($v);
        $b = $this->booking($v, $u, $p, 'pending');
        // Customer paid a 20,000 deposit.
        \App\Models\Payment::create([
            'booking_id' => $b->id, 'amount_paid' => 20000, 'commission' => 4000,
            'vendor_payout' => 16000, 'currency' => 'SYP', 'transaction_id' => 't'.rand(1, 1e6),
            'sender_name' => 'x', 'status' => 'verified',
        ]);
        $admin = $this->superAdmin();

        // Dispute-cancel it → customer owed a full refund.
        $this->actingAs($admin, 'admins')->postJson("/api/admin/bookings/{$b->id}/cancel", ['reason' => 'x'])->assertOk();

        $this->actingAs($admin, 'admins')->getJson('/api/admin/refunds-due')
            ->assertOk()
            ->assertJsonPath('total_due', 20000)
            ->assertJsonCount(1, 'refunds.data');

        // Admin pays it out and marks it → drops off the list.
        $this->actingAs($admin, 'admins')->postJson("/api/admin/refunds/{$b->id}/mark-paid")->assertOk();
        $this->actingAs($admin, 'admins')->getJson('/api/admin/refunds-due')
            ->assertOk()
            ->assertJsonPath('total_due', 0)
            ->assertJsonCount(0, 'refunds.data');
    }

    public function test_withdrawals_oversight_lists_and_marks_paid(): void
    {
        $v = $this->makeVendor();
        $w = WalletTransaction::create(['vendor_id' => $v->id, 'booking_id' => null, 'type' => 'withdrawal', 'amount' => -30000]);
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'admins')->getJson('/api/admin/withdrawals?unpaid=1')
            ->assertOk()
            ->assertJsonPath('total_unpaid', 30000)
            ->assertJsonCount(1, 'withdrawals.data');

        $this->actingAs($admin, 'admins')->postJson("/api/admin/withdrawals/{$w->id}/mark-paid")->assertOk();
        $this->assertNotNull($w->fresh()->paid_at);

        $this->actingAs($admin, 'admins')->getJson('/api/admin/withdrawals?unpaid=1')
            ->assertOk()
            ->assertJsonPath('total_unpaid', 0);
    }

    public function test_support_blocked_from_money_oversight(): void
    {
        $sup = $this->support();
        $this->actingAs($sup, 'admins')->getJson('/api/admin/refunds-due')->assertStatus(403);
        $this->actingAs($sup, 'admins')->getJson('/api/admin/withdrawals')->assertStatus(403);
    }

    // ── Vendor wallet oversight + escrow ──────────────────

    public function test_vendor_wallet_shows_escrowed_vs_available(): void
    {
        $v = $this->makeVendor();
        $u = $this->makeUser();
        $p = $this->makeProduct($v);

        $held = $this->booking($v, $u, $p, 'approved');   // in progress → pending
        $this->credit($held, 30000);
        $done = $this->booking($v, $u, $p, 'completed');  // delivered → available
        $this->credit($done, 20000);

        $res = $this->actingAs($this->superAdmin(), 'admins')->getJson("/api/admin/vendors/{$v->id}/wallet");
        $res->assertOk()
            ->assertJsonPath('wallet.available_balance', 20000)
            ->assertJsonPath('wallet.pending_clearance', 30000);
    }
}

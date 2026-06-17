<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSystemTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): Admin
    {
        return Admin::create([
            'name'     => 'Super',
            'email'    => 'super@haflati.com',
            'password' => 'secret123',
            'role'     => 'super_admin',
        ]);
    }

    private function support(): Admin
    {
        return Admin::create([
            'name'     => 'Support',
            'email'    => 'support@haflati.com',
            'password' => 'secret123',
            'role'     => 'support',
        ]);
    }

    private function makeVendor(array $overrides = []): Vendor
    {
        static $n = 0;
        $n++;
        return Vendor::create(array_merge([
            'phone'         => '09000000' . $n,
            'business_name' => 'Vendor ' . $n,
            'vendor_type'   => 'photographer',
            'booking_style' => 'appointment',
            'is_approved'   => false,
            'is_active'     => true,
        ], $overrides));
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

    // ── Auth ──────────────────────────────────────────────

    public function test_login_succeeds_with_correct_credentials(): void
    {
        $this->superAdmin();

        $res = $this->postJson('/api/admin/login', [
            'email'    => 'super@haflati.com',
            'password' => 'secret123',
        ]);

        $res->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['token', 'admin' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_normalizes_email_case_and_spaces(): void
    {
        $this->superAdmin();

        $res = $this->postJson('/api/admin/login', [
            'email'    => '  Super@Haflati.COM ',
            'password' => 'secret123',
        ]);

        $res->assertOk()->assertJsonPath('status', 'success');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->superAdmin();

        $this->postJson('/api/admin/login', [
            'email'    => 'super@haflati.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    public function test_login_records_last_login_at(): void
    {
        $admin = $this->superAdmin();
        $this->assertNull($admin->last_login_at);

        $this->postJson('/api/admin/login', [
            'email'    => 'super@haflati.com',
            'password' => 'secret123',
        ])->assertOk();

        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    // ── Role split ────────────────────────────────────────

    public function test_support_can_view_dashboard(): void
    {
        $this->actingAs($this->support(), 'admins')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['stats' => ['total_users', 'total_vendors', 'approved_vendors', 'pending_vendors']]);
    }

    public function test_support_can_approve_kyc(): void
    {
        $vendor = $this->makeVendor();

        $this->actingAs($this->support(), 'admins')
            ->postJson("/api/admin/vendors/{$vendor->id}/approve")
            ->assertOk();

        $this->assertTrue($vendor->fresh()->is_approved);
    }

    public function test_support_cannot_ban_vendor(): void
    {
        $vendor = $this->makeVendor(['is_active' => true]);

        $this->actingAs($this->support(), 'admins')
            ->postJson("/api/admin/vendors/{$vendor->id}/toggle")
            ->assertStatus(403);

        // still active — the ban was blocked
        $this->assertTrue($vendor->fresh()->is_active);
    }

    public function test_support_cannot_view_payments(): void
    {
        $this->actingAs($this->support(), 'admins')
            ->getJson('/api/admin/payments')
            ->assertStatus(403);
    }

    public function test_support_cannot_access_audit_logs_or_manage_admins(): void
    {
        $support = $this->support();

        $this->actingAs($support, 'admins')->getJson('/api/admin/audit-logs')->assertStatus(403);
        $this->actingAs($support, 'admins')->getJson('/api/admin/admins')->assertStatus(403);
    }

    // ── Reject vs Ban separation ──────────────────────────

    public function test_reject_kyc_stores_reason_and_does_not_ban(): void
    {
        $vendor = $this->makeVendor(['is_active' => true, 'is_approved' => false]);

        $this->actingAs($this->support(), 'admins')
            ->postJson("/api/admin/vendors/{$vendor->id}/reject", ['reason' => 'Documents unclear'])
            ->assertOk();

        $fresh = $vendor->fresh();
        $this->assertFalse($fresh->is_approved);                 // KYC rejected
        $this->assertTrue($fresh->is_active);                    // NOT banned
        $this->assertSame('Documents unclear', $fresh->rejection_reason);
    }

    public function test_reject_requires_a_reason(): void
    {
        $vendor = $this->makeVendor();

        $this->actingAs($this->support(), 'admins')
            ->postJson("/api/admin/vendors/{$vendor->id}/reject", [])
            ->assertStatus(422);
    }

    public function test_super_admin_can_ban_and_unban_vendor(): void
    {
        $vendor = $this->makeVendor(['is_active' => true]);
        $super  = $this->superAdmin();

        $this->actingAs($super, 'admins')
            ->postJson("/api/admin/vendors/{$vendor->id}/toggle")
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertFalse($vendor->fresh()->is_active);

        $this->actingAs($super, 'admins')
            ->postJson("/api/admin/vendors/{$vendor->id}/toggle")
            ->assertJsonPath('is_active', true);
    }

    public function test_super_admin_can_ban_user(): void
    {
        $user = $this->makeUser();

        $this->actingAs($this->superAdmin(), 'admins')
            ->postJson("/api/admin/users/{$user->id}/toggle")
            ->assertOk()
            ->assertJsonPath('is_active', false);
    }

    // ── Audit log ─────────────────────────────────────────

    public function test_sensitive_actions_are_audited(): void
    {
        $vendor = $this->makeVendor();
        $super  = $this->superAdmin();

        $this->actingAs($super, 'admins')->postJson("/api/admin/vendors/{$vendor->id}/approve")->assertOk();
        $this->actingAs($super, 'admins')->postJson("/api/admin/vendors/{$vendor->id}/toggle")->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id'    => $super->id,
            'action'      => 'vendor.approve',
            'target_type' => 'vendor',
            'target_id'   => $vendor->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $super->id,
            'action'   => 'vendor.ban',
        ]);
    }

    // ── Manage admins ─────────────────────────────────────

    public function test_super_admin_can_create_support_account(): void
    {
        $this->actingAs($this->superAdmin(), 'admins')
            ->postJson('/api/admin/admins', [
                'name'     => 'New Support',
                'email'    => 'new@haflati.com',
                'password' => 'secret123',
                'role'     => 'support',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('admins', ['email' => 'new@haflati.com', 'role' => 'support']);
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $super = $this->superAdmin();

        $this->actingAs($super, 'admins')
            ->deleteJson("/api/admin/admins/{$super->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('admins', ['id' => $super->id]);
    }

    public function test_super_admin_can_delete_another_admin(): void
    {
        $super   = $this->superAdmin();
        $support = $this->support();

        $this->actingAs($super, 'admins')
            ->deleteJson("/api/admin/admins/{$support->id}")
            ->assertOk();

        $this->assertDatabaseMissing('admins', ['id' => $support->id]);
    }

    // ── Pagination ────────────────────────────────────────

    public function test_vendor_list_is_paginated(): void
    {
        $this->makeVendor();

        $this->actingAs($this->superAdmin(), 'admins')
            ->getJson('/api/admin/vendors')
            ->assertOk()
            ->assertJsonStructure(['vendors' => ['current_page', 'data', 'per_page', 'total']]);
    }

    // ── Guard isolation ───────────────────────────────────

    public function test_admin_routes_reject_unauthenticated(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
    }
}

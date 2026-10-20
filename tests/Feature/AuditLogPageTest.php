<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_audit_history_by_actor_and_search_text(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Actor', 'role' => 'admin', 'is_active' => true]);
        $other = User::factory()->create(['name' => 'Other Actor', 'role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'customer_updated',
            'model_type' => Customer::class,
            'model_id' => $customer->id,
            'before' => ['opening_balance' => 10],
            'after' => ['opening_balance' => 20],
            'ip_address' => '127.0.0.1',
            'created_at' => '2026-10-20 10:00:00',
        ]);
        AuditLog::create([
            'user_id' => $other->id,
            'action' => 'supplier_updated',
            'model_type' => 'App\\Models\\Supplier',
            'model_id' => 99,
            'created_at' => '2026-10-20 11:00:00',
        ]);

        $this->actingAs($admin)->get(route('audit-logs.index', [
            'q' => 'customer',
            'user_id' => $admin->id,
            'from' => '2026-10-20',
            'to' => '2026-10-20',
        ]))
            ->assertOk()
            ->assertSee('Admin Actor')
            ->assertSee('Customer Updated')
            ->assertSee('&quot;opening_balance&quot;: 10', false)
            ->assertDontSee('Supplier Updated');
    }

    public function test_non_admin_cannot_view_audit_history(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);

        $this->actingAs($accountant)->get(route('audit-logs.index'))->assertForbidden();
    }
}

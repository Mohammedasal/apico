<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\MonthlyExpense;
use App\Models\ProductionDay;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessAuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_supplier_opening_balance_changes_capture_before_and_after_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'opening_balance' => 10, 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Supplier', 'opening_balance' => 20, 'status' => 'active']);

        $this->actingAs($admin)->put(route('customers.update', $customer), [
            'name' => 'Customer',
            'opening_balance' => 15,
            'opening_weight_balance_kg' => 0,
            'status' => 'active',
        ])->assertRedirect(route('customers.show', $customer));
        $this->put(route('suppliers.update', $supplier), [
            'name' => 'Supplier',
            'opening_balance' => 30,
            'status' => 'active',
        ])->assertRedirect(route('suppliers.show', $supplier));

        $customerAudit = AuditLog::where('action', 'customer_updated')->firstOrFail();
        $supplierAudit = AuditLog::where('action', 'supplier_updated')->firstOrFail();
        $this->assertSame('10.000', $customerAudit->before['opening_balance']);
        $this->assertSame('15.000', $customerAudit->after['opening_balance']);
        $this->assertSame('20.000', $supplierAudit->before['opening_balance']);
        $this->assertSame('30.000', $supplierAudit->after['opening_balance']);
        $this->assertSame($admin->id, $customerAudit->user_id);
    }

    public function test_user_audit_never_contains_password_or_remember_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Audited User',
            'email' => 'audited@example.com',
            'password' => 'Secret@123',
            'role' => 'viewer',
            'is_active' => 1,
        ])->assertRedirect(route('users.index'));

        $audit = AuditLog::where('action', 'user_created')->firstOrFail();
        $this->assertArrayNotHasKey('password', $audit->after);
        $this->assertArrayNotHasKey('remember_token', $audit->after);
        $this->assertSame('audited@example.com', $audit->after['email']);
    }

    public function test_production_days_monthly_expenses_and_settings_are_audited_as_batches(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Setting::create(['key' => 'currency', 'value' => 'JOD']);

        $this->actingAs($admin)->post(route('production.days.store'), [
            'year' => 2026,
            'month' => 1,
            'days' => [[
                'date' => '2026-01-01',
                'shift_one_kg' => 100,
                'shift_two_kg' => 50,
            ]],
        ])->assertRedirect();
        $this->post(route('production.expenses.store'), [
            'year' => 2026,
            'month' => 1,
            'average_income_per_ton' => 140,
            'electricity_bill' => 25,
            'total_salaries' => 0,
            'rent' => 0,
            'misc' => 0,
            'social_security' => 0,
            'other_expenses' => 0,
        ])->assertRedirect();
        $this->put(route('settings.update'), [
            'currency' => 'JOD',
            'default_granulation_cost_per_kg' => 0,
            'high_balance_threshold' => 1000,
            'allow_stock_override' => 0,
            'accounting_enabled' => 0,
            'monthly_expense_source' => 'legacy_monthly_expenses',
            'expense_cutover_date' => null,
        ])->assertRedirect(route('settings.index'));

        $this->assertDatabaseHas('production_days', ['date' => '2026-01-01 00:00:00', 'shift_one_kg' => 100]);
        $this->assertDatabaseHas('monthly_expenses', ['year' => 2026, 'month' => 1, 'electricity_bill' => 25]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'production_days_saved', 'model_type' => ProductionDay::class]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'monthly_expense_saved', 'model_type' => MonthlyExpense::class]);
        $settingsAudit = AuditLog::where('action', 'settings_updated')->firstOrFail();
        $this->assertSame('JOD', $settingsAudit->before['currency']);
        $this->assertSame('1000', $settingsAudit->after['high_balance_threshold']);
    }
}

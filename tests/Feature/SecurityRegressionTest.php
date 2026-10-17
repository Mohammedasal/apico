<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_authenticated_user_is_blocked_from_every_authenticated_route(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('accounting.dashboard'))->assertForbidden();
    }

    public function test_accountant_can_read_financial_reports_and_statement_exports(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);

        $this->actingAs($accountant)->get(route('production.index'))->assertOk();
        $this->actingAs($accountant)->get(route('reports.monthly'))->assertOk();
        $this->actingAs($accountant)->get(route('reports.stock-profit'))->assertOk();
        $this->actingAs($accountant)->get(route('customers.export', $customer))->assertOk();
        $this->actingAs($accountant)->get(route('suppliers.export', $supplier))->assertOk();
    }

    public function test_data_entry_cannot_settle_customer_or_supplier_cheques(): void
    {
        $dataEntry = User::factory()->create(['role' => 'data_entry', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);
        $incoming = Payment::create([
            'date' => '2026-10-01',
            'customer_id' => $customer->id,
            'amount' => 100,
            'payment_type' => 'cheque',
            'cheque_due_date' => '2026-10-15',
            'cheque_status' => 'pending',
        ]);
        $outgoing = SupplierPayment::create([
            'date' => '2026-10-01',
            'supplier_id' => $supplier->id,
            'amount' => 100,
            'payment_type' => 'cheque',
            'cheque_due_date' => '2026-10-15',
            'cheque_status' => 'pending',
        ]);

        $this->actingAs($dataEntry)->put(route('cheques-in.update', $incoming), [
            'cheque_status' => 'collected',
            'cheque_settlement_date' => '2026-10-15',
        ])->assertForbidden();
        $this->actingAs($dataEntry)->put(route('cheques-out.supplier.update', $outgoing), [
            'cheque_status' => 'cleared',
            'cheque_settlement_date' => '2026-10-15',
        ])->assertForbidden();
    }

    public function test_statement_exports_keep_counterparty_name_and_date_in_filename(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Marwan APICO', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Golden Supplier', 'status' => 'active']);

        $this->travelTo('2026-06-20 12:00:00');

        $this->actingAs($admin)
            ->get(route('customers.export', $customer))
            ->assertDownload('Marwan-APICO-june-20.xlsx');
        $this->actingAs($admin)
            ->get(route('suppliers.export', $supplier))
            ->assertDownload('Golden-Supplier-june-20.xlsx');
    }
}

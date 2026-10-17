<?php

namespace Tests\Feature;

use App\Models\StockPurchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_list_shows_live_payable_instead_of_only_opening_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Live Balance Supplier', 'opening_balance' => 10, 'status' => 'active']);
        StockPurchase::create(['date' => '2026-01-01', 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'weight_kg' => 100, 'cost_per_kg' => 0.5, 'total_cost' => 50]);
        SupplierPayment::create(['date' => '2026-01-02', 'supplier_id' => $supplier->id, 'amount' => 20, 'payment_type' => 'cash']);

        $this->actingAs($admin)->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('40.000');
    }

    public function test_supplier_payments_filter_and_sort_before_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Filtered Supplier', 'status' => 'active']);
        $other = Supplier::create(['name' => 'Other Supplier', 'status' => 'active']);

        foreach (range(1, 30) as $amount) {
            SupplierPayment::create([
                'date' => '2026-04-01',
                'supplier_id' => $supplier->id,
                'amount' => $amount,
                'payment_type' => $amount % 2 === 0 ? 'cash' : 'bank_transfer',
                'notes' => 'batch payment',
            ]);
        }
        SupplierPayment::create(['date' => '2026-04-01', 'supplier_id' => $other->id, 'amount' => 0.5, 'payment_type' => 'cash']);

        $response = $this->actingAs($admin)->get(route('supplier-payments.index', [
            'supplier_id' => $supplier->id,
            'payment_type' => 'cash',
            'min_amount' => 10,
            'sort' => 'amount',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        $payments = $response->viewData('payments');
        $this->assertSame(10.0, (float) $payments->first()->amount);
        $this->assertTrue($payments->every(fn (SupplierPayment $payment) => $payment->supplier_id === $supplier->id));
        $this->assertTrue($payments->every(fn (SupplierPayment $payment) => $payment->payment_type === 'cash'));
    }

    public function test_saving_supplier_payment_returns_to_form_and_shows_last_added(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Repeat Supplier', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('supplier-payments.store'), [
            'date' => '2026-04-01',
            'supplier_id' => $supplier->id,
            'amount' => 25,
            'payment_type' => 'cash',
        ]);

        $payment = SupplierPayment::firstOrFail();
        $response->assertRedirect(route('supplier-payments.create'))
            ->assertSessionHas('recent_supplier_payment', $payment->id);
        $this->actingAs($admin)->get(route('supplier-payments.create'))
            ->assertOk()
            ->assertSee('Repeat Supplier')
            ->assertSee('25.000');
    }

    public function test_only_admin_can_delete_supplier_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $dataEntry = User::factory()->create(['role' => 'data_entry', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);
        $payment = SupplierPayment::create(['date' => '2026-04-01', 'supplier_id' => $supplier->id, 'amount' => 25, 'payment_type' => 'cash']);

        $this->actingAs($dataEntry)->delete(route('supplier-payments.destroy', $payment))->assertForbidden();
        $this->assertDatabaseHas('supplier_payments', ['id' => $payment->id]);

        $this->actingAs($admin)->delete(route('supplier-payments.destroy', $payment))->assertRedirect(route('supplier-payments.index'));
        $this->assertDatabaseMissing('supplier_payments', ['id' => $payment->id]);
    }

    public function test_viewer_can_export_filtered_supplier_payments(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);

        $this->actingAs($viewer)
            ->get(route('supplier-payments.export'))
            ->assertDownload('supplier-payments-'.now()->format('Y-m-d').'.xlsx');
    }
}

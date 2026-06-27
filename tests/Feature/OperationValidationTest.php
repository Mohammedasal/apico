<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Material;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'data_entry', 'is_active' => true]));
    }

    public function test_zero_recycle_out_price_requires_a_note(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $material = Material::create(['name' => 'PET', 'type' => 'both', 'is_active' => true]);

        $response = $this->post(route('operations.store', 'recycle-out'), [
            'date' => '2026-01-01',
            'customer_id' => $customer->id,
            'material_id' => $material->id,
            'recycled_out_kg' => 10,
            'waste_kg' => 0,
            'non_recycled_kg' => 0,
            'rate_per_kg' => 0,
        ]);

        $response->assertSessionHasErrors('notes');
    }

    public function test_recycle_out_saves_recycled_waste_and_non_recycled_weights(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $material = Material::create(['name' => 'PET', 'is_active' => true]);

        $response = $this->post(route('operations.store', 'recycle-out'), [
            'date' => '2026-01-01',
            'customer_id' => $customer->id,
            'material_id' => $material->id,
            'recycled_out_kg' => 8,
            'waste_kg' => 1,
            'non_recycled_kg' => 1.5,
            'rate_per_kg' => 2,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recycle_outs', [
            'recycled_out_kg' => 8,
            'waste_kg' => 1,
            'non_recycled_kg' => 1.5,
            'weight_kg' => 10.5,
            'total_amount' => 16,
        ]);
    }

    public function test_recycle_out_keeps_decimal_rate_and_amount_exactly(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);

        $response = $this->post(route('operations.store', 'recycle-out'), [
            'date' => '2026-01-01',
            'customer_id' => $customer->id,
            'recycled_out_kg' => 1390,
            'waste_kg' => 20,
            'non_recycled_kg' => 12,
            'rate_per_kg' => '.12',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recycle_outs', [
            'recycled_out_kg' => 1390,
            'rate_per_kg' => 0.12,
            'total_amount' => 166.8,
        ]);
    }

    public function test_imported_zero_rate_recycle_out_is_treated_as_waste(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);

        $response = $this->post(route('operations.store', 'recycle-out'), [
            'date' => '2026-01-01',
            'customer_id' => $customer->id,
            'recycled_out_kg' => 0,
            'waste_kg' => 25,
            'non_recycled_kg' => 0,
            'rate_per_kg' => 0,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recycle_outs', [
            'recycled_out_kg' => 0,
            'waste_kg' => 25,
            'non_recycled_kg' => 0,
            'rate_per_kg' => 0,
            'total_amount' => 0,
        ]);
    }

    public function test_recycle_out_material_is_optional(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);

        $response = $this->post(route('operations.store', 'recycle-out'), [
            'date' => '2026-01-01',
            'customer_id' => $customer->id,
            'recycled_out_kg' => 8,
            'waste_kg' => 1,
            'non_recycled_kg' => 1,
            'rate_per_kg' => 2,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recycle_outs', ['material_id' => null, 'weight_kg' => 10]);
    }

    public function test_stock_purchase_material_is_optional(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);

        $response = $this->post(route('operations.store', 'stock-purchases'), [
            'date' => '2026-01-01',
            'supplier_id' => $supplier->id,
            'weight_kg' => 10,
            'cost_per_kg' => 2,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_purchases', ['supplier_id' => $supplier->id, 'material_id' => null, 'total_cost' => 20]);
    }

    public function test_recycle_in_does_not_require_a_rate(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $material = Material::create(['name' => 'PET', 'is_active' => true]);

        $response = $this->post(route('operations.store', 'recycle-in'), [
            'date' => '2026-01-01',
            'customer_id' => $customer->id,
            'material_id' => $material->id,
            'weight_kg' => 10,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recycle_ins', ['weight_kg' => 10, 'rate_per_kg' => 0, 'total_amount' => 0]);
    }

    public function test_recycle_in_material_is_optional(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);

        $response = $this->post(route('operations.store', 'recycle-in'), [
            'date' => '2026-01-01',
            'customer_id' => $customer->id,
            'weight_kg' => 10,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recycle_ins', ['material_id' => null, 'weight_kg' => 10]);
    }

    public function test_recycle_lists_can_be_filtered_by_customer_date_and_weight(): void
    {
        $first = Customer::create(['name' => 'First Customer', 'status' => 'active']);
        $second = Customer::create(['name' => 'Second Customer', 'status' => 'active']);

        RecycleIn::create(['date' => '2026-01-05', 'customer_id' => $first->id, 'weight_kg' => 100, 'rate_per_kg' => 0, 'total_amount' => 0]);
        RecycleIn::create(['date' => '2026-01-10', 'customer_id' => $second->id, 'weight_kg' => 250, 'rate_per_kg' => 0, 'total_amount' => 0]);

        $response = $this->get(route('operations.index', [
            'module' => 'recycle-in',
            'customer_id' => $second->id,
            'from' => '2026-01-09',
            'to' => '2026-01-11',
            'min_weight' => 200,
            'max_weight' => 300,
        ]));

        $response->assertOk();
        $response->assertSee('Second Customer');
        $response->assertSee('250.000');
        $response->assertDontSee('2026-01-05');
        $response->assertDontSee('100.000');

        RecycleOut::create(['date' => '2026-02-01', 'customer_id' => $first->id, 'weight_kg' => 50, 'recycled_out_kg' => 50, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 50]);
        RecycleOut::create(['date' => '2026-02-02', 'customer_id' => $second->id, 'weight_kg' => 75, 'recycled_out_kg' => 75, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 75]);

        $response = $this->get(route('operations.index', [
            'module' => 'recycle-out',
            'customer_id' => $second->id,
            'from' => '2026-02-02',
            'to' => '2026-02-02',
            'min_weight' => 70,
            'max_weight' => 80,
        ]));

        $response->assertOk();
        $response->assertSee('Second Customer');
        $response->assertSee('75.000');
        $response->assertDontSee('2026-02-01');
        $response->assertDontSee('50.000');
    }

    public function test_stock_sale_is_blocked_when_weight_exceeds_available_stock(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $material = Material::create(['name' => 'PET', 'type' => 'stock', 'is_active' => true]);

        StockPurchase::create(['date' => '2026-01-01', 'supplier_name' => 'Supplier', 'material_id' => $material->id, 'weight_kg' => 5, 'cost_per_kg' => 1, 'total_cost' => 5]);

        $response = $this->post(route('operations.store', 'stock-sales'), [
            'date' => '2026-01-02',
            'customer_id' => $customer->id,
            'material_id' => $material->id,
            'weight_kg' => 6,
            'selling_price_per_kg' => 2,
            'purchase_cost_per_kg' => 1,
            'granulation_cost_per_kg' => 0,
        ]);

        $response->assertSessionHasErrors('weight_kg');
    }

    public function test_supplier_cheque_payments_are_visible_on_cheques_out_page(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);
        SupplierPayment::create([
            'date' => '2026-01-01',
            'supplier_id' => $supplier->id,
            'amount' => 250,
            'payment_type' => 'cheque',
            'reference_no' => 'CH-1',
            'cheque_due_date' => '2026-02-01',
            'cheque_status' => 'pending',
        ]);

        $response = $this->get(route('cheques-out.index'));

        $response->assertOk();
        $response->assertSee('Supplier Payment Cheques');
        $response->assertSee('CH-1');
        $response->assertSee('250.000');
    }
}

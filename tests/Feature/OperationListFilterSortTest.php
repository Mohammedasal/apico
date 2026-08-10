<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Payment;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationListFilterSortTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'data_entry', 'is_active' => true]));
    }

    public function test_payments_can_be_filtered_by_all_relevant_fields(): void
    {
        $first = Customer::create(['name' => 'First Customer', 'status' => 'active']);
        $second = Customer::create(['name' => 'Filtered Customer', 'status' => 'active']);

        Payment::create([
            'date' => '2026-01-01',
            'customer_id' => $first->id,
            'amount' => 100,
            'payment_type' => 'cash',
            'reference_no' => 'EXCLUDE',
        ]);
        Payment::create([
            'date' => '2026-02-15',
            'customer_id' => $second->id,
            'amount' => 750,
            'payment_type' => 'cheque',
            'cheque_status' => 'bounced',
            'reference_no' => 'MATCH-REF',
        ]);

        $response = $this->get(route('operations.index', [
            'module' => 'payments',
            'customer_id' => $second->id,
            'from' => '2026-02-01',
            'to' => '2026-02-28',
            'min_amount' => 700,
            'max_amount' => 800,
            'payment_type' => 'cheque',
            'cheque_status' => 'bounced',
            'search' => 'MATCH-REF',
        ]));

        $response->assertOk()
            ->assertSee('Filtered Customer')
            ->assertSee('750.000')
            ->assertDontSee('EXCLUDE');
    }

    public function test_purchase_and_sale_lists_have_relationship_weight_amount_and_text_filters(): void
    {
        $firstSupplier = Supplier::create(['name' => 'First Supplier', 'status' => 'active']);
        $secondSupplier = Supplier::create(['name' => 'Filtered Supplier', 'status' => 'active']);
        $firstMaterial = Material::create(['name' => 'PET', 'is_active' => true]);
        $secondMaterial = Material::create(['name' => 'HDPE', 'is_active' => true]);

        StockPurchase::create(['date' => '2026-01-01', 'supplier_id' => $firstSupplier->id, 'supplier_name' => $firstSupplier->name, 'material_id' => $firstMaterial->id, 'weight_kg' => 100, 'cost_per_kg' => 1, 'total_cost' => 100, 'notes' => 'exclude purchase']);
        StockPurchase::create(['date' => '2026-03-10', 'supplier_id' => $secondSupplier->id, 'supplier_name' => $secondSupplier->name, 'material_id' => $secondMaterial->id, 'weight_kg' => 500, 'cost_per_kg' => 2, 'total_cost' => 1000, 'notes' => 'purchase needle']);

        $purchaseResponse = $this->get(route('operations.index', [
            'module' => 'stock-purchases',
            'supplier_id' => $secondSupplier->id,
            'material_id' => $secondMaterial->id,
            'from' => '2026-03-01',
            'min_weight' => 450,
            'max_weight' => 550,
            'min_amount' => 900,
            'max_amount' => 1100,
            'search' => 'needle',
        ]));

        $purchaseResponse->assertOk()
            ->assertSee('Filtered Supplier')
            ->assertSee('purchase needle')
            ->assertDontSee('exclude purchase');

        $firstCustomer = Customer::create(['name' => 'First Buyer', 'status' => 'active']);
        $secondCustomer = Customer::create(['name' => 'Filtered Buyer', 'status' => 'active']);
        StockSale::create(['date' => '2026-01-01', 'customer_id' => $firstCustomer->id, 'material_id' => $firstMaterial->id, 'weight_kg' => 50, 'selling_price_per_kg' => 1, 'sales_value' => 50, 'net_profit' => 10, 'notes' => 'exclude sale']);
        StockSale::create(['date' => '2026-04-20', 'customer_id' => $secondCustomer->id, 'material_id' => $secondMaterial->id, 'weight_kg' => 300, 'selling_price_per_kg' => 2, 'sales_value' => 600, 'net_profit' => 100, 'notes' => 'sale needle']);

        $saleResponse = $this->get(route('operations.index', [
            'module' => 'stock-sales',
            'customer_id' => $secondCustomer->id,
            'material_id' => $secondMaterial->id,
            'from' => '2026-04-01',
            'min_weight' => 250,
            'max_weight' => 350,
            'min_amount' => 550,
            'max_amount' => 650,
            'search' => 'needle',
        ]));

        $saleResponse->assertOk()
            ->assertSee('Filtered Buyer')
            ->assertSee('sale needle')
            ->assertDontSee('exclude sale');
    }

    public function test_column_sorting_is_applied_before_pagination(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);

        foreach (range(1, 30) as $offset) {
            Payment::create([
                'date' => '2026-01-01',
                'customer_id' => $customer->id,
                'amount' => 1000 + $offset,
                'payment_type' => 'cash',
            ]);
        }

        $ascending = $this->get(route('operations.index', [
            'module' => 'payments',
            'sort' => 'amount',
            'direction' => 'asc',
        ]));

        $ascending->assertOk()
            ->assertSee('1,001.000')
            ->assertDontSee('1,030.000')
            ->assertSee('sort=amount', false);

        $descending = $this->get(route('operations.index', [
            'module' => 'payments',
            'sort' => 'amount',
            'direction' => 'desc',
        ]));

        $descending->assertOk()
            ->assertSee('1,030.000')
            ->assertDontSee('1,001.000');
    }

    public function test_relationship_columns_are_sorted_in_the_database(): void
    {
        $betaCustomer = Customer::create(['name' => 'Beta Customer', 'status' => 'active']);
        $alphaCustomer = Customer::create(['name' => 'Alpha Customer', 'status' => 'active']);
        Payment::create(['date' => '2026-01-01', 'customer_id' => $betaCustomer->id, 'amount' => 10, 'payment_type' => 'cash']);
        Payment::create(['date' => '2026-01-01', 'customer_id' => $alphaCustomer->id, 'amount' => 20, 'payment_type' => 'cash']);

        $customerBody = str($this->get(route('operations.index', [
            'module' => 'payments',
            'sort' => 'customer',
            'direction' => 'asc',
        ]))->assertOk()->getContent())->after('<tbody>')->before('</tbody>')->toString();

        $this->assertLessThan(strpos($customerBody, 'Beta Customer'), strpos($customerBody, 'Alpha Customer'));

        $betaSupplier = Supplier::create(['name' => 'Beta Supplier', 'status' => 'active']);
        $alphaSupplier = Supplier::create(['name' => 'Alpha Supplier', 'status' => 'active']);
        StockPurchase::create(['date' => '2026-01-01', 'supplier_id' => $betaSupplier->id, 'supplier_name' => $betaSupplier->name, 'weight_kg' => 10, 'cost_per_kg' => 1, 'total_cost' => 10]);
        StockPurchase::create(['date' => '2026-01-01', 'supplier_id' => $alphaSupplier->id, 'supplier_name' => $alphaSupplier->name, 'weight_kg' => 20, 'cost_per_kg' => 1, 'total_cost' => 20]);

        $supplierBody = str($this->get(route('operations.index', [
            'module' => 'stock-purchases',
            'sort' => 'supplier',
            'direction' => 'asc',
        ]))->assertOk()->getContent())->after('<tbody>')->before('</tbody>')->toString();

        $this->assertLessThan(strpos($supplierBody, 'Beta Supplier'), strpos($supplierBody, 'Alpha Supplier'));

        $betaMaterial = Material::create(['name' => 'Beta Material', 'is_active' => true]);
        $alphaMaterial = Material::create(['name' => 'Alpha Material', 'is_active' => true]);
        StockSale::create(['date' => '2026-01-01', 'customer_id' => $alphaCustomer->id, 'material_id' => $betaMaterial->id, 'weight_kg' => 10, 'selling_price_per_kg' => 1, 'sales_value' => 10, 'net_profit' => 1]);
        StockSale::create(['date' => '2026-01-01', 'customer_id' => $alphaCustomer->id, 'material_id' => $alphaMaterial->id, 'weight_kg' => 20, 'selling_price_per_kg' => 1, 'sales_value' => 20, 'net_profit' => 2]);

        $materialBody = str($this->get(route('operations.index', [
            'module' => 'stock-sales',
            'sort' => 'material',
            'direction' => 'asc',
        ]))->assertOk()->getContent())->after('<tbody>')->before('</tbody>')->toString();

        $this->assertLessThan(strpos($materialBody, 'Beta Material'), strpos($materialBody, 'Alpha Material'));
    }
}

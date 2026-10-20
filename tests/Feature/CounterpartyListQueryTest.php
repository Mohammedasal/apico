<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterpartyListQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    public function test_customer_balance_sort_applies_before_pagination(): void
    {
        foreach (range(1, 30) as $number) {
            Customer::create([
                'name' => sprintf('Customer %02d', $number),
                'opening_balance' => $number,
                'opening_weight_balance_kg' => $number,
                'status' => 'active',
            ]);
        }
        $highest = Customer::where('name', 'Customer 01')->firstOrFail();
        RecycleIn::create(['date' => '2026-01-01', 'customer_id' => $highest->id, 'weight_kg' => 1000, 'rate_per_kg' => 0, 'total_amount' => 0]);
        RecycleOut::create(['date' => '2026-01-02', 'customer_id' => $highest->id, 'weight_kg' => 100, 'recycled_out_kg' => 100, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 10, 'total_amount' => 1000]);

        $response = $this->get(route('customers.index', [
            'sort' => 'balance_jod',
            'direction' => 'desc',
            'per_page' => 25,
        ]));

        $response->assertOk();
        $customers = $response->viewData('customers');
        $this->assertSame(30, $customers->total());
        $this->assertSame('Customer 01', $customers->items()[0]->name);
        $this->assertSame(1001.0, (float) $customers->items()[0]->remaining_balance_jod);
        $this->assertSame(901.0, (float) $customers->items()[0]->remaining_balance_kg);
    }

    public function test_supplier_totals_sort_and_status_filter_run_in_database(): void
    {
        $small = Supplier::create(['name' => 'Small Supplier', 'status' => 'active']);
        $large = Supplier::create(['name' => 'Large Supplier', 'opening_balance' => 10, 'status' => 'active']);
        Supplier::create(['name' => 'Inactive Supplier', 'status' => 'inactive']);
        StockPurchase::create(['date' => '2026-01-01', 'supplier_id' => $small->id, 'supplier_name' => $small->name, 'weight_kg' => 10, 'cost_per_kg' => 1, 'total_cost' => 10]);
        StockPurchase::create(['date' => '2026-01-01', 'supplier_id' => $large->id, 'supplier_name' => $large->name, 'weight_kg' => 500, 'cost_per_kg' => 2, 'total_cost' => 1000]);
        SupplierPayment::create(['date' => '2026-01-02', 'supplier_id' => $large->id, 'amount' => 100, 'payment_type' => 'cash']);

        $response = $this->get(route('suppliers.index', [
            'status' => 'active',
            'sort' => 'purchased_kg',
            'direction' => 'desc',
        ]));

        $response->assertOk()->assertDontSee('Inactive Supplier');
        $suppliers = $response->viewData('suppliers');
        $this->assertSame(2, $suppliers->total());
        $this->assertSame('Large Supplier', $suppliers->items()[0]->name);
        $this->assertSame(500.0, (float) $suppliers->items()[0]->purchased_kg);
        $this->assertSame(910.0, (float) $suppliers->items()[0]->remaining_balance_jod);
    }
}

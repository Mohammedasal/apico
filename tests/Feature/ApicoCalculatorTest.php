<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Material;
use App\Models\MonthlyExpense;
use App\Models\Payment;
use App\Models\ProductionDay;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\ApicoCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApicoCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_statement_uses_opening_balance_before_date_range_and_running_balance(): void
    {
        $calculator = app(ApicoCalculator::class);
        $customer = Customer::create(['name' => 'Ayman', 'opening_balance' => 100, 'opening_weight_balance_kg' => 5, 'status' => 'active']);
        $material = Material::create(['name' => 'PET', 'type' => 'both', 'is_active' => true]);

        RecycleIn::create(['date' => '2026-01-01', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 10, 'rate_per_kg' => 0, 'total_amount' => 0]);
        Payment::create(['date' => '2026-01-05', 'customer_id' => $customer->id, 'amount' => 30]);
        RecycleOut::create(['date' => '2026-02-01', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 7, 'recycled_out_kg' => 4, 'waste_kg' => 2, 'non_recycled_kg' => 1, 'rate_per_kg' => 3, 'total_amount' => 12]);

        $statement = $calculator->customerStatement($customer, '2026-02-01', '2026-02-28');

        $this->assertSame(70.0, $statement['opening_balance']);
        $this->assertSame(15.0, $statement['opening_weight']);
        $this->assertSame(82.0, $statement['closing_balance']);
        $this->assertSame(8.0, $statement['closing_weight']);
        $this->assertCount(1, $statement['transactions']);
    }

    public function test_customer_statement_shows_remaining_receivable_after_charges_and_payments(): void
    {
        $calculator = app(ApicoCalculator::class);
        $customer = Customer::create(['name' => 'Statement Customer', 'status' => 'active']);

        RecycleOut::create(['date' => '2026-05-05', 'customer_id' => $customer->id, 'weight_kg' => 5000, 'recycled_out_kg' => 5000, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 0.12, 'total_amount' => 600]);
        StockSale::create(['date' => '2026-05-10', 'customer_id' => $customer->id, 'weight_kg' => 1000, 'selling_price_per_kg' => 0.6, 'sales_value' => 600, 'purchase_cost_per_kg' => 0, 'granulation_cost_per_kg' => 0, 'net_profit' => 600]);
        Payment::create(['date' => '2026-05-15', 'customer_id' => $customer->id, 'amount' => 700]);

        $statement = $calculator->customerStatement($customer, '2026-05-01', '2026-05-31');

        $this->assertSame(600.0, $statement['period_recycle_charges']);
        $this->assertSame(600.0, $statement['period_stock_charges']);
        $this->assertSame(700.0, $statement['period_payments']);
        $this->assertSame(500.0, $statement['closing_balance']);
        $this->assertSame(500.0, (float) $statement['tables']['payments']->first()->remaining_balance);

        $stockSaleRow = $statement['transactions']->firstWhere('type', 'Stock Sale');
        $this->assertSame(-1000.0, $stockSaleRow['display_weight']);
        $this->assertSame(0.0, (float) $stockSaleRow['weight_delta']);
        $this->assertSame(-5000.0, $stockSaleRow['running_weight']);
    }

    public function test_customer_statement_includes_datetime_rows_on_the_end_date(): void
    {
        $calculator = app(ApicoCalculator::class);
        $customer = Customer::create(['name' => 'End Date Customer', 'status' => 'active']);

        DB::table('recycle_outs')->insert([
            'date' => '2026-06-21 00:00:00',
            'customer_id' => $customer->id,
            'weight_kg' => 3750,
            'recycled_out_kg' => 3750,
            'waste_kg' => 0,
            'non_recycled_kg' => 0,
            'rate_per_kg' => 0.14,
            'total_amount' => 525,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statement = $calculator->customerStatement($customer, '2026-06-01', '2026-06-21');

        $this->assertSame(525.0, $statement['closing_balance']);
        $this->assertSame(525.0, $statement['period_recycle_charges']);
        $this->assertCount(1, $statement['transactions']);
    }

    public function test_stock_sale_profit_and_remaining_stock_are_calculated(): void
    {
        $calculator = app(ApicoCalculator::class);
        $customer = Customer::create(['name' => 'Buyer', 'status' => 'active']);
        $material = Material::create(['name' => 'HDPE', 'type' => 'stock', 'is_active' => true]);

        StockPurchase::create(['date' => '2026-01-01', 'supplier_name' => 'Supplier', 'material_id' => $material->id, 'weight_kg' => 100, 'cost_per_kg' => 1.5, 'total_cost' => 150]);
        $values = $calculator->stockSaleValues(20, 3, 1.5, 0.25);
        StockSale::create(['date' => '2026-01-02', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 20, 'selling_price_per_kg' => 3, 'sales_value' => $values['sales_value'], 'purchase_cost_per_kg' => 1.5, 'granulation_cost_per_kg' => 0.25, 'net_profit' => $values['net_profit']]);

        $this->assertSame(60.0, $values['sales_value']);
        $this->assertSame(25.0, $values['net_profit']);
        $this->assertSame(80.0, $calculator->remainingStockWeight($material->id));
    }

    public function test_stock_profit_summary_subtracts_weighted_average_cogs_and_conversion_cost(): void
    {
        $calculator = app(ApicoCalculator::class);
        $customer = Customer::create(['name' => 'Buyer', 'status' => 'active']);
        $material = Material::create(['name' => 'HDPE', 'type' => 'stock', 'is_active' => true]);

        ProductionDay::create(['date' => '2026-04-01', 'shift_one_kg' => 500, 'shift_two_kg' => 500]);
        MonthlyExpense::create(['year' => 2026, 'month' => 4, 'electricity_bill' => 100, 'total_salaries' => 0, 'rent' => 0, 'misc' => 0, 'social_security' => 0, 'other_expenses' => 0]);
        StockPurchase::create(['date' => '2026-04-01', 'supplier_name' => 'Supplier', 'material_id' => $material->id, 'weight_kg' => 1000, 'cost_per_kg' => 0.2, 'total_cost' => 200]);
        StockSale::create(['date' => '2026-04-02', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 500, 'selling_price_per_kg' => 1, 'sales_value' => 500, 'purchase_cost_per_kg' => 0, 'granulation_cost_per_kg' => 0, 'net_profit' => 500]);

        $summary = $calculator->stockProfitSummary('2026-04-01', '2026-04-30');

        $this->assertSame(500.0, $summary['revenue']);
        $this->assertSame(100.0, $summary['material_cogs']);
        $this->assertSame(50.0, $summary['recycle_cost']);
        $this->assertSame(350.0, $summary['profit']);
    }

    public function test_actual_profit_summary_subtracts_stock_cogs_and_operating_expenses_once(): void
    {
        $calculator = app(ApicoCalculator::class);
        $customer = Customer::create(['name' => 'Buyer', 'status' => 'active']);
        $material = Material::create(['name' => 'HDPE', 'type' => 'stock', 'is_active' => true]);

        ProductionDay::create(['date' => '2026-04-01', 'shift_one_kg' => 500, 'shift_two_kg' => 500]);
        MonthlyExpense::create(['year' => 2026, 'month' => 4, 'electricity_bill' => 100, 'total_salaries' => 0, 'rent' => 0, 'misc' => 0, 'social_security' => 0, 'other_expenses' => 0]);
        RecycleOut::create(['date' => '2026-04-02', 'customer_id' => $customer->id, 'weight_kg' => 100, 'recycled_out_kg' => 100, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 100]);
        StockPurchase::create(['date' => '2026-04-01', 'supplier_name' => 'Supplier', 'material_id' => $material->id, 'weight_kg' => 1000, 'cost_per_kg' => 0.2, 'total_cost' => 200]);
        StockSale::create(['date' => '2026-04-03', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 500, 'selling_price_per_kg' => 1, 'sales_value' => 500, 'purchase_cost_per_kg' => 0, 'granulation_cost_per_kg' => 0, 'net_profit' => 500]);

        $summary = $calculator->actualProfitSummary('2026-04-01', '2026-04-30');

        $this->assertSame(100.0, $summary['recycle_income']);
        $this->assertSame(500.0, $summary['stock_revenue']);
        $this->assertSame(100.0, $summary['stock_material_cogs']);
        $this->assertSame(100.0, $summary['operating_expenses']);
        $this->assertSame(400.0, $summary['actual_profit_loss']);
    }

    public function test_supplier_statement_tracks_purchases_payments_and_running_balance(): void
    {
        $calculator = app(ApicoCalculator::class);
        $supplier = Supplier::create(['name' => 'Supplier', 'opening_balance' => 25, 'status' => 'active']);
        $material = Material::create(['name' => 'PET', 'type' => 'stock', 'is_active' => true]);

        StockPurchase::create(['date' => '2026-04-01', 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'material_id' => $material->id, 'weight_kg' => 100, 'cost_per_kg' => 0.5, 'total_cost' => 50]);
        SupplierPayment::create(['date' => '2026-04-02', 'supplier_id' => $supplier->id, 'amount' => 30, 'payment_type' => 'cash']);

        $statement = $calculator->supplierStatement($supplier, '2026-04-01', '2026-04-30');

        $this->assertSame(25.0, $statement['opening_balance']);
        $this->assertSame(50.0, $statement['period_purchases']);
        $this->assertSame(30.0, $statement['period_payments']);
        $this->assertSame(45.0, $statement['closing_balance']);
        $this->assertCount(2, $statement['transactions']);
    }
}

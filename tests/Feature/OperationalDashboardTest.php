<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Material;
use App\Models\MonthlyExpense;
use App\Models\ProductionDay;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_sales_monthly_dashboard_uses_actual_profit_calculation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Dashboard Customer', 'status' => 'active']);
        $material = Material::create(['name' => 'Dashboard Material', 'is_active' => true]);
        StockPurchase::create(['date' => '2026-06-01', 'supplier_name' => 'Supplier', 'material_id' => $material->id, 'weight_kg' => 1000, 'cost_per_kg' => .5, 'total_cost' => 500]);
        StockSale::create(['date' => '2026-07-10', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 100, 'selling_price_per_kg' => 2, 'sales_value' => 200, 'purchase_cost_per_kg' => .5, 'net_profit' => 150]);
        StockSale::create(['date' => '2026-06-10', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 50, 'selling_price_per_kg' => 2, 'sales_value' => 100, 'purchase_cost_per_kg' => .5, 'net_profit' => 75]);
        ProductionDay::create(['date' => '2026-07-10', 'shift_one_kg' => 10000, 'shift_two_kg' => 0]);
        MonthlyExpense::create(['year' => 2026, 'month' => 7, 'electricity_bill' => 1000]);

        $response = $this->actingAs($admin)->get(route('dashboards.stock-sales', [
            'period' => 'monthly',
            'month' => '2026-07',
        ]));

        $response->assertOk()
            ->assertSee('<title>APICO | Stock Sales Dashboard</title>', false)
            ->assertSee('July 2026')
            ->assertSee('Dashboard Customer')
            ->assertSee('Dashboard Material')
            ->assertSee('200.000')
            ->assertSee('50.000')
            ->assertSee('10.000')
            ->assertSee('140.000');
    }

    public function test_recycle_out_weekly_dashboard_filters_and_calculates_output_mix(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Recycle Customer', 'status' => 'active']);
        $material = Material::create(['name' => 'Recycle Material', 'is_active' => true]);
        RecycleOut::create(['date' => '2026-07-08', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 100, 'recycled_out_kg' => 80, 'waste_kg' => 15, 'non_recycled_kg' => 5, 'rate_per_kg' => .5, 'total_amount' => 40]);
        RecycleOut::create(['date' => '2026-07-15', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 900, 'recycled_out_kg' => 900, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 900]);

        $response = $this->actingAs($viewer)->get(route('dashboards.recycle-out', [
            'period' => 'weekly',
            'week' => '2026-W28',
        ]));

        $response->assertOk()
            ->assertSee('<title>APICO | Recycle Out Dashboard</title>', false)
            ->assertSee('Recycle Customer')
            ->assertSee('Recycle Material')
            ->assertSee('100.000')
            ->assertSee('80.00%')
            ->assertSee('15.00%')
            ->assertDontSee('900.000');

        $arabicResponse = $this->actingAs($viewer)
            ->withSession(['locale' => 'ar'])
            ->get(route('dashboards.recycle-out', ['period' => 'weekly', 'week' => '2026-W28']));

        $arabicResponse->assertOk()
            ->assertSee('100.000')
            ->assertDontSee('900.000');
    }

    public function test_weekly_dashboard_runs_from_saturday_through_thursday(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Boundary Customer', 'status' => 'active']);

        foreach ([
            ['date' => '2026-07-03', 'weight' => 300],
            ['date' => '2026-07-04', 'weight' => 400],
            ['date' => '2026-07-09', 'weight' => 900],
            ['date' => '2026-07-10', 'weight' => 1000],
        ] as $row) {
            RecycleOut::create([
                'date' => $row['date'],
                'customer_id' => $customer->id,
                'weight_kg' => $row['weight'],
                'recycled_out_kg' => $row['weight'],
                'waste_kg' => 0,
                'non_recycled_kg' => 0,
                'rate_per_kg' => 1,
                'total_amount' => $row['weight'],
            ]);
        }

        $this->actingAs($admin)->get(route('dashboards.recycle-out', [
            'period' => 'weekly',
            'week' => '2026-W28',
        ]))->assertOk()
            ->assertSee('04 Jul 2026 - 09 Jul 2026')
            ->assertViewHas('period', fn (array $period) => $period['from'] === '2026-07-04' && $period['to'] === '2026-07-09')
            ->assertViewHas('summary', fn (array $summary) => $summary['total_kg'] === 1300.0 && $summary['transactions'] === 2);
    }

    public function test_custom_dashboard_filters_any_date_range_and_normalizes_reversed_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Custom Range Customer', 'status' => 'active']);
        RecycleOut::create(['date' => '2026-07-10', 'customer_id' => $customer->id, 'weight_kg' => 100, 'recycled_out_kg' => 100, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 100]);
        RecycleOut::create(['date' => '2026-07-20', 'customer_id' => $customer->id, 'weight_kg' => 500, 'recycled_out_kg' => 500, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 500]);

        $this->actingAs($admin)->get(route('dashboards.recycle-out', [
            'period' => 'custom',
            'from' => '2026-07-15',
            'to' => '2026-07-05',
        ]))->assertOk()
            ->assertSee('05 Jul 2026 - 15 Jul 2026')
            ->assertSee('Custom Range Customer')
            ->assertViewHas('period', fn (array $period) => $period['from'] === '2026-07-05' && $period['to'] === '2026-07-15')
            ->assertViewHas('summary', fn (array $summary) => $summary['total_kg'] === 100.0 && $summary['transactions'] === 1);
    }

    public function test_total_view_groups_all_history_by_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        RecycleOut::create(['date' => '2026-01-10', 'customer_id' => $customer->id, 'weight_kg' => 10, 'recycled_out_kg' => 10, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 10]);
        RecycleOut::create(['date' => '2026-03-10', 'customer_id' => $customer->id, 'weight_kg' => 20, 'recycled_out_kg' => 15, 'waste_kg' => 5, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 15]);

        $response = $this->actingAs($admin)->get(route('dashboards.recycle-out', ['period' => 'total']));

        $response->assertOk()
            ->assertSee('All recorded dates')
            ->assertSee('Jan 2026')
            ->assertSee('Mar 2026')
            ->assertSee('30.000');
    }

    public function test_dashboard_permissions_hide_profit_and_reject_data_entry(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $dataEntry = User::factory()->create(['role' => 'data_entry', 'is_active' => true]);

        $this->actingAs($viewer)->get(route('dashboards.stock-sales'))
            ->assertOk()
            ->assertDontSee('Actual Stock Profit JOD');
        $this->actingAs($accountant)->get(route('dashboards.stock-sales'))
            ->assertOk()
            ->assertSee('Actual Stock Profit JOD');
        $this->actingAs($dataEntry)->get(route('dashboards.stock-sales'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('dashboards.recycle-out'))->assertForbidden();
    }
}

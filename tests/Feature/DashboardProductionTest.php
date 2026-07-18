<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProductionDay;
use App\Models\RecycleOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_production_uses_active_production_sheet_days_not_recycle_out_dates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);

        ProductionDay::create(['date' => '2026-01-01', 'shift_one_kg' => 2000, 'shift_two_kg' => 1000]);
        ProductionDay::create(['date' => '2026-01-02', 'shift_one_kg' => 2500, 'shift_two_kg' => 1500]);
        ProductionDay::create(['date' => '2026-01-03', 'shift_one_kg' => 0, 'shift_two_kg' => 0]);
        ProductionDay::create(['date' => '2026-02-01', 'shift_one_kg' => 9000, 'shift_two_kg' => 0]);

        foreach (['2026-01-01', '2026-01-02', '2026-01-03'] as $date) {
            RecycleOut::create([
                'date' => $date,
                'customer_id' => $customer->id,
                'weight_kg' => 100,
                'recycled_out_kg' => 100,
                'waste_kg' => 0,
                'non_recycled_kg' => 0,
                'rate_per_kg' => 0.1,
                'total_amount' => 10,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ]));

        $response->assertOk();
        $this->assertSame(7000.0, $response->viewData('productionKg'));
        $this->assertSame(2, $response->viewData('productionDays'));
        $this->assertSame(3500.0, $response->viewData('dailyProductionAverage'));
    }
}

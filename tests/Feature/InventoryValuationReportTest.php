<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Material;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryValuationReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_filters_as_of_date_and_reconciles_visible_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Buyer', 'status' => 'active']);
        $material = Material::create(['name' => 'PET', 'type' => 'stock', 'is_active' => true]);
        StockPurchase::create(['date' => '2026-01-01', 'supplier_name' => 'A', 'material_id' => $material->id, 'weight_kg' => 100, 'cost_per_kg' => 1, 'total_cost' => 100]);
        StockSale::create(['date' => '2026-01-02', 'customer_id' => $customer->id, 'material_id' => $material->id, 'weight_kg' => 40, 'selling_price_per_kg' => 2, 'sales_value' => 80, 'net_profit' => 40]);
        StockPurchase::create(['date' => '2026-02-01', 'supplier_name' => 'Later', 'material_id' => $material->id, 'weight_kg' => 100, 'cost_per_kg' => 2, 'total_cost' => 200]);

        $this->actingAs($admin)
            ->get(route('reports.inventory-valuation', ['on_date' => '2026-01-31', 'q' => 'PET']))
            ->assertOk()
            ->assertSee('PET')
            ->assertSee('60.000')
            ->assertSee('Inventory Value');
    }

    public function test_accountant_can_export_selected_inventory_columns_but_viewer_cannot_access_report(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);

        $this->actingAs($accountant)
            ->get(route('reports.inventory-valuation.export', ['columns' => ['material', 'remaining_kg']]))
            ->assertOk()
            ->assertDownload();

        $this->actingAs($viewer)
            ->get(route('reports.inventory-valuation'))
            ->assertForbidden();
    }
}

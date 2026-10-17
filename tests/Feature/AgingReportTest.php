<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\AgingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_payments_are_allocated_to_oldest_charges_first(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        RecycleOut::create(['date' => '2026-01-01', 'customer_id' => $customer->id, 'weight_kg' => 100, 'recycled_out_kg' => 100, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 100]);
        RecycleOut::create(['date' => '2026-03-20', 'customer_id' => $customer->id, 'weight_kg' => 80, 'recycled_out_kg' => 80, 'waste_kg' => 0, 'non_recycled_kg' => 0, 'rate_per_kg' => 1, 'total_amount' => 80]);
        Payment::create(['date' => '2026-03-25', 'customer_id' => $customer->id, 'amount' => 120, 'payment_type' => 'cash']);

        $row = app(AgingReportService::class)->customers('2026-04-01')->first();

        $this->assertSame(60.0, $row['current']);
        $this->assertSame(0.0, $row['over_90']);
        $this->assertSame(60.0, $row['balance']);
    }

    public function test_supplier_aging_tracks_old_payable_and_unapplied_credit(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);
        StockPurchase::create(['date' => '2026-01-01', 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'weight_kg' => 100, 'cost_per_kg' => 1, 'total_cost' => 100]);
        SupplierPayment::create(['date' => '2026-05-01', 'supplier_id' => $supplier->id, 'amount' => 125, 'payment_type' => 'cash']);

        $row = app(AgingReportService::class)->suppliers('2026-05-15')->first();

        $this->assertSame(25.0, $row['unapplied_credit']);
        $this->assertSame(-25.0, $row['balance']);
    }
}

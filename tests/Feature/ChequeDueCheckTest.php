<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\ChequeDueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChequeDueCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_check_classifies_cheques_without_changing_financial_status(): void
    {
        $this->travelTo('2026-10-17 08:00:00');
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);
        $overdue = Payment::create(['date' => '2026-10-01', 'customer_id' => $customer->id, 'amount' => 100, 'payment_type' => 'cheque', 'cheque_due_date' => '2026-10-16', 'cheque_status' => 'pending']);
        Payment::create(['date' => '2026-10-01', 'customer_id' => $customer->id, 'amount' => 200, 'payment_type' => 'cheque', 'cheque_due_date' => '2026-10-17', 'cheque_status' => 'pending']);
        SupplierPayment::create(['date' => '2026-10-01', 'supplier_id' => $supplier->id, 'amount' => 50, 'payment_type' => 'cheque', 'cheque_due_date' => '2026-10-20', 'cheque_status' => 'pending']);

        $summary = app(ChequeDueService::class)->summary();

        $this->assertSame(1, $summary['incoming']['overdue']);
        $this->assertSame(1, $summary['incoming']['due_today']);
        $this->assertSame(1, $summary['outgoing']['upcoming']);

        $this->artisan('apico:cheque-due-check')->assertSuccessful();
        $this->assertSame($summary, Cache::get('apico.cheque_due_summary'));
        $this->assertSame('pending', $overdue->fresh()->cheque_status);
        $this->assertNull($overdue->fresh()->cheque_settlement_date);
    }
}

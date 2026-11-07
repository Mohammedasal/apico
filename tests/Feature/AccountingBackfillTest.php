<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StockPurchase;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountingSeeder::class);
        Setting::where('key', 'accounting_enabled')->update(['value' => '1']);
    }

    public function test_backfill_is_dry_run_by_default_and_idempotent_when_committed(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'opening_balance' => 50, 'status' => 'active']);
        Payment::create([
            'date' => '2026-01-05',
            'customer_id' => $customer->id,
            'amount' => 20,
            'payment_type' => 'cash',
        ]);
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);
        StockPurchase::create([
            'date' => '2026-01-06',
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'weight_kg' => 100,
            'cost_per_kg' => 0,
            'total_cost' => 0,
        ]);

        $this->artisan('apico:accounting-backfill', ['--from' => '2026-01-01', '--to' => '2026-12-31'])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
        $this->assertSame(0, JournalEntry::count());

        $this->artisan('apico:accounting-backfill', ['--from' => '2026-01-01', '--to' => '2026-12-31', '--commit' => true])
            ->assertSuccessful();
        $this->assertSame(2, JournalEntry::count());

        $this->artisan('apico:accounting-backfill', ['--from' => '2026-01-01', '--to' => '2026-12-31', '--commit' => true])
            ->assertSuccessful();
        $this->assertSame(2, JournalEntry::count());
        $this->assertDatabaseHas('journal_entries', [
            'source_module' => 'backfill',
            'posting_type' => 'opening_balances',
            'status' => 'posted',
        ]);

        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $dashboard = $this->actingAs($accountant)->get(route('accounting.dashboard'));
        $dashboard->assertOk();
        $this->assertSame(100.0, $dashboard->viewData('sourceCoverage')['percentage']);
        $this->assertSame(0, $dashboard->viewData('sourceCoverage')['missing']);
    }
}

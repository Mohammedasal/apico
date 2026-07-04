<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountMapping;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingPostingService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingPostingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AccountingPostingService $posting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccountingSeeder::class);
        Setting::where('key', 'accounting_enabled')->update(['value' => '1']);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($this->admin);
        $this->posting = app(AccountingPostingService::class);
    }

    public function test_balanced_manual_journal_can_be_posted(): void
    {
        $cash = $this->posting->mappedAccount('cash_default');
        $capital = $this->posting->mappedAccount('owner_capital');

        $entry = $this->posting->createPostedEntry([
            'entry_date' => '2026-01-01',
            'memo_en' => 'Opening capital',
            'is_auto' => false,
        ], [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1000],
        ], $this->admin);

        $this->assertSame('posted', $entry->status);
        $this->assertSame(1000.0, $entry->total_debit);
        $this->assertSame(1000.0, $entry->total_credit);
    }

    public function test_unbalanced_manual_journal_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->posting->createPostedEntry(['entry_date' => '2026-01-01'], [
            ['account_id' => $this->posting->mappedAccount('cash_default')->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->posting->mappedAccount('owner_capital')->id, 'debit' => 0, 'credit' => 90],
        ], $this->admin);
    }

    public function test_customer_cash_payment_posts_once_to_cash_and_receivables(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $payment = Payment::create([
            'date' => '2026-01-05',
            'customer_id' => $customer->id,
            'amount' => 700,
            'payment_type' => 'cash',
        ]);

        $this->posting->postOperational($payment, $this->admin);
        $this->posting->postOperational($payment, $this->admin);

        $this->assertDatabaseCount('journal_entries', 1);
        $entry = JournalEntry::with('lines.account')->firstOrFail();
        $this->assertSame(700.0, $entry->total_debit);
        $this->assertSame(700.0, $entry->total_credit);
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '1110' && (float) $line->debit === 700.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '1300' && (float) $line->credit === 700.0));
    }

    public function test_stock_purchase_and_sale_create_inventory_revenue_and_cogs_entries(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $material = Material::create(['name' => 'PET', 'type' => 'stock', 'is_active' => true]);
        $purchase = StockPurchase::create([
            'date' => '2026-01-01',
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'material_id' => $material->id,
            'weight_kg' => 60000,
            'cost_per_kg' => 0.3,
            'total_cost' => 18000,
        ]);
        $sale = StockSale::create([
            'date' => '2026-01-02',
            'customer_id' => $customer->id,
            'material_id' => $material->id,
            'weight_kg' => 1000,
            'selling_price_per_kg' => 0.6,
            'sales_value' => 600,
            'purchase_cost_per_kg' => 0.3,
            'granulation_cost_per_kg' => 0,
            'net_profit' => 300,
        ]);

        $this->posting->postOperational($purchase, $this->admin);
        $entries = $this->posting->postOperational($sale, $this->admin);

        $this->assertCount(2, $entries);
        $this->assertDatabaseCount('journal_entries', 3);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->posting->mappedAccount('stock_sales_income')->id,
            'credit' => 600,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->posting->mappedAccount('stock_material_cogs')->id,
            'debit' => 300,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->posting->mappedAccount('inventory_default')->id,
            'credit' => 300,
        ]);
    }

    public function test_edit_reverses_and_reposts_source_without_duplicate_active_entry(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $payment = Payment::create([
            'date' => '2026-01-05',
            'customer_id' => $customer->id,
            'amount' => 100,
            'payment_type' => 'cash',
        ]);
        $this->posting->postOperational($payment, $this->admin);

        $payment->update(['amount' => 150]);
        $this->posting->repostOperational($payment->fresh(), $this->admin);

        $this->assertSame(3, JournalEntry::count());
        $this->assertSame(1, JournalEntry::where('status', 'reversed')->count());
        $this->assertSame(1, JournalEntry::where('posting_type', 'customer_payment')->where('status', 'posted')->count());
        $this->assertSame(150.0, JournalEntry::where('posting_type', 'customer_payment')->where('status', 'posted')->firstOrFail()->total_debit);
    }

    public function test_locked_period_rejects_new_posting(): void
    {
        AccountingPeriod::create([
            'period_year' => 2026,
            'period_month' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $this->admin->id,
        ]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $payment = Payment::create([
            'date' => '2026-01-05',
            'customer_id' => $customer->id,
            'amount' => 100,
            'payment_type' => 'cash',
        ]);

        $this->expectException(ValidationException::class);
        $this->posting->postOperational($payment, $this->admin);
    }

    public function test_existing_payment_screen_saves_operation_and_journal_once(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);

        $response = $this->post(route('operations.store', 'payments'), [
            'date' => '2026-02-01',
            'customer_id' => $customer->id,
            'amount' => 250,
            'payment_type' => 'cash',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_entry_lines', 2);
    }

    public function test_failed_accounting_posting_rolls_back_operational_payment(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        AccountMapping::where('mapping_key', 'cash_default')->delete();

        $response = $this->post(route('operations.store', 'payments'), [
            'date' => '2026-02-01',
            'customer_id' => $customer->id,
            'amount' => 250,
            'payment_type' => 'cash',
        ]);

        $response->assertSessionHasErrors('account_mapping');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}

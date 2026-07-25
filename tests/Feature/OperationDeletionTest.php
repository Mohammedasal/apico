<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\Setting;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingPostingService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_each_operational_transaction_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Supplier', 'status' => 'active']);

        $records = [
            'recycle-in' => RecycleIn::create([
                'date' => '2026-01-01', 'customer_id' => $customer->id,
                'weight_kg' => 100, 'rate_per_kg' => 0, 'total_amount' => 0,
            ]),
            'recycle-out' => RecycleOut::create([
                'date' => '2026-01-02', 'customer_id' => $customer->id,
                'weight_kg' => 80, 'recycled_out_kg' => 80, 'waste_kg' => 0,
                'non_recycled_kg' => 0, 'rate_per_kg' => 0.1, 'total_amount' => 8,
            ]),
            'payments' => Payment::create([
                'date' => '2026-01-03', 'customer_id' => $customer->id,
                'amount' => 5, 'payment_type' => 'cash',
            ]),
            'stock-purchases' => StockPurchase::create([
                'date' => '2026-01-04', 'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name, 'weight_kg' => 50,
                'cost_per_kg' => 0.2, 'total_cost' => 10,
            ]),
            'stock-sales' => StockSale::create([
                'date' => '2026-01-05', 'customer_id' => $customer->id,
                'weight_kg' => 20, 'selling_price_per_kg' => 0.5,
                'sales_value' => 10, 'purchase_cost_per_kg' => 0.2,
                'granulation_cost_per_kg' => 0, 'net_profit' => 6,
            ]),
        ];

        foreach ($records as $module => $record) {
            $response = $this->actingAs($admin)->delete(route('operations.destroy', [$module, $record->id]));

            $response->assertRedirect(route('operations.index', $module));
            $this->assertNull($record->fresh());
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'operation_deleted',
                'model_type' => $record::class,
                'model_id' => $record->id,
                'user_id' => $admin->id,
            ]);
        }

        $this->assertSame(5, AuditLog::where('action', 'operation_deleted')->count());
    }

    public function test_non_admin_roles_cannot_delete_transactions(): void
    {
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $record = RecycleIn::create([
            'date' => '2026-01-01', 'customer_id' => $customer->id,
            'weight_kg' => 100, 'rate_per_kg' => 0, 'total_amount' => 0,
        ]);

        foreach (['data_entry', 'accountant', 'viewer'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)
                ->delete(route('operations.destroy', ['recycle-in', $record->id]))
                ->assertForbidden();
            $this->assertNotNull($record->fresh());
        }
    }

    public function test_deleting_settled_customer_cheque_reverses_original_and_settlement_journals(): void
    {
        $this->seed(AccountingSeeder::class);
        Setting::where('key', 'accounting_enabled')->update(['value' => '1']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $bank = BankAccount::where('is_default', true)->firstOrFail();
        $payment = Payment::create([
            'date' => '2026-01-01',
            'customer_id' => $customer->id,
            'amount' => 500,
            'payment_type' => 'cheque',
            'cheque_due_date' => '2026-01-15',
            'cheque_status' => 'pending',
        ]);
        $posting = app(AccountingPostingService::class);
        $posting->postOperational($payment, $admin);
        $payment->update([
            'cheque_status' => 'collected',
            'cheque_settlement_date' => '2026-01-15',
            'cheque_bank_account_id' => $bank->id,
        ]);
        $posting->repostIncomingChequeSettlement($payment->fresh(), $admin);

        $sourceEntries = JournalEntry::where('source_type', 'Payment')
            ->where('source_id', $payment->id)
            ->whereNull('reversal_of_journal_entry_id');
        $this->assertSame(2, (clone $sourceEntries)->where('status', 'posted')->count());

        $this->actingAs($admin)
            ->delete(route('operations.destroy', ['payments', $payment->id]))
            ->assertRedirect(route('operations.index', 'payments'));

        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertSame(2, (clone $sourceEntries)->where('status', 'reversed')->count());
        $this->assertSame(2, JournalEntry::where('source_type', 'Payment')
            ->where('source_id', $payment->id)
            ->whereNotNull('reversal_of_journal_entry_id')
            ->where('status', 'posted')
            ->count());
        $this->assertSame(0.0, (float) JournalEntry::where('source_type', 'Payment')
            ->where('source_id', $payment->id)
            ->with('lines')
            ->get()
            ->sum(fn (JournalEntry $entry) => $entry->lines->sum('debit') - $entry->lines->sum('credit')));
    }
}

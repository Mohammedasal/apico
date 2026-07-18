<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChequeSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BankAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingSeeder::class);
        Setting::where('key', 'accounting_enabled')->update(['value' => '1']);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->bank = BankAccount::where('is_default', true)->firstOrFail();
        $this->actingAs($this->admin);
    }

    public function test_collected_customer_cheque_moves_receivable_cheque_to_bank(): void
    {
        $payment = $this->createCustomerCheque();

        $this->put(route('cheques-in.update', $payment), [
            'cheque_status' => 'collected',
            'cheque_settlement_date' => '2026-02-15',
            'cheque_bank_account_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::with('lines.account')
            ->where('posting_type', 'customer_cheque_collected')->firstOrFail();
        $this->assertSame('collected', $payment->fresh()->cheque_status);
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '1210' && (float) $line->debit === 700.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '1400' && (float) $line->credit === 700.0));
        $this->assertTrue($entry->lines->every(fn ($line) => $line->cheque_id === $payment->id));
    }

    public function test_collected_customer_cheque_can_be_corrected_to_bounced(): void
    {
        $payment = $this->createCustomerCheque();
        $this->put(route('cheques-in.update', $payment), [
            'cheque_status' => 'collected',
            'cheque_settlement_date' => '2026-02-15',
            'cheque_bank_account_id' => $this->bank->id,
        ]);

        $this->put(route('cheques-in.update', $payment), [
            'cheque_status' => 'bounced',
            'cheque_settlement_date' => '2026-02-16',
        ])->assertSessionHasNoErrors();

        $bounced = JournalEntry::with('lines.account')
            ->where('posting_type', 'customer_cheque_bounced')->where('status', 'posted')->firstOrFail();
        $this->assertSame('reversed', JournalEntry::where('posting_type', 'customer_cheque_collected')->firstOrFail()->status);
        $this->assertTrue($bounced->lines->contains(fn ($line) => $line->account->code === '1300' && (float) $line->debit === 700.0));
        $this->assertTrue($bounced->lines->contains(fn ($line) => $line->account->code === '1400' && (float) $line->credit === 700.0));
    }

    public function test_supplier_cheque_clearance_moves_cheques_payable_to_bank_then_can_be_cancelled(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier One', 'status' => 'active']);
        $this->post(route('supplier-payments.store'), [
            'date' => '2026-02-01',
            'supplier_id' => $supplier->id,
            'amount' => 450,
            'payment_type' => 'cheque',
            'cheque_due_date' => '2026-02-20',
            'cheque_status' => 'pending',
        ])->assertSessionHasNoErrors();
        $payment = SupplierPayment::firstOrFail();

        $this->put(route('cheques-out.supplier.update', $payment), [
            'cheque_status' => 'cleared',
            'cheque_settlement_date' => '2026-02-20',
            'cheque_bank_account_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();

        $clearance = JournalEntry::with('lines.account')
            ->where('posting_type', 'supplier_cheque_cleared')->firstOrFail();
        $this->assertTrue($clearance->lines->contains(fn ($line) => $line->account->code === '2200' && (float) $line->debit === 450.0));
        $this->assertTrue($clearance->lines->contains(fn ($line) => $line->account->code === '1210' && (float) $line->credit === 450.0));

        $this->put(route('cheques-out.supplier.update', $payment), [
            'cheque_status' => 'cancelled',
            'cheque_settlement_date' => '2026-02-21',
        ])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $payment->fresh()->cheque_status);
        $this->assertSame('reversed', $clearance->fresh()->status);
        $this->assertSame('reversed', JournalEntry::where('posting_type', 'supplier_payment')->firstOrFail()->status);
    }

    public function test_expense_cheque_clearance_moves_cheques_payable_to_bank(): void
    {
        $category = ExpenseCategory::where('name_en', 'Rent')->firstOrFail();
        $this->post(route('accounting.expenses.store'), [
            'expense_date' => '2026-02-01',
            'expense_category_id' => $category->id,
            'amount' => 300,
            'payment_status' => 'paid',
            'payment_type' => 'cheque',
            'cheque_due_date' => '2026-02-28',
        ])->assertSessionHasNoErrors();
        $voucher = ExpenseVoucher::firstOrFail();

        $this->put(route('cheques-out.expense.update', $voucher), [
            'cheque_status' => 'cleared',
            'cheque_settlement_date' => '2026-02-28',
            'cheque_bank_account_id' => $this->bank->id,
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::with('lines.account')
            ->where('posting_type', 'expense_cheque_cleared')->firstOrFail();
        $this->assertSame('cleared', $voucher->fresh()->cheque_status);
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '2200' && (float) $line->debit === 300.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '1210' && (float) $line->credit === 300.0));
    }

    public function test_collected_or_cleared_cheque_requires_bank_account(): void
    {
        $payment = $this->createCustomerCheque();

        $this->put(route('cheques-in.update', $payment), [
            'cheque_status' => 'collected',
            'cheque_settlement_date' => '2026-02-15',
        ])->assertSessionHasErrors('cheque_bank_account_id');

        $this->assertSame('pending', $payment->fresh()->cheque_status);
        $this->assertDatabaseMissing('journal_entries', ['posting_type' => 'customer_cheque_collected']);
    }

    private function createCustomerCheque(): Payment
    {
        $customer = Customer::create(['name' => 'Customer One', 'status' => 'active']);
        $this->post(route('operations.store', 'payments'), [
            'date' => '2026-02-01',
            'customer_id' => $customer->id,
            'amount' => 700,
            'payment_type' => 'cheque',
            'cheque_due_date' => '2026-02-15',
            'cheque_status' => 'pending',
        ])->assertSessionHasNoErrors();

        return Payment::firstOrFail();
    }
}

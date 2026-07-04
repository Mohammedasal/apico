<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseVoucherTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->category = ExpenseCategory::where('name_en', 'Rent')->firstOrFail();
        $this->actingAs($this->admin);
    }

    public function test_cash_expense_posts_expense_debit_and_cash_credit(): void
    {
        $response = $this->post(route('accounting.expenses.store'), $this->voucherData([
            'amount' => 500,
            'payment_status' => 'paid',
            'payment_type' => 'cash',
        ]));

        $voucher = ExpenseVoucher::firstOrFail();
        $response->assertRedirect(route('accounting.expenses.show', $voucher));
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $this->category->default_account_id,
            'debit' => 500,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => ChartOfAccount::where('code', '1110')->value('id'),
            'credit' => 500,
        ]);
    }

    public function test_bank_expense_uses_selected_bank_account(): void
    {
        $bank = BankAccount::where('is_default', true)->with('chartAccount')->firstOrFail();

        $this->post(route('accounting.expenses.store'), $this->voucherData([
            'amount' => 250,
            'payment_status' => 'paid',
            'payment_type' => 'bank_transfer',
            'bank_account_id' => $bank->id,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $bank->chart_account_id,
            'bank_account_id' => $bank->id,
            'credit' => 250,
        ]);
    }

    public function test_unpaid_expense_posts_to_accrued_expenses(): void
    {
        $this->post(route('accounting.expenses.store'), $this->voucherData([
            'amount' => 300,
            'payment_status' => 'unpaid',
            'payment_type' => 'credit',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => ChartOfAccount::where('code', '2300')->value('id'),
            'credit' => 300,
        ]);
        $this->assertSame(0.0, (float) ExpenseVoucher::firstOrFail()->paid_amount);
    }

    public function test_partial_expense_splits_credit_between_cash_and_accrual(): void
    {
        $this->post(route('accounting.expenses.store'), $this->voucherData([
            'amount' => 100,
            'paid_amount' => 30,
            'payment_status' => 'partially_paid',
            'payment_type' => 'cash',
        ]))->assertSessionHasNoErrors();

        $entry = JournalEntry::with('lines')->firstOrFail();
        $this->assertSame(100.0, $entry->total_debit);
        $this->assertSame(100.0, $entry->total_credit);
        $this->assertTrue($entry->lines->contains(fn ($line) => (float) $line->credit === 30.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => (float) $line->credit === 70.0));
    }

    public function test_cheque_expense_requires_due_date_and_posts_cheques_payable(): void
    {
        $invalid = $this->post(route('accounting.expenses.store'), $this->voucherData([
            'amount' => 400,
            'payment_status' => 'paid',
            'payment_type' => 'cheque',
        ]));
        $invalid->assertSessionHasErrors('cheque_due_date');

        $valid = $this->post(route('accounting.expenses.store'), $this->voucherData([
            'amount' => 400,
            'payment_status' => 'paid',
            'payment_type' => 'cheque',
            'cheque_due_date' => '2026-03-01',
        ]));
        $valid->assertSessionHasNoErrors();
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => ChartOfAccount::where('code', '2200')->value('id'),
            'credit' => 400,
        ]);
    }

    public function test_cancelling_voucher_reverses_journal_and_preserves_audit_history(): void
    {
        $this->post(route('accounting.expenses.store'), $this->voucherData([
            'amount' => 500,
            'payment_status' => 'paid',
            'payment_type' => 'cash',
        ]));
        $voucher = ExpenseVoucher::firstOrFail();

        $this->post(route('accounting.expenses.cancel', $voucher))->assertRedirect(route('accounting.expenses.show', $voucher));

        $this->assertSame('cancelled', $voucher->fresh()->status);
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame(1, JournalEntry::where('status', 'reversed')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense_voucher_cancelled', 'model_id' => $voucher->id]);
    }

    public function test_locked_period_rolls_back_expense_voucher(): void
    {
        AccountingPeriod::create([
            'period_year' => 2026,
            'period_month' => 2,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'status' => 'locked',
        ]);

        $this->post(route('accounting.expenses.store'), $this->voucherData([
            'amount' => 500,
            'payment_status' => 'paid',
            'payment_type' => 'cash',
        ]))->assertSessionHasErrors('entry_date');

        $this->assertDatabaseCount('expense_vouchers', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_only_admin_and_accountant_can_access_expenses(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        $dataEntry = User::factory()->create(['role' => 'data_entry', 'is_active' => true]);

        $this->actingAs($accountant)->get(route('accounting.expenses.index'))->assertOk();
        $this->actingAs($viewer)->get(route('accounting.expenses.index'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('accounting.expenses.index'))->assertForbidden();
    }

    public function test_expense_report_exports_selected_columns(): void
    {
        ExpenseVoucher::create([
            'voucher_no' => 'EV-TEST-1',
            'expense_date' => '2026-02-01',
            'expense_category_id' => $this->category->id,
            'amount' => 100,
            'paid_amount' => 100,
            'payment_status' => 'paid',
            'payment_type' => 'cash',
            'status' => 'posted',
        ]);

        $response = $this->get(route('accounting.expenses.export', [
            'from' => '2026-02-01',
            'to' => '2026-02-28',
            'columns' => ['date', 'voucher_no', 'amount'],
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function voucherData(array $overrides = []): array
    {
        return array_merge([
            'expense_date' => '2026-02-01',
            'expense_category_id' => $this->category->id,
            'amount' => 100,
            'paid_amount' => 0,
            'payment_status' => 'paid',
            'payment_type' => 'cash',
        ], $overrides);
    }
}

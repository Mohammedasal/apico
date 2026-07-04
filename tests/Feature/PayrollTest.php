<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->employee = Employee::create([
            'name_en' => 'Employee One',
            'name_ar' => 'الموظف الأول',
            'base_salary' => 500,
            'is_active' => true,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_draft_payroll_calculates_employee_and_run_totals(): void
    {
        $response = $this->post(route('accounting.payroll.store'), $this->payrollData());

        $run = PayrollRun::with('lines')->firstOrFail();
        $response->assertRedirect(route('accounting.payroll.show', $run));
        $this->assertSame('draft', $run->status);
        $this->assertSame(500.0, (float) $run->total_gross);
        $this->assertSame(50.0, (float) $run->total_allowances);
        $this->assertSame(25.0, (float) $run->total_deductions);
        $this->assertSame(525.0, (float) $run->total_net);
        $this->assertSame(525.0, (float) $run->lines->first()->net_salary);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_posting_payroll_creates_balanced_accrual_with_employee_dimensions(): void
    {
        $run = $this->createDraftPayroll();

        $this->post(route('accounting.payroll.post', $run))->assertSessionHasNoErrors();

        $run->refresh();
        $entry = JournalEntry::with('lines.account')->firstOrFail();
        $this->assertSame('posted', $run->status);
        $this->assertSame(560.0, $entry->total_debit);
        $this->assertSame(560.0, $entry->total_credit);
        $this->assertTrue($entry->lines->every(fn ($line) => $line->employee_id === $this->employee->id));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '6100' && (float) $line->debit === 550.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '2400' && (float) $line->credit === 525.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '2500' && (float) $line->credit === 35.0));
    }

    public function test_cash_payroll_payment_clears_salary_payable(): void
    {
        $run = $this->createDraftPayroll();
        $this->post(route('accounting.payroll.post', $run));

        $this->post(route('accounting.payroll.pay', $run), [
            'payment_date' => '2026-03-05',
            'payment_type' => 'cash',
            'payment_reference' => 'PAY-1',
        ])->assertSessionHasNoErrors();

        $run->refresh();
        $payment = JournalEntry::with('lines.account')->where('posting_type', 'payroll_payment')->firstOrFail();
        $this->assertSame('paid', $run->status);
        $this->assertSame(525.0, $payment->total_debit);
        $this->assertSame(525.0, $payment->total_credit);
        $this->assertTrue($payment->lines->contains(fn ($line) => $line->account->code === '2400' && (float) $line->debit === 525.0));
        $this->assertTrue($payment->lines->contains(fn ($line) => $line->account->code === '1110' && (float) $line->credit === 525.0));
    }

    public function test_bank_payroll_payment_uses_selected_bank(): void
    {
        $run = $this->createDraftPayroll();
        $this->post(route('accounting.payroll.post', $run));
        $bank = BankAccount::where('is_default', true)->firstOrFail();

        $this->post(route('accounting.payroll.pay', $run), [
            'payment_date' => '2026-03-05',
            'payment_type' => 'bank_transfer',
            'bank_account_id' => $bank->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $bank->chart_account_id,
            'bank_account_id' => $bank->id,
            'credit' => 525,
        ]);
    }

    public function test_cancelling_paid_payroll_reverses_accrual_and_payment(): void
    {
        $run = $this->createDraftPayroll();
        $this->post(route('accounting.payroll.post', $run));
        $this->post(route('accounting.payroll.pay', $run), [
            'payment_date' => '2026-03-05',
            'payment_type' => 'cash',
        ]);

        $this->post(route('accounting.payroll.cancel', $run))->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $run->fresh()->status);
        $this->assertSame(4, JournalEntry::count());
        $this->assertSame(2, JournalEntry::where('status', 'reversed')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payroll_cancelled', 'model_id' => $run->id]);
    }

    public function test_locked_period_prevents_payroll_posting(): void
    {
        $run = $this->createDraftPayroll();
        AccountingPeriod::create([
            'period_year' => 2026,
            'period_month' => 2,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
            'status' => 'locked',
        ]);

        $this->post(route('accounting.payroll.post', $run))->assertSessionHasErrors('entry_date');

        $this->assertSame('draft', $run->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_payroll_export_and_permissions(): void
    {
        $this->createDraftPayroll();
        $this->get(route('accounting.payroll.export'))->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        $this->actingAs($accountant)->get(route('accounting.payroll.index'))->assertOk();
        $this->actingAs($viewer)->get(route('accounting.payroll.index'))->assertForbidden();
    }

    public function test_employee_management_and_payroll_accounts_are_valid(): void
    {
        $this->assertDatabaseHas('employees', ['name_en' => 'Employee One']);
        $this->assertTrue(ChartOfAccount::where('code', '6100')->where('is_posting', true)->exists());
        $this->assertTrue(ChartOfAccount::where('code', '2400')->where('is_posting', true)->exists());
    }

    private function createDraftPayroll(): PayrollRun
    {
        $this->post(route('accounting.payroll.store'), $this->payrollData())->assertSessionHasNoErrors();

        return PayrollRun::firstOrFail();
    }

    private function payrollData(): array
    {
        return [
            'period_year' => 2026,
            'period_month' => 2,
            'payroll_date' => '2026-02-28',
            'lines' => [[
                'include' => 1,
                'employee_id' => $this->employee->id,
                'gross_salary' => 500,
                'allowances' => 50,
                'deductions' => 25,
                'employer_social_security' => 10,
            ]],
        ];
    }
}

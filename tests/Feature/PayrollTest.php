<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
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
        $this->assertSame(5.0, (float) $run->total_deductions);
        $this->assertSame(25.0, (float) $run->total_employee_social_security);
        $this->assertSame(520.0, (float) $run->total_net);
        $this->assertSame(520.0, (float) $run->lines->first()->net_salary);
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
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '2400' && (float) $line->credit === 520.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '2500' && (float) $line->credit === 35.0));
        $this->assertTrue($entry->lines->contains(fn ($line) => $line->account->code === '2300' && (float) $line->credit === 5.0));
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
        $this->assertSame(520.0, $payment->total_debit);
        $this->assertSame(520.0, $payment->total_credit);
        $this->assertTrue($payment->lines->contains(fn ($line) => $line->account->code === '2400' && (float) $line->debit === 520.0));
        $this->assertTrue($payment->lines->contains(fn ($line) => $line->account->code === '1110' && (float) $line->credit === 520.0));
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
            'credit' => 520,
        ]);
    }

    public function test_social_security_payment_clears_social_security_payable(): void
    {
        $run = $this->createDraftPayroll();
        $this->post(route('accounting.payroll.post', $run));

        $this->post(route('accounting.payroll.pay-social-security', $run), [
            'social_security_payment_date' => '2026-03-10',
            'social_security_payment_type' => 'cash',
            'social_security_payment_reference' => 'SSC-1',
        ])->assertSessionHasNoErrors();

        $run->refresh();
        $payment = JournalEntry::with('lines.account')
            ->where('posting_type', 'payroll_social_security_payment')
            ->firstOrFail();
        $this->assertNotNull($run->social_security_paid_at);
        $this->assertSame(35.0, $payment->total_debit);
        $this->assertSame(35.0, $payment->total_credit);
        $this->assertTrue($payment->lines->contains(fn ($line) => $line->account->code === '2500' && (float) $line->debit === 35.0));
        $this->assertTrue($payment->lines->contains(fn ($line) => $line->account->code === '1110' && (float) $line->credit === 35.0));
    }

    public function test_multiple_salary_advances_reduce_the_final_payroll_payment(): void
    {
        $this->post(route('accounting.salary-advances.store'), [
            'employee_id' => $this->employee->id,
            'payment_date' => '2026-02-07',
            'amount' => 30,
            'payment_type' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->post(route('accounting.salary-advances.store'), [
            'employee_id' => $this->employee->id,
            'payment_date' => '2026-02-10',
            'amount' => 50,
            'payment_type' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, SalaryAdvance::whereNull('payroll_run_id')->count());
        $run = $this->createDraftPayroll();

        $run->refresh()->load('advances');
        $this->assertSame(80.0, $run->total_advances);
        $this->assertSame(440.0, $run->remaining_salary);
        $this->assertDatabaseCount('salary_advances', 2);
        $this->assertSame(2, JournalEntry::where('posting_type', 'salary_advance')->count());

        $this->post(route('accounting.payroll.post', $run))->assertSessionHasNoErrors();
        $this->post(route('accounting.payroll.pay', $run), [
            'payment_date' => '2026-02-28',
            'payment_type' => 'cash',
        ])->assertSessionHasNoErrors();

        $payment = JournalEntry::with('lines.account')
            ->where('posting_type', 'payroll_payment')
            ->firstOrFail();
        $this->assertSame(440.0, $payment->total_debit);
        $this->assertSame(440.0, $payment->total_credit);
        $this->assertTrue($payment->lines->contains(
            fn ($line) => $line->account->code === '1110' && (float) $line->credit === 440.0
        ));
    }

    public function test_salary_advance_can_be_cancelled_before_payroll(): void
    {
        $this->post(route('accounting.salary-advances.store'), [
            'employee_id' => $this->employee->id,
            'payment_date' => '2026-02-07',
            'amount' => 500,
            'payment_type' => 'cash',
        ])->assertSessionHasNoErrors();

        $advance = SalaryAdvance::firstOrFail();
        $this->post(route('accounting.salary-advances.cancel', $advance))
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $advance->fresh()->status);
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame('reversed', JournalEntry::where('posting_type', 'salary_advance')->firstOrFail()->status);
    }

    public function test_payroll_cannot_be_below_advances_recorded_before_month_end(): void
    {
        $this->post(route('accounting.salary-advances.store'), [
            'employee_id' => $this->employee->id,
            'payment_date' => '2026-02-07',
            'amount' => 521,
            'payment_type' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->post(route('accounting.payroll.store'), $this->payrollData())
            ->assertSessionHasErrors('lines');

        $this->assertDatabaseCount('payroll_runs', 0);
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

    public function test_cancelling_payroll_keeps_paid_advances_for_a_replacement_payroll(): void
    {
        $this->post(route('accounting.salary-advances.store'), [
            'employee_id' => $this->employee->id,
            'payment_date' => '2026-02-07',
            'amount' => 80,
            'payment_type' => 'cash',
        ])->assertSessionHasNoErrors();

        $run = $this->createDraftPayroll();
        $this->post(route('accounting.payroll.post', $run))->assertSessionHasNoErrors();
        $this->post(route('accounting.payroll.cancel', $run))->assertSessionHasNoErrors();

        $advance = SalaryAdvance::firstOrFail();
        $this->assertSame('posted', $advance->status);
        $this->assertNull($advance->payroll_run_id);
        $this->assertSame('posted', JournalEntry::where('posting_type', 'salary_advance')->firstOrFail()->status);
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
                'employee_social_security' => 25,
                'deductions' => 5,
                'employer_social_security' => 10,
            ]],
        ];
    }
}

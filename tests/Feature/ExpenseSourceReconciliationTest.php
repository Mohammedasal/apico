<?php

namespace Tests\Feature;

use App\Models\MonthlyExpense;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountingPostingService;
use App\Services\ExpenseSourceService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseSourceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ExpenseSourceService $expenses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->expenses = app(ExpenseSourceService::class);

        MonthlyExpense::create(['year' => 2026, 'month' => 1, 'rent' => 100]);
        MonthlyExpense::create(['year' => 2026, 'month' => 2, 'rent' => 200]);

        $posting = app(AccountingPostingService::class);
        $cash = $posting->mappedAccount('cash_default');
        $rent = $posting->mappedAccount('rent_expense');
        foreach ([['2026-01-15', 90], ['2026-02-15', 250]] as [$date, $amount]) {
            $posting->createPostedEntry(['entry_date' => $date], [
                ['account_id' => $rent->id, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount],
            ], $this->admin);
        }
    }

    public function test_legacy_source_remains_the_safe_default(): void
    {
        $this->assertSame(ExpenseSourceService::LEGACY, $this->expenses->source());
        $this->assertSame(300.0, $this->expenses->total('2026-01-01', '2026-02-28'));
    }

    public function test_accounting_source_reads_posted_expense_journals(): void
    {
        Setting::where('key', 'monthly_expense_source')->update(['value' => ExpenseSourceService::ACCOUNTING]);

        $this->assertSame(340.0, $this->expenses->total('2026-01-01', '2026-02-28'));
    }

    public function test_cutover_uses_legacy_before_date_and_accounting_after_date_without_overlap(): void
    {
        Setting::where('key', 'monthly_expense_source')->update(['value' => ExpenseSourceService::CUTOVER]);
        Setting::where('key', 'expense_cutover_date')->update(['value' => '2026-02-01']);

        $this->assertSame(350.0, $this->expenses->total('2026-01-01', '2026-02-28'));
    }

    public function test_reconciliation_report_shows_monthly_differences_and_exports(): void
    {
        $response = $this->actingAs($this->admin)->get(route('accounting.reports.expense-reconciliation', [
            'year' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('2026-01')
            ->assertSee('2026-02')
            ->assertViewHas('totals', fn (array $totals) => $totals['legacy'] === 300.0
                && $totals['accounting'] === 340.0
                && $totals['difference'] === 40.0);

        $this->actingAs($this->admin)->get(route('accounting.reports.expense-reconciliation.export', [
            'year' => 2026,
            'columns' => ['month', 'difference'],
        ]))->assertOk()->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function test_settings_reject_cutover_that_is_not_first_day_of_month(): void
    {
        $response = $this->actingAs($this->admin)->put(route('settings.update'), [
            'currency' => 'JOD',
            'default_granulation_cost_per_kg' => 0,
            'high_balance_threshold' => 1000,
            'allow_stock_override' => 0,
            'accounting_enabled' => 1,
            'monthly_expense_source' => ExpenseSourceService::CUTOVER,
            'expense_cutover_date' => '2026-02-15',
        ]);

        $response->assertSessionHasErrors('expense_cutover_date');
        $this->assertSame(ExpenseSourceService::LEGACY, $this->expenses->source());
    }
}

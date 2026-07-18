<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AccountingPostingService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialBalanceTest extends TestCase
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
        $this->posting = app(AccountingPostingService::class);
    }

    public function test_trial_balance_shows_opening_period_and_balanced_closing_totals(): void
    {
        $cash = $this->posting->mappedAccount('cash_default');
        $capital = $this->posting->mappedAccount('owner_capital');
        $income = $this->posting->mappedAccount('other_income');

        $this->posting->createPostedEntry(['entry_date' => '2025-12-31'], [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1000],
        ], $this->admin);
        $this->posting->createPostedEntry(['entry_date' => '2026-01-15'], [
            ['account_id' => $cash->id, 'debit' => 250, 'credit' => 0],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => 250],
        ], $this->admin);

        $response = $this->actingAs($this->admin)->get(route('accounting.reports.trial-balance', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ]));

        $response->assertOk()
            ->assertSee('Trial Balance')
            ->assertSee('1,000.000')
            ->assertSee('250.000')
            ->assertSee('1,250.000')
            ->assertSee('Trial balance is balanced.');
        $this->assertSame(1250.0, $response->viewData('totals')['closing_debit']);
        $this->assertSame(1250.0, $response->viewData('totals')['closing_credit']);
        $this->assertSame(0.0, $response->viewData('difference'));
    }

    public function test_reversed_entry_and_its_reversal_net_to_zero(): void
    {
        $cash = $this->posting->mappedAccount('cash_default');
        $capital = $this->posting->mappedAccount('owner_capital');
        $entry = $this->posting->createPostedEntry(['entry_date' => '2026-02-01'], [
            ['account_id' => $cash->id, 'debit' => 400, 'credit' => 0],
            ['account_id' => $capital->id, 'debit' => 0, 'credit' => 400],
        ], $this->admin);
        $this->posting->reverse($entry, $this->admin);

        $response = $this->actingAs($this->admin)->get(route('accounting.reports.trial-balance', [
            'from' => '2026-02-01',
            'to' => '2026-02-28',
        ]));

        $response->assertOk();
        $this->assertSame(0.0, $response->viewData('totals')['closing_debit']);
        $this->assertSame(0.0, $response->viewData('totals')['closing_credit']);
        $this->assertCount(2, $response->viewData('rows'));
    }

    public function test_export_honors_selected_columns(): void
    {
        $response = $this->actingAs($this->admin)->get(route('accounting.reports.trial-balance.export', [
            'from' => '2026-01-01',
            'to' => '2026-12-31',
            'columns' => ['code', 'closing_debit', 'closing_credit'],
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
    }

    public function test_accountant_can_view_trial_balance_but_operational_roles_cannot(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $this->actingAs($accountant)->get(route('accounting.reports.trial-balance'))->assertOk();

        foreach (['data_entry', 'viewer'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('accounting.reports.trial-balance'))->assertForbidden();
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_authenticated_pages(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('customers.index'))->assertRedirect(route('login'));
        $this->get(route('accounting.dashboard'))->assertRedirect(route('login'));
    }

    public function test_viewer_can_read_operations_but_not_profit_cheques_or_accounting(): void
    {
        $viewer = $this->user('viewer');

        $this->actingAs($viewer)->get(route('customers.index'))->assertOk();
        $this->actingAs($viewer)->get(route('operations.index', ['module' => 'recycle-out']))->assertOk();
        $this->actingAs($viewer)->get(route('reports.monthly'))->assertForbidden();
        $this->actingAs($viewer)->get(route('cheques-in.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('accounting.dashboard'))->assertForbidden();
    }

    public function test_data_entry_can_enter_operations_but_not_financial_or_system_pages(): void
    {
        $dataEntry = $this->user('data_entry');

        $this->actingAs($dataEntry)->get(route('operations.create', ['module' => 'payments']))->assertOk();
        $this->actingAs($dataEntry)->get(route('cheques-in.index'))->assertOk();
        $this->actingAs($dataEntry)->get(route('reports.monthly'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('accounting.dashboard'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($dataEntry)->get(route('users.index'))->assertForbidden();
    }

    public function test_accountant_can_read_financial_and_accounting_reports_but_not_admin_pages(): void
    {
        $accountant = $this->user('accountant');

        foreach ([
            'production.index',
            'reports.monthly',
            'reports.stock-profit',
            'accounting.dashboard',
            'accounting.reports.trial-balance',
            'accounting.reports.general-ledger',
            'accounting.reports.profit-loss',
            'accounting.reports.balance-sheet',
            'accounting.reports.receivables-aging',
            'accounting.reports.payables-aging',
            'accounting.reports.cash-bank',
        ] as $route) {
            $this->actingAs($accountant)->get(route($route))->assertOk();
        }

        $this->actingAs($accountant)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('users.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('accounting.accounts.index'))->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }
}

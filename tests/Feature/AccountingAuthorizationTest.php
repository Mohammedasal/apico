<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\User;
use Database\Seeders\AccountingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingSeeder::class);
    }

    public function test_admin_can_manage_chart_of_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('accounting.accounts.store'), [
            'code' => '6990',
            'name_en' => 'Test Expense',
            'name_ar' => 'مصاريف مكتبية',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_posting' => 1,
            'is_active' => 1,
            'sort_order' => 699,
        ]);

        $response->assertRedirect(route('accounting.accounts.index'));
        $this->assertDatabaseHas('chart_of_accounts', ['code' => '6990', 'name_en' => 'Test Expense']);
    }

    public function test_posting_account_cannot_be_used_as_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $postingParent = ChartOfAccount::where('is_posting', true)->firstOrFail();

        $response = $this->actingAs($admin)->post(route('accounting.accounts.store'), [
            'parent_id' => $postingParent->id,
            'code' => '9991',
            'name_en' => 'Invalid Child',
            'name_ar' => 'حساب فرعي غير صالح',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_posting' => 1,
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_accountant_can_use_journals_but_cannot_manage_system_chart(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);

        $this->actingAs($accountant)->get(route('accounting.dashboard'))->assertOk();
        $this->actingAs($accountant)->get(route('accounting.journals.index'))->assertOk();
        $this->actingAs($accountant)->get(route('accounting.accounts.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('accounting.mappings.index'))->assertForbidden();
    }

    public function test_data_entry_and_viewer_cannot_access_accounting_routes(): void
    {
        foreach (['data_entry', 'viewer'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('accounting.dashboard'))->assertForbidden();
            $this->actingAs($user)->get(route('accounting.journals.index'))->assertForbidden();
        }
    }
}

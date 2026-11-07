<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_pages_have_distinct_browser_titles(): void
    {
        $user = User::factory()->create(['role' => 'data_entry', 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('operations.index', 'recycle-in'))
            ->assertOk()
            ->assertSee('<title>APICO | Recycle In</title>', false);

        $this->get(route('operations.create', 'payments'))
            ->assertOk()
            ->assertSee('<title>APICO | Payments</title>', false);
    }

    public function test_page_titles_follow_the_selected_language(): void
    {
        $user = User::factory()->create(['role' => 'data_entry', 'is_active' => true]);

        $this->actingAs($user)
            ->withSession(['locale' => 'ar'])
            ->get(route('operations.index', 'recycle-out'))
            ->assertOk()
            ->assertSee('<title>APICO | إخراج تدوير</title>', false);
    }

    public function test_non_operation_pages_have_specific_browser_titles(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('accounting.reports.trial-balance'))
            ->assertOk()
            ->assertSee('<title>APICO | Trial Balance</title>', false);
    }

    public function test_statement_and_ledger_print_titles_include_name_and_month(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Client One', 'status' => 'active']);
        $supplier = Supplier::create(['name' => 'Supplier One', 'status' => 'active']);

        $this->actingAs($admin)
            ->get(route('customers.show', [
                'customer' => $customer,
                'from' => '2026-10-01',
                'to' => '2026-10-31',
            ]))
            ->assertOk()
            ->assertSee('<title>APICO | Customer Statement - Client One - October 2026</title>', false)
            ->assertSee('Running Ledger - Client One - October 2026');

        $this->get(route('suppliers.show', [
            'supplier' => $supplier,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]))
            ->assertOk()
            ->assertSee('<title>APICO | Supplier Statement - Supplier One - October 2026</title>', false)
            ->assertSee('Running Ledger - Supplier One - October 2026');
    }
}

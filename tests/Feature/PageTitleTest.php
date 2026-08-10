<?php

namespace Tests\Feature;

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
}

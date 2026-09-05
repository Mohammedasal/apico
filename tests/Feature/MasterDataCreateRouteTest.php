<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataCreateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_customer_and_supplier_create_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/customers/create')
            ->assertOk()
            ->assertViewIs('customers.form');

        $this->actingAs($admin)
            ->get('/suppliers/create')
            ->assertOk()
            ->assertViewIs('suppliers.form');
    }
}

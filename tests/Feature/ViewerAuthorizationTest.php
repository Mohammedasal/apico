<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_access_profit_and_loss_or_cheque_pages(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);

        $this->actingAs($viewer)->get(route('production.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('reports.monthly'))->assertForbidden();
        $this->actingAs($viewer)->get(route('reports.stock-profit'))->assertForbidden();
        $this->actingAs($viewer)->get(route('cheques-in.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('cheques-out.index'))->assertForbidden();
    }

    public function test_viewer_dashboard_and_navigation_hide_profit_and_cheques(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);

        $response = $this->actingAs($viewer)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Production / P&amp;L', false);
        $response->assertDontSee('Actual Factory P&amp;L JOD', false);
        $response->assertDontSee('Cheques In');
        $response->assertDontSee('Cheques Out');
    }
}

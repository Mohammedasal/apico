<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class ApplicationPageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_parameterless_authenticated_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true))
            ->filter(fn (Route $route) => $route->getName() !== null)
            ->filter(fn (Route $route) => in_array('auth', $route->gatherMiddleware(), true))
            ->filter(fn (Route $route) => $route->parameterNames() === [])
            ->sortBy(fn (Route $route) => $route->getName());

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route->getName()));

            $this->assertTrue(
                $response->isSuccessful() || $response->isRedirection(),
                sprintf(
                    'GET route [%s] returned HTTP %d.',
                    $route->getName(),
                    $response->getStatusCode()
                )
            );
        }
    }

    public function test_every_operation_list_and_create_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        foreach (['recycle-in', 'recycle-out', 'payments', 'stock-purchases', 'stock-sales'] as $module) {
            $this->actingAs($admin)
                ->get(route('operations.index', ['module' => $module]))
                ->assertOk();

            $this->actingAs($admin)
                ->get(route('operations.create', ['module' => $module]))
                ->assertOk();
        }
    }
}

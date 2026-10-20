<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RecycleIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormSubmissionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_reusing_browser_submission_token_does_not_create_duplicate_transaction(): void
    {
        $user = User::factory()->create(['role' => 'data_entry', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $page = $this->actingAs($user)->get(route('operations.create', 'recycle-in'))->assertOk();
        preg_match('/name="_submission_token" value="([^"]+)"/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $payload = [
            '_submission_token' => $matches[1],
            'date' => '2026-10-20',
            'customer_id' => $customer->id,
            'weight_kg' => 100,
        ];

        $this->post(route('operations.store', 'recycle-in'), $payload)->assertRedirect();
        $this->post(route('operations.store', 'recycle-in'), $payload)->assertSessionHasErrors('form');

        $this->assertDatabaseCount('recycle_ins', 1);
    }

    public function test_stale_browser_edit_cannot_overwrite_newer_transaction_value(): void
    {
        $user = User::factory()->create(['role' => 'data_entry', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Customer', 'status' => 'active']);
        $record = RecycleIn::create([
            'date' => '2026-10-20',
            'customer_id' => $customer->id,
            'weight_kg' => 100,
            'rate_per_kg' => 0,
            'total_amount' => 0,
        ]);
        $page = $this->actingAs($user)->get(route('operations.edit', ['module' => 'recycle-in', 'id' => $record->id]))->assertOk();
        preg_match('/name="_record_version" value="([^"]+)"/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        DB::table('recycle_ins')->where('id', $record->id)->update([
            'weight_kg' => 110,
            'updated_at' => now()->addSecond(),
        ]);

        $this->put(route('operations.update', ['module' => 'recycle-in', 'id' => $record->id]), [
            '_record_version' => $matches[1],
            'date' => '2026-10-20',
            'customer_id' => $customer->id,
            'weight_kg' => 125,
        ])->assertSessionHasErrors('form');

        $this->assertSame(110.0, (float) $record->fresh()->weight_kg);
    }
}

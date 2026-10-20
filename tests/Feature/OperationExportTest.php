<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RecycleOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class OperationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_export_honors_filters_sort_and_selected_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $includedCustomer = Customer::create(['name' => 'Included Customer', 'status' => 'active']);
        $excludedCustomer = Customer::create(['name' => 'Excluded Customer', 'status' => 'active']);

        RecycleOut::create([
            'date' => '2026-07-10',
            'customer_id' => $includedCustomer->id,
            'weight_kg' => 125,
            'recycled_out_kg' => 100,
            'waste_kg' => 20,
            'non_recycled_kg' => 5,
            'rate_per_kg' => 0.25,
            'total_amount' => 25,
            'notes' => 'Included transaction',
        ]);
        RecycleOut::create([
            'date' => '2026-07-11',
            'customer_id' => $excludedCustomer->id,
            'weight_kg' => 500,
            'recycled_out_kg' => 500,
            'waste_kg' => 0,
            'non_recycled_kg' => 0,
            'rate_per_kg' => 1,
            'total_amount' => 500,
            'notes' => 'Excluded transaction',
        ]);

        $response = $this->actingAs($admin)->get(route('operations.export', [
            'module' => 'recycle-out',
            'customer_id' => $includedCustomer->id,
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'sort' => 'weight',
            'direction' => 'asc',
            'columns' => ['date', 'customer', 'waste_kg', 'amount'],
        ]));

        $response->assertOk()->assertDownload('recycle-out-'.now()->format('Y-m-d').'.xlsx');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertIsString($worksheet);
        $this->assertStringContainsString('Included Customer', $worksheet);
        $this->assertStringContainsString('Waste Kg', $worksheet);
        $this->assertStringContainsString('20.000', $worksheet);
        $this->assertStringNotContainsString('Excluded Customer', $worksheet);
        $this->assertStringNotContainsString('Excluded transaction', $worksheet);
        $this->assertStringNotContainsString('Material', $worksheet);
        $this->assertStringNotContainsString('Notes', $worksheet);
    }
}

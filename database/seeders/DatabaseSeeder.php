<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Customer::firstOrCreate(
            ['name' => 'Sample Customer'],
            [
                'phone' => '0790000000',
                'location' => 'Amman',
                'opening_balance' => 0,
                'opening_weight_balance_kg' => 0,
                'status' => 'active',
            ]
        );

        foreach (['PET Plastic', 'HDPE Plastic', 'Mixed Granules'] as $name) {
            Material::firstOrCreate(['name' => $name], ['type' => null, 'default_processing_cost_per_kg' => 0, 'is_active' => true]);
        }

        foreach ([
            'currency' => ['JOD', 'Currency used in reports'],
            'default_granulation_cost_per_kg' => ['0', 'Default stock sale granulation cost'],
            'high_balance_threshold' => ['1000', 'Balance value shown as high risk'],
            'allow_stock_override' => ['0', 'Allow stock sales above available stock'],
        ] as $key => [$value, $description]) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'description' => $description]);
        }
    }
}

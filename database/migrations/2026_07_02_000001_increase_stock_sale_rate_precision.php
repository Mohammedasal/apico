<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_sales', function (Blueprint $table) {
            $table->decimal('selling_price_per_kg', 15, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_sales', function (Blueprint $table) {
            $table->decimal('selling_price_per_kg', 12, 3)->change();
        });
    }
};

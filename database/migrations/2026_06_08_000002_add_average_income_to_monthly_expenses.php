<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_expenses', function (Blueprint $table) {
            $table->decimal('average_income_per_ton', 14, 3)->default(140)->after('month');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_expenses', function (Blueprint $table) {
            $table->dropColumn('average_income_per_ton');
        });
    }
};

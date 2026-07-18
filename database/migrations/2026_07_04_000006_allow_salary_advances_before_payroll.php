<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_advances', function (Blueprint $table) {
            $table->foreignId('payroll_run_id')->nullable()->change();
            $table->index(['payment_date', 'employee_id', 'status'], 'salary_advances_period_employee_status_index');
        });
    }

    public function down(): void
    {
        DB::table('salary_advances')->whereNull('payroll_run_id')->delete();

        Schema::table('salary_advances', function (Blueprint $table) {
            $table->dropIndex('salary_advances_period_employee_status_index');
            $table->foreignId('payroll_run_id')->nullable(false)->change();
        });
    }
};

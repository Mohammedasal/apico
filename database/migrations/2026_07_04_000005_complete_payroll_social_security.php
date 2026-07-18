<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->decimal('total_employee_social_security', 15, 3)->default(0)->after('total_deductions');
            $table->date('social_security_payment_date')->nullable()->after('payment_reference');
            $table->string('social_security_payment_type')->nullable()->after('social_security_payment_date');
            $table->foreignId('social_security_cash_account_id')->nullable()->after('social_security_payment_type')->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('social_security_bank_account_id')->nullable()->after('social_security_cash_account_id')->constrained('bank_accounts')->nullOnDelete();
            $table->string('social_security_payment_reference')->nullable()->after('social_security_bank_account_id');
            $table->timestamp('social_security_paid_at')->nullable()->after('social_security_payment_reference');
            $table->foreignId('social_security_paid_by')->nullable()->after('social_security_paid_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->decimal('employee_social_security', 15, 3)->default(0)->after('allowances');
        });

        DB::table('payroll_lines')->update([
            'employee_social_security' => DB::raw('deductions'),
            'deductions' => 0,
        ]);
        DB::table('payroll_runs')->update([
            'total_employee_social_security' => DB::raw('total_deductions'),
            'total_deductions' => 0,
        ]);
    }

    public function down(): void
    {
        DB::table('payroll_lines')->update([
            'deductions' => DB::raw('deductions + employee_social_security'),
        ]);
        DB::table('payroll_runs')->update([
            'total_deductions' => DB::raw('total_deductions + total_employee_social_security'),
        ]);

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropColumn('employee_social_security');
        });

        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['social_security_cash_account_id']);
            $table->dropForeign(['social_security_bank_account_id']);
            $table->dropForeign(['social_security_paid_by']);
            $table->dropColumn([
                'total_employee_social_security',
                'social_security_payment_date',
                'social_security_payment_type',
                'social_security_cash_account_id',
                'social_security_bank_account_id',
                'social_security_payment_reference',
                'social_security_paid_at',
                'social_security_paid_by',
            ]);
        });
    }
};

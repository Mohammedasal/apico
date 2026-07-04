<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->decimal('base_salary', 15, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->date('payroll_date');
            $table->string('status')->default('draft');
            $table->decimal('total_gross', 15, 3)->default(0);
            $table->decimal('total_allowances', 15, 3)->default(0);
            $table->decimal('total_deductions', 15, 3)->default(0);
            $table->decimal('total_employer_social_security', 15, 3)->default(0);
            $table->decimal('total_net', 15, 3)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('payment_type')->nullable();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['period_year', 'period_month']);
            $table->index(['payroll_date', 'status']);
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('gross_salary', 15, 3);
            $table->decimal('allowances', 15, 3)->default(0);
            $table->decimal('deductions', 15, 3)->default(0);
            $table->decimal('employer_social_security', 15, 3)->default(0);
            $table->decimal('net_salary', 15, 3);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employees');
    }
};

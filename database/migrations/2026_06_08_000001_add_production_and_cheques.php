<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('shift_one_kg', 14, 3)->default(0);
            $table->decimal('shift_two_kg', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('monthly_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('electricity_bill', 14, 3)->default(0);
            $table->decimal('total_salaries', 14, 3)->default(0);
            $table->decimal('rent', 14, 3)->default(0);
            $table->decimal('misc', 14, 3)->default(0);
            $table->decimal('social_security', 14, 3)->default(0);
            $table->decimal('other_expenses', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('cheques_out', function (Blueprint $table) {
            $table->id();
            $table->string('payee');
            $table->string('bank_name')->nullable();
            $table->string('cheque_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->decimal('amount', 14, 3);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type')->default('cash')->after('amount');
            $table->string('bank_name')->nullable()->after('reference_no');
            $table->date('cheque_due_date')->nullable()->after('bank_name');
            $table->string('cheque_status')->default('pending')->after('cheque_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'bank_name', 'cheque_due_date', 'cheque_status']);
        });

        Schema::dropIfExists('cheques_out');
        Schema::dropIfExists('monthly_expenses');
        Schema::dropIfExists('production_days');
    }
};

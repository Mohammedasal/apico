<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('type');
            $table->string('normal_balance');
            $table->boolean('is_posting')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('mapping_key');
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['mapping_key', 'entity_type', 'entity_id'], 'account_mapping_unique');
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['period_year', 'period_month']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('currency', 3)->default('JOD');
            $table->foreignId('chart_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->decimal('opening_balance', 15, 3)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->foreignId('chart_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no')->unique();
            $table->date('entry_date');
            $table->string('memo_en')->nullable();
            $table->string('memo_ar')->nullable();
            $table->string('source_module')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('posting_type')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_auto')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['source_module', 'source_type', 'source_id', 'posting_type'], 'journal_source_index');
            $table->index(['entry_date', 'status']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('description_en')->nullable();
            $table->string('description_ar')->nullable();
            $table->decimal('debit', 15, 3)->default(0);
            $table->decimal('credit', 15, 3)->default(0);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('cheque_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'customer_id']);
            $table->index(['account_id', 'supplier_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('bank_name')->constrained()->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('bank_name')->constrained()->nullOnDelete();
            $table->foreignId('cash_account_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_account_id');
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('chart_of_accounts');
    }
};

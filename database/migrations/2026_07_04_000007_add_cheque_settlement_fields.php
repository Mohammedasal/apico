<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->date('cheque_settlement_date')->nullable()->after('cheque_status');
            $table->foreignId('cheque_bank_account_id')->nullable()->after('cheque_settlement_date')->constrained('bank_accounts')->nullOnDelete();
            $table->timestamp('cheque_status_updated_at')->nullable()->after('cheque_bank_account_id');
            $table->foreignId('cheque_status_updated_by')->nullable()->after('cheque_status_updated_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->date('cheque_settlement_date')->nullable()->after('cheque_status');
            $table->foreignId('cheque_bank_account_id')->nullable()->after('cheque_settlement_date')->constrained('bank_accounts')->nullOnDelete();
            $table->timestamp('cheque_status_updated_at')->nullable()->after('cheque_bank_account_id');
            $table->foreignId('cheque_status_updated_by')->nullable()->after('cheque_status_updated_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('expense_vouchers', function (Blueprint $table) {
            $table->string('cheque_status', 20)->default('pending')->after('cheque_bank');
            $table->date('cheque_settlement_date')->nullable()->after('cheque_status');
            $table->foreignId('cheque_bank_account_id')->nullable()->after('cheque_settlement_date')->constrained('bank_accounts')->nullOnDelete();
            $table->timestamp('cheque_status_updated_at')->nullable()->after('cheque_bank_account_id');
            $table->foreignId('cheque_status_updated_by')->nullable()->after('cheque_status_updated_at')->constrained('users')->nullOnDelete();
        });

        DB::table('supplier_payments')->where('cheque_status', 'collected')->update(['cheque_status' => 'cleared']);
        DB::table('supplier_payments')->where('cheque_status', 'bounced')->update(['cheque_status' => 'cancelled']);
    }

    public function down(): void
    {
        Schema::table('expense_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cheque_status_updated_by');
            $table->dropConstrainedForeignId('cheque_bank_account_id');
            $table->dropColumn(['cheque_status', 'cheque_settlement_date', 'cheque_status_updated_at']);
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cheque_status_updated_by');
            $table->dropConstrainedForeignId('cheque_bank_account_id');
            $table->dropColumn(['cheque_settlement_date', 'cheque_status_updated_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cheque_status_updated_by');
            $table->dropConstrainedForeignId('cheque_bank_account_id');
            $table->dropColumn(['cheque_settlement_date', 'cheque_status_updated_at']);
        });
    }
};

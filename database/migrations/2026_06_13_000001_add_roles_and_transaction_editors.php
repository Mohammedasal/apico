<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('viewer');
            $table->boolean('is_active')->default(true);
        });

        foreach (['recycle_ins', 'recycle_outs', 'payments', 'stock_purchases', 'stock_sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        Schema::table('cheques_out', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cheques_out', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
        });

        foreach (['recycle_ins', 'recycle_outs', 'payments', 'stock_purchases', 'stock_sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('updated_by');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};

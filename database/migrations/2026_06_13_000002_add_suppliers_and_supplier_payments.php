<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->decimal('opening_balance', 14, 3)->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('supplier_name')->constrained()->nullOnDelete();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 3);
            $table->string('payment_type')->default('cash');
            $table->string('payment_method')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->date('cheque_due_date')->nullable();
            $table->string('cheque_status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('stock_purchases')
            ->select('supplier_name')
            ->whereNotNull('supplier_name')
            ->distinct()
            ->get()
            ->each(function ($row) {
                $name = trim((string) $row->supplier_name);

                if ($name === '') {
                    return;
                }

                $supplierId = DB::table('suppliers')->insertGetId([
                    'name' => $name,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('stock_purchases')
                    ->where('supplier_name', $name)
                    ->update(['supplier_id' => $supplierId]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');

        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};

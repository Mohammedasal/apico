<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('location')->nullable();
            $table->decimal('opening_balance', 14, 3)->default(0);
            $table->decimal('opening_weight_balance_kg', 14, 3)->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->decimal('default_processing_cost_per_kg', 10, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('recycle_ins', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight_kg', 14, 3);
            $table->decimal('rate_per_kg', 12, 3)->default(0);
            $table->decimal('total_amount', 14, 3)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recycle_outs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight_kg', 14, 3);
            $table->decimal('recycled_out_kg', 14, 3)->default(0);
            $table->decimal('waste_kg', 14, 3)->default(0);
            $table->decimal('non_recycled_kg', 14, 3)->default(0);
            $table->decimal('rate_per_kg', 12, 3);
            $table->decimal('total_amount', 14, 3);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 3);
            $table->string('payment_method')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_purchases', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('supplier_name');
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight_kg', 14, 3);
            $table->decimal('cost_per_kg', 12, 3);
            $table->decimal('total_cost', 14, 3);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_sales', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight_kg', 14, 3);
            $table->decimal('selling_price_per_kg', 12, 3);
            $table->decimal('sales_value', 14, 3);
            $table->decimal('purchase_cost_per_kg', 12, 3)->default(0);
            $table->decimal('granulation_cost_per_kg', 12, 3)->default(0);
            $table->decimal('net_profit', 14, 3);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('stock_sales');
        Schema::dropIfExists('stock_purchases');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('recycle_outs');
        Schema::dropIfExists('recycle_ins');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('customers');
    }

};

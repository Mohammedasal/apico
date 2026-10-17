<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recycle_outs', function (Blueprint $table) {
            $table->string('transaction_type', 20)->default('output')->after('material_id')->index();
            $table->decimal('calculated_amount', 14, 3)->default(0)->after('total_amount');
            $table->decimal('amount_variance', 14, 3)->default(0)->after('calculated_amount');
            $table->text('override_reason')->nullable()->after('amount_variance');
        });

        DB::table('recycle_outs')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $calculated = round((float) $row->recycled_out_kg * (float) $row->rate_per_kg, 3);
                $variance = round((float) $row->total_amount - $calculated, 3);
                $isAdjustment = (float) $row->weight_kg < 0
                    || (float) $row->recycled_out_kg < 0
                    || (float) $row->waste_kg < 0
                    || (float) $row->non_recycled_kg < 0
                    || (float) $row->rate_per_kg < 0
                    || (float) $row->total_amount < 0;

                DB::table('recycle_outs')->where('id', $row->id)->update([
                    'transaction_type' => $isAdjustment ? 'correction' : 'output',
                    'calculated_amount' => $calculated,
                    'amount_variance' => $variance,
                    'override_reason' => abs($variance) >= 0.0005 ? 'Historical/imported total differs from weight x rate.' : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('recycle_outs', function (Blueprint $table) {
            $table->dropIndex(['transaction_type']);
            $table->dropColumn(['transaction_type', 'calculated_amount', 'amount_variance', 'override_reason']);
        });
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecycleOut extends Model
{
    protected $fillable = ['date', 'customer_id', 'material_id', 'transaction_type', 'weight_kg', 'recycled_out_kg', 'waste_kg', 'non_recycled_kg', 'rate_per_kg', 'total_amount', 'calculated_amount', 'amount_variance', 'override_reason', 'notes', 'created_by', 'updated_by', 'approved_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'approved_at' => 'datetime',
            'weight_kg' => 'decimal:3',
            'recycled_out_kg' => 'decimal:3',
            'waste_kg' => 'decimal:3',
            'non_recycled_kg' => 'decimal:3',
            'rate_per_kg' => 'decimal:3',
            'total_amount' => 'decimal:3',
            'calculated_amount' => 'decimal:3',
            'amount_variance' => 'decimal:3',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

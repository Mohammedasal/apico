<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSale extends Model
{
    protected $fillable = ['date', 'customer_id', 'material_id', 'weight_kg', 'selling_price_per_kg', 'sales_value', 'purchase_cost_per_kg', 'granulation_cost_per_kg', 'net_profit', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'weight_kg' => 'decimal:3', 'selling_price_per_kg' => 'decimal:3', 'sales_value' => 'decimal:3', 'purchase_cost_per_kg' => 'decimal:3', 'granulation_cost_per_kg' => 'decimal:3', 'net_profit' => 'decimal:3'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPurchase extends Model
{
    protected $fillable = ['date', 'supplier_name', 'supplier_id', 'material_id', 'weight_kg', 'cost_per_kg', 'total_cost', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'weight_kg' => 'decimal:3', 'cost_per_kg' => 'decimal:3', 'total_cost' => 'decimal:3'];
    }

    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}

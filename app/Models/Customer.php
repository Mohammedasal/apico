<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'location', 'opening_balance',
        'opening_weight_balance_kg', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:3',
            'opening_weight_balance_kg' => 'decimal:3',
        ];
    }

    public function recycleIns(): HasMany
    {
        return $this->hasMany(RecycleIn::class);
    }

    public function recycleOuts(): HasMany
    {
        return $this->hasMany(RecycleOut::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function stockSales(): HasMany
    {
        return $this->hasMany(StockSale::class);
    }
}

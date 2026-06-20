<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'location', 'opening_balance', 'status', 'notes'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:3'];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(StockPurchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}

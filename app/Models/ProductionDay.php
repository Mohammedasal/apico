<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionDay extends Model
{
    protected $fillable = ['date', 'shift_one_kg', 'shift_two_kg', 'notes'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'shift_one_kg' => 'decimal:3',
            'shift_two_kg' => 'decimal:3',
        ];
    }

    public function getTotalKgAttribute(): float
    {
        return round((float) $this->shift_one_kg + (float) $this->shift_two_kg, 3);
    }
}

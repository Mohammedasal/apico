<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = ['name', 'type', 'default_processing_cost_per_kg', 'is_active'];

    protected function casts(): array
    {
        return [
            'default_processing_cost_per_kg' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }
}

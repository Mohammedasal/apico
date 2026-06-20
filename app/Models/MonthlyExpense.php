<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyExpense extends Model
{
    protected $fillable = [
        'year',
        'month',
        'average_income_per_ton',
        'electricity_bill',
        'total_salaries',
        'rent',
        'misc',
        'social_security',
        'other_expenses',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'average_income_per_ton' => 'decimal:3',
            'electricity_bill' => 'decimal:3',
            'total_salaries' => 'decimal:3',
            'rent' => 'decimal:3',
            'misc' => 'decimal:3',
            'social_security' => 'decimal:3',
            'other_expenses' => 'decimal:3',
        ];
    }

    public function getTotalExpensesAttribute(): float
    {
        return round(
            (float) $this->electricity_bill
            + (float) $this->total_salaries
            + (float) $this->rent
            + (float) $this->misc
            + (float) $this->social_security
            + (float) $this->other_expenses,
            3
        );
    }
}

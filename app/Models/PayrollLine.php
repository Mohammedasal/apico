<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'gross_salary', 'allowances',
        'deductions', 'employer_social_security', 'net_salary', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:3',
            'allowances' => 'decimal:3',
            'deductions' => 'decimal:3',
            'employer_social_security' => 'decimal:3',
            'net_salary' => 'decimal:3',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

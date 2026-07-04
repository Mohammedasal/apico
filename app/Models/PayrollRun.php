<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'period_month', 'period_year', 'payroll_date', 'status',
        'total_gross', 'total_allowances', 'total_deductions',
        'total_employer_social_security', 'total_net', 'payment_date',
        'payment_type', 'cash_account_id', 'bank_account_id',
        'payment_reference', 'notes', 'posted_at', 'posted_by',
        'paid_at', 'paid_by', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payroll_date' => 'date',
            'payment_date' => 'date',
            'total_gross' => 'decimal:3',
            'total_allowances' => 'decimal:3',
            'total_deductions' => 'decimal:3',
            'total_employer_social_security' => 'decimal:3',
            'total_net' => 'decimal:3',
            'posted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}

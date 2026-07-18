<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseVoucher extends Model
{
    protected $fillable = [
        'voucher_no', 'expense_date', 'expense_category_id', 'amount', 'paid_amount',
        'payment_status', 'payment_type', 'cash_account_id', 'bank_account_id',
        'payable_account_id', 'cheque_due_date', 'cheque_bank', 'cheque_status',
        'cheque_settlement_date', 'cheque_bank_account_id',
        'cheque_status_updated_at', 'cheque_status_updated_by', 'reference',
        'notes', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:3',
            'paid_amount' => 'decimal:3',
            'cheque_due_date' => 'date',
            'cheque_settlement_date' => 'date',
            'cheque_status_updated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function chequeBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'cheque_bank_account_id');
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
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

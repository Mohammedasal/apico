<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['date', 'customer_id', 'amount', 'payment_type', 'payment_method', 'reference_no', 'bank_name', 'bank_account_id', 'cash_account_id', 'cheque_due_date', 'cheque_status', 'cheque_settlement_date', 'cheque_bank_account_id', 'cheque_status_updated_at', 'cheque_status_updated_by', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'cheque_due_date' => 'date', 'cheque_settlement_date' => 'date', 'cheque_status_updated_at' => 'datetime', 'amount' => 'decimal:3'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function chequeBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'cheque_bank_account_id');
    }
}

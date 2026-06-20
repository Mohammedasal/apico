<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    protected $fillable = ['date', 'supplier_id', 'amount', 'payment_type', 'payment_method', 'reference_no', 'bank_name', 'cheque_due_date', 'cheque_status', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'cheque_due_date' => 'date', 'amount' => 'decimal:3'];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeOut extends Model
{
    protected $table = 'cheques_out';

    protected $fillable = ['payee', 'bank_name', 'cheque_number', 'issue_date', 'due_date', 'amount', 'status', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:3',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function editor(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}

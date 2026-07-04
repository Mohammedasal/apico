<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_no', 'entry_date', 'memo_en', 'memo_ar', 'source_module',
        'source_type', 'source_id', 'posting_type', 'status', 'is_auto',
        'posted_at', 'posted_by', 'reversed_at', 'reversed_by',
        'reversal_of_journal_entry_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'is_auto' => 'boolean',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_journal_entry_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_journal_entry_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalDebitAttribute(): float
    {
        return round((float) $this->lines->sum('debit'), 3);
    }

    public function getTotalCreditAttribute(): float
    {
        return round((float) $this->lines->sum('credit'), 3);
    }
}

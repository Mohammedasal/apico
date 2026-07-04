<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'period_year', 'period_month', 'start_date', 'end_date',
        'status', 'locked_at', 'locked_by',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'locked_at' => 'datetime'];
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public static function isLocked(string $date): bool
    {
        $date = Carbon::parse($date);

        return static::where('period_year', $date->year)
            ->where('period_month', $date->month)
            ->where('status', 'locked')
            ->exists();
    }
}

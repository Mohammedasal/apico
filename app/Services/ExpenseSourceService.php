<?php

namespace App\Services;

use App\Models\JournalEntryLine;
use App\Models\MonthlyExpense;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExpenseSourceService
{
    public const LEGACY = 'legacy_monthly_expenses';

    public const ACCOUNTING = 'accounting_journals';

    public const CUTOVER = 'cutover';

    public function source(): string
    {
        $source = (string) Setting::where('key', 'monthly_expense_source')->value('value');

        return in_array($source, [self::LEGACY, self::ACCOUNTING, self::CUTOVER], true)
            ? $source
            : self::LEGACY;
    }

    public function cutoverDate(): ?Carbon
    {
        $value = Setting::where('key', 'expense_cutover_date')->value('value');

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-01$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
    }

    public function total(?string $from, ?string $to): float
    {
        [$start, $end] = $this->range($from, $to);

        if (! $start || ! $end || $end->lt($start)) {
            return 0;
        }

        return round(match ($this->source()) {
            self::ACCOUNTING => $this->accountingTotal($start, $end),
            self::CUTOVER => $this->cutoverTotal($start, $end),
            default => $this->legacyTotal($start, $end),
        }, 3);
    }

    public function basis(?string $from, ?string $to): string
    {
        $rangeBasis = 'activity_period';

        if ($from && $to) {
            $start = Carbon::parse($from);
            $end = Carbon::parse($to);
            $rangeBasis = $start->isStartOfMonth() && $end->isSameDay($end->copy()->endOfMonth())
                ? 'completed_months'
                : 'partial_period';
        }

        return $this->source().':'.$rangeBasis;
    }

    public function reconciliation(int $year): Collection
    {
        return collect(range(1, 12))->map(function (int $month) use ($year) {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth()->startOfDay();
            $legacy = round($this->legacyTotal($start, $end), 3);
            $accounting = round($this->accountingTotal($start, $end), 3);

            return [
                'month' => $start->format('Y-m'),
                'legacy' => $legacy,
                'accounting' => $accounting,
                'difference' => round($accounting - $legacy, 3),
                'selected' => round($this->selectedTotal($start, $end), 3),
            ];
        });
    }

    public function legacyTotal(Carbon $start, Carbon $end): float
    {
        return (float) MonthlyExpense::where(function ($query) use ($start) {
            $query->where('year', '>', $start->year)
                ->orWhere(fn ($query) => $query->where('year', $start->year)->where('month', '>=', $start->month));
        })
            ->where(function ($query) use ($end) {
                $query->where('year', '<', $end->year)
                    ->orWhere(fn ($query) => $query->where('year', $end->year)->where('month', '<=', $end->month));
            })
            ->get()
            ->sum(function (MonthlyExpense $expense) use ($start, $end) {
                $monthStart = Carbon::create($expense->year, $expense->month, 1)->startOfDay();
                $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
                $overlapStart = $start->copy()->max($monthStart);
                $overlapEnd = $end->copy()->min($monthEnd);

                if ($overlapEnd->lt($overlapStart)) {
                    return 0;
                }

                return $expense->total_expenses
                    * (($overlapStart->diffInDays($overlapEnd) + 1) / $monthStart->daysInMonth);
            });
    }

    public function accountingTotal(Carbon $start, Carbon $end): float
    {
        return (float) JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('chart_of_accounts.type', 'expense')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->whereDate('journal_entries.entry_date', '>=', $start->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $end->toDateString())
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) AS total')
            ->value('total');
    }

    private function cutoverTotal(Carbon $start, Carbon $end): float
    {
        $cutover = $this->cutoverDate();

        if (! $cutover) {
            return $this->legacyTotal($start, $end);
        }

        $legacyEnd = $end->copy()->min($cutover->copy()->subDay());
        $accountingStart = $start->copy()->max($cutover);

        return ($legacyEnd->gte($start) ? $this->legacyTotal($start, $legacyEnd) : 0)
            + ($accountingStart->lte($end) ? $this->accountingTotal($accountingStart, $end) : 0);
    }

    private function selectedTotal(Carbon $start, Carbon $end): float
    {
        return match ($this->source()) {
            self::ACCOUNTING => $this->accountingTotal($start, $end),
            self::CUTOVER => $this->cutoverTotal($start, $end),
            default => $this->legacyTotal($start, $end),
        };
    }

    private function range(?string $from, ?string $to): array
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : $this->firstExpenseDate();
        $end = $to ? Carbon::parse($to)->startOfDay() : Carbon::today()->startOfDay();

        return [$start, $end];
    }

    private function firstExpenseDate(): ?Carbon
    {
        $legacy = MonthlyExpense::orderBy('year')->orderBy('month')->first();
        $accounting = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('chart_of_accounts.type', 'expense')
            ->min('journal_entries.entry_date');

        return collect([
            $legacy ? Carbon::create($legacy->year, $legacy->month, 1) : null,
            $accounting ? Carbon::parse($accounting) : null,
        ])->filter()->sort()->first();
    }
}

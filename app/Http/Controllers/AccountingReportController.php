<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AccountingReportController extends Controller
{
    public function generalLedger(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $from = $validated['from'] ?? now()->startOfYear()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $accountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
        $search = trim((string) ($validated['q'] ?? ''));
        $opening = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->whereDate('journal_entries.entry_date', '<', $from)
            ->when($accountId, fn ($query) => $query->where('journal_entry_lines.account_id', $accountId))
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit - journal_entry_lines.credit), 0) AS balance')
            ->value('balance');
        $running = round((float) $opening, 3);
        $lines = JournalEntryLine::with(['journalEntry', 'account', 'customer', 'supplier'])
            ->whereHas('journalEntry', function ($query) use ($from, $to, $search) {
                $query->whereIn('status', ['posted', 'reversed'])
                    ->whereDate('entry_date', '>=', $from)
                    ->whereDate('entry_date', '<=', $to)
                    ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                        $query->where('entry_no', 'like', "%{$search}%")
                            ->orWhere('memo_en', 'like', "%{$search}%")
                            ->orWhere('memo_ar', 'like', "%{$search}%");
                    }));
            })
            ->when($accountId, fn ($query) => $query->where('account_id', $accountId))
            ->orderBy(
                JournalEntry::select('entry_date')->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id')
            )
            ->orderBy('journal_entry_id')
            ->orderBy('id')
            ->get()
            ->map(function (JournalEntryLine $line) use (&$running) {
                $running = round($running + (float) $line->debit - (float) $line->credit, 3);

                return ['line' => $line, 'running_balance' => $running];
            });

        return view('accounting.reports.general-ledger', [
            'rows' => $lines,
            'accounts' => ChartOfAccount::where('is_posting', true)->orderBy('code')->get(),
            'filters' => ['from' => $from, 'to' => $to, 'account_id' => $accountId, 'q' => $search],
            'openingBalance' => round((float) $opening, 3),
            'periodDebit' => round((float) $lines->sum(fn ($row) => $row['line']->debit), 3),
            'periodCredit' => round((float) $lines->sum(fn ($row) => $row['line']->credit), 3),
            'closingBalance' => $running,
        ]);
    }

    public function profitLoss(Request $request)
    {
        [$from, $to] = $this->dateRange($request);
        $rows = $this->accountPeriodBalances($from, $to, ['revenue', 'cogs', 'expense']);
        $revenue = round((float) $rows->where('type', 'revenue')->sum('amount'), 3);
        $cogs = round((float) $rows->where('type', 'cogs')->sum('amount'), 3);
        $expenses = round((float) $rows->where('type', 'expense')->sum('amount'), 3);

        return view('accounting.reports.profit-loss', compact('from', 'to', 'rows', 'revenue', 'cogs', 'expenses') + [
            'grossProfit' => round($revenue - $cogs, 3),
            'netProfit' => round($revenue - $cogs - $expenses, 3),
        ]);
    }

    public function balanceSheet(Request $request)
    {
        $request->validate(['to' => ['nullable', 'date']]);
        $to = $request->input('to', now()->toDateString());
        $rows = $this->accountPeriodBalances('1900-01-01', $to, ['asset', 'liability', 'equity']);
        $incomeRows = $this->accountPeriodBalances('1900-01-01', $to, ['revenue', 'cogs', 'expense']);
        $assets = round((float) $rows->where('type', 'asset')->sum('amount'), 3);
        $liabilities = round((float) $rows->where('type', 'liability')->sum('amount'), 3);
        $equity = round((float) $rows->where('type', 'equity')->sum('amount'), 3);
        $currentEarnings = round(
            (float) $incomeRows->where('type', 'revenue')->sum('amount')
            - (float) $incomeRows->where('type', 'cogs')->sum('amount')
            - (float) $incomeRows->where('type', 'expense')->sum('amount'),
            3
        );

        return view('accounting.reports.balance-sheet', compact('to', 'rows', 'assets', 'liabilities', 'equity', 'currentEarnings') + [
            'liabilitiesAndEquity' => round($liabilities + $equity + $currentEarnings, 3),
            'difference' => round($assets - $liabilities - $equity - $currentEarnings, 3),
        ]);
    }

    public function trialBalance(Request $request)
    {
        return view('accounting.reports.trial-balance', $this->trialBalanceData($request) + [
            'exportColumns' => $this->exportColumns(),
        ]);
    }

    public function trialBalanceExport(Request $request, SimpleXlsxExporter $exporter)
    {
        $report = $this->trialBalanceData($request);
        $allColumns = $this->exportColumns();
        $columns = collect($allColumns)
            ->only($request->input('columns', array_keys($allColumns)))
            ->all() ?: $allColumns;

        $rows = $report['rows']->map(function (array $row) use ($columns) {
            return collect(array_keys($columns))->map(function (string $column) use ($row) {
                $value = $row[$column] ?? '';

                return is_numeric($value) && $column !== 'code'
                    ? number_format((float) $value, 3, '.', '')
                    : $value;
            })->all();
        })->all();

        $rows[] = collect(array_keys($columns))->map(function (string $column) use ($report) {
            if ($column === 'code') {
                return __('Totals');
            }

            return array_key_exists($column, $report['totals'])
                ? number_format($report['totals'][$column], 3, '.', '')
                : '';
        })->all();

        return $exporter->download(
            'trial-balance-'.$report['filters']['from'].'-to-'.$report['filters']['to'].'.xlsx',
            array_values($columns),
            $rows,
            [
                [__('Trial Balance Report'), $report['filters']['from'].' '.__('to').' '.$report['filters']['to']],
                [__('Generated Date'), now()->toDateString()],
                [__('Balance Difference'), number_format($report['difference'], 3, '.', '')],
            ]
        );
    }

    private function trialBalanceData(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'account_type' => ['nullable', Rule::in(ChartOfAccount::TYPES)],
            'q' => ['nullable', 'string', 'max:255'],
            'include_zero' => ['nullable', 'boolean'],
        ]);

        $from = $validated['from'] ?? now()->startOfYear()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $includeZero = $request->boolean('include_zero');

        $movements = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->whereDate('journal_entries.entry_date', '<=', $to)
            ->selectRaw(
                'journal_entry_lines.account_id,
                 SUM(CASE WHEN journal_entries.entry_date < ? THEN journal_entry_lines.debit ELSE 0 END) AS opening_debit,
                 SUM(CASE WHEN journal_entries.entry_date < ? THEN journal_entry_lines.credit ELSE 0 END) AS opening_credit,
                 SUM(CASE WHEN journal_entries.entry_date >= ? THEN journal_entry_lines.debit ELSE 0 END) AS period_debit,
                 SUM(CASE WHEN journal_entries.entry_date >= ? THEN journal_entry_lines.credit ELSE 0 END) AS period_credit',
                [$from, $from, $from, $from]
            )
            ->groupBy('journal_entry_lines.account_id')
            ->get()
            ->keyBy('account_id');

        $accounts = ChartOfAccount::query()
            ->where('is_posting', true)
            ->when($validated['account_type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['q'] ?? null, function ($query, $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('code', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('name_ar', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $rows = $accounts->map(function (ChartOfAccount $account) use ($movements) {
            $movement = $movements->get($account->id);
            $openingNet = round((float) ($movement->opening_debit ?? 0) - (float) ($movement->opening_credit ?? 0), 3);
            $periodDebit = round((float) ($movement->period_debit ?? 0), 3);
            $periodCredit = round((float) ($movement->period_credit ?? 0), 3);
            $closingNet = round($openingNet + $periodDebit - $periodCredit, 3);

            return [
                'code' => $account->code,
                'account' => $account->localized_name,
                'type' => __(ucfirst($account->type)),
                'opening_debit' => max($openingNet, 0),
                'opening_credit' => abs(min($openingNet, 0)),
                'period_debit' => $periodDebit,
                'period_credit' => $periodCredit,
                'closing_debit' => max($closingNet, 0),
                'closing_credit' => abs(min($closingNet, 0)),
            ];
        })->when(! $includeZero, function (Collection $rows) {
            return $rows->filter(fn (array $row) => collect([
                'opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit',
            ])->contains(fn (string $column) => abs($row[$column]) >= 0.0005));
        })->values();

        $numericColumns = ['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'];
        $totals = collect($numericColumns)->mapWithKeys(fn (string $column) => [
            $column => round((float) $rows->sum($column), 3),
        ])->all();

        return [
            'rows' => $rows,
            'totals' => $totals,
            'difference' => round($totals['closing_debit'] - $totals['closing_credit'], 3),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'account_type' => $validated['account_type'] ?? '',
                'q' => $validated['q'] ?? '',
                'include_zero' => $includeZero,
            ],
        ];
    }

    private function exportColumns(): array
    {
        return [
            'code' => __('Code'),
            'account' => __('Account'),
            'type' => __('Account Type'),
            'opening_debit' => __('Opening Debit'),
            'opening_credit' => __('Opening Credit'),
            'period_debit' => __('Period Debit'),
            'period_credit' => __('Period Credit'),
            'closing_debit' => __('Closing Debit'),
            'closing_credit' => __('Closing Credit'),
        ];
    }

    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            $validated['from'] ?? now()->startOfYear()->toDateString(),
            $validated['to'] ?? now()->toDateString(),
        ];
    }

    private function accountPeriodBalances(string $from, string $to, array $types): Collection
    {
        $movements = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->whereDate('journal_entries.entry_date', '>=', $from)
            ->whereDate('journal_entries.entry_date', '<=', $to)
            ->selectRaw('journal_entry_lines.account_id, SUM(journal_entry_lines.debit) AS debit, SUM(journal_entry_lines.credit) AS credit')
            ->groupBy('journal_entry_lines.account_id')
            ->get()
            ->keyBy('account_id');

        return ChartOfAccount::where('is_posting', true)
            ->whereIn('type', $types)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(function (ChartOfAccount $account) use ($movements) {
                $movement = $movements->get($account->id);
                $debit = (float) ($movement->debit ?? 0);
                $credit = (float) ($movement->credit ?? 0);
                $amount = in_array($account->type, ['liability', 'equity', 'revenue'], true)
                    ? $credit - $debit
                    : $debit - $credit;

                return [
                    'code' => $account->code,
                    'account' => $account->localized_name,
                    'type' => $account->type,
                    'amount' => round($amount, 3),
                ];
            })
            ->filter(fn (array $row) => abs($row['amount']) >= 0.0005)
            ->values();
    }
}

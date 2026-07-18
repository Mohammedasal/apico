<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AccountingReportController extends Controller
{
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
}

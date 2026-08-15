<?php

namespace App\Http\Controllers;

use App\Models\RecycleOut;
use App\Models\StockSale;
use App\Services\ApicoCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OperationalDashboardController extends Controller
{
    public function stockSales(Request $request, ApicoCalculator $calculator)
    {
        $period = $this->period($request);
        $sales = StockSale::with(['customer', 'material'])
            ->tap(fn (Builder $query) => $this->applyPeriod($query, $period))
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        $summary = $calculator->stockProfitSummary($period['from'], $period['to']);
        $summary['average_rate'] = $summary['sold_kg'] > 0
            ? round($summary['revenue'] / $summary['sold_kg'], 6)
            : 0;
        $summary['margin_percentage'] = $summary['revenue'] != 0
            ? round(($summary['profit'] / $summary['revenue']) * 100, 2)
            : 0;
        $summary['transactions'] = $sales->count();

        return view('reports.stock-sales-dashboard', [
            'period' => $period,
            'summary' => $summary,
            'trend' => $this->stockTrend($sales, $period, $calculator),
            'topCustomers' => $this->stockBreakdown($sales, 'customer'),
            'topMaterials' => $this->stockBreakdown($sales, 'material'),
        ]);
    }

    public function recycleOut(Request $request)
    {
        $period = $this->period($request);
        $rows = RecycleOut::with(['customer', 'material'])
            ->tap(fn (Builder $query) => $this->applyPeriod($query, $period))
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        $summary = $this->recycleSummary($rows);

        return view('reports.recycle-out-dashboard', [
            'period' => $period,
            'summary' => $summary,
            'trend' => $this->recycleTrend($rows, $period),
            'topCustomers' => $this->recycleBreakdown($rows, 'customer'),
            'topMaterials' => $this->recycleBreakdown($rows, 'material'),
        ]);
    }

    private function period(Request $request): array
    {
        $mode = in_array($request->input('period'), ['total', 'monthly', 'weekly', 'custom'], true)
            ? $request->input('period')
            : 'monthly';
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('month'))
            ? $request->input('month')
            : today()->format('Y-m');
        $week = preg_match('/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/', (string) $request->input('week'))
            ? $request->input('week')
            : today()->format('o-\WW');
        $customFrom = $this->dateInput($request->input('from')) ?? today()->startOfMonth()->toDateString();
        $customTo = $this->dateInput($request->input('to')) ?? today()->toDateString();

        if ($customFrom > $customTo) {
            [$customFrom, $customTo] = [$customTo, $customFrom];
        }

        if ($mode === 'total') {
            return compact('mode', 'month', 'week', 'customFrom', 'customTo') + [
                'from' => null,
                'to' => null,
                'label' => __('All recorded dates'),
            ];
        }

        if ($mode === 'weekly') {
            [$year, $weekNumber] = array_map('intval', explode('-W', $week));
            $from = Carbon::now()->setISODate($year, $weekNumber)->startOfDay()->subDays(2);
            $to = $from->copy()->addDays(5);

            return compact('mode', 'month', 'week', 'customFrom', 'customTo') + [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->locale(app()->getLocale())->translatedFormat('d M Y').' - '.$to->locale(app()->getLocale())->translatedFormat('d M Y'),
            ];
        }

        if ($mode === 'custom') {
            $from = Carbon::createFromFormat('Y-m-d', $customFrom);
            $to = Carbon::createFromFormat('Y-m-d', $customTo);

            return compact('mode', 'month', 'week', 'customFrom', 'customTo') + [
                'from' => $customFrom,
                'to' => $customTo,
                'label' => $from->locale(app()->getLocale())->translatedFormat('d M Y').' - '.$to->locale(app()->getLocale())->translatedFormat('d M Y'),
            ];
        }

        $from = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return compact('mode', 'month', 'week', 'customFrom', 'customTo') + [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'label' => $from->locale(app()->getLocale())->translatedFormat('F Y'),
        ];
    }

    private function dateInput(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }

    private function applyPeriod(Builder $query, array $period): void
    {
        $query
            ->when($period['from'], fn (Builder $query, string $from) => $query->whereDate('date', '>=', $from))
            ->when($period['to'], fn (Builder $query, string $to) => $query->whereDate('date', '<=', $to));
    }

    private function stockTrend(Collection $sales, array $period, ApicoCalculator $calculator): Collection
    {
        return $this->groupByPeriod($sales, $period)->map(function (Collection $rows, string $key) use ($period, $calculator) {
            [$from, $to] = $this->bucketDates($key, $period['mode']);
            $summary = $calculator->stockProfitSummary($from, $to);

            return [
                'label' => $this->bucketLabel($key, $period['mode']),
                'weight_kg' => round((float) $rows->sum('weight_kg'), 3),
                'revenue' => round((float) $rows->sum('sales_value'), 3),
                'profit' => $summary['profit'],
                'transactions' => $rows->count(),
            ];
        })->values();
    }

    private function stockBreakdown(Collection $sales, string $relation): Collection
    {
        return $sales->groupBy(fn (StockSale $sale) => $sale->{$relation.'_id'} ?: 'none')
            ->map(function (Collection $rows) use ($relation) {
                $first = $rows->first();

                return [
                    'name' => $first->{$relation}?->name ?: __($relation === 'customer' ? 'No customer' : 'No material'),
                    'weight_kg' => round((float) $rows->sum('weight_kg'), 3),
                    'amount' => round((float) $rows->sum('sales_value'), 3),
                    'transactions' => $rows->count(),
                ];
            })
            ->sortByDesc('amount')
            ->take(5)
            ->values();
    }

    private function recycleSummary(Collection $rows): array
    {
        $total = (float) $rows->sum('weight_kg');
        $recycled = (float) $rows->sum('recycled_out_kg');
        $waste = (float) $rows->sum('waste_kg');
        $nonRecycled = (float) $rows->sum('non_recycled_kg');
        $revenue = (float) $rows->sum('total_amount');

        return [
            'total_kg' => round($total, 3),
            'recycled_kg' => round($recycled, 3),
            'waste_kg' => round($waste, 3),
            'non_recycled_kg' => round($nonRecycled, 3),
            'revenue' => round($revenue, 3),
            'average_rate' => $recycled > 0 ? round($revenue / $recycled, 6) : 0,
            'waste_percentage' => $total > 0 ? round(($waste / $total) * 100, 2) : 0,
            'yield_percentage' => $total > 0 ? round(($recycled / $total) * 100, 2) : 0,
            'transactions' => $rows->count(),
        ];
    }

    private function recycleTrend(Collection $rows, array $period): Collection
    {
        return $this->groupByPeriod($rows, $period)->map(function (Collection $rows, string $key) use ($period) {
            $summary = $this->recycleSummary($rows);

            return $summary + ['label' => $this->bucketLabel($key, $period['mode'])];
        })->values();
    }

    private function recycleBreakdown(Collection $rows, string $relation): Collection
    {
        return $rows->groupBy(fn (RecycleOut $row) => $row->{$relation.'_id'} ?: 'none')
            ->map(function (Collection $rows) use ($relation) {
                $first = $rows->first();
                $summary = $this->recycleSummary($rows);

                return [
                    'name' => $first->{$relation}?->name ?: __($relation === 'customer' ? 'No customer' : 'No material'),
                    'total_kg' => $summary['total_kg'],
                    'recycled_kg' => $summary['recycled_kg'],
                    'waste_kg' => $summary['waste_kg'],
                    'amount' => $summary['revenue'],
                ];
            })
            ->sortByDesc('total_kg')
            ->take(5)
            ->values();
    }

    private function groupByPeriod(Collection $rows, array $period): Collection
    {
        return $rows->groupBy(fn ($row) => $period['mode'] === 'total'
            ? $row->date->format('Y-m')
            : $row->date->format('Y-m-d'));
    }

    private function bucketDates(string $key, string $mode): array
    {
        $from = $mode === 'total'
            ? Carbon::createFromFormat('Y-m-d', $key.'-01')->startOfMonth()
            : Carbon::createFromFormat('Y-m-d', $key);
        $to = $mode === 'total' ? $from->copy()->endOfMonth() : $from->copy();

        return [$from->toDateString(), $to->toDateString()];
    }

    private function bucketLabel(string $key, string $mode): string
    {
        return $mode === 'total'
            ? Carbon::createFromFormat('Y-m-d', $key.'-01')->locale(app()->getLocale())->translatedFormat('M Y')
            : Carbon::createFromFormat('Y-m-d', $key)->locale(app()->getLocale())->translatedFormat('d M');
    }
}

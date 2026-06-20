<?php

namespace App\Http\Controllers;

use App\Models\MonthlyExpense;
use App\Models\ProductionDay;
use App\Models\RecycleOut;
use App\Models\StockSale;
use App\Services\ApicoCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index(Request $request, ApicoCalculator $calculator)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        $entries = ProductionDay::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (ProductionDay $day) => $day->date->toDateString());
        $expense = MonthlyExpense::firstOrNew(['year' => $year, 'month' => $month]);
        $recycleIncome = (float) RecycleOut::whereBetween('date', [$start, $end])->sum('total_amount');
        $stockIncome = (float) StockSale::whereBetween('date', [$start, $end])->sum('sales_value');
        $income = round($recycleIncome + $stockIncome, 3);
        $productionKg = round($entries->sum(fn (ProductionDay $day) => $day->total_kg), 3);
        $productionTons = round($productionKg / 1000, 3);
        $productionDays = $entries->filter(fn (ProductionDay $day) => $day->total_kg > 0)->count();
        $totalExpenses = $expense->exists ? $expense->total_expenses : 0;
        $averageIncomePerTon = (float) ($expense->average_income_per_ton ?? 140);
        $productionIncome = round($productionTons * $averageIncomePerTon, 3);
        $actual = $calculator->actualProfitSummary($start->toDateString(), $end->toDateString());

        return view('production.index', [
            'year' => $year,
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'entries' => $entries,
            'expense' => $expense,
            'days' => collect(range(1, $end->day))->map(fn (int $day) => $start->copy()->day($day)),
            'recycleIncome' => $recycleIncome,
            'stockIncome' => $stockIncome,
            'income' => $income,
            'averageIncomePerTon' => $averageIncomePerTon,
            'productionIncome' => $productionIncome,
            'productionKg' => $productionKg,
            'productionTons' => $productionTons,
            'productionDays' => $productionDays,
            'averageDailyProduction' => $productionDays > 0 ? round($productionKg / $productionDays, 3) : 0,
            'totalExpenses' => $totalExpenses,
            'costPerTon' => $productionTons > 0 ? round($totalExpenses / $productionTons, 3) : 0,
            'dailyCost' => $productionDays > 0 ? round($totalExpenses / $productionDays, 3) : 0,
            'profitLoss' => round($productionIncome - $totalExpenses, 3),
            'actualProfitLoss' => $actual['actual_profit_loss'],
            'actualStockMaterialCogs' => $actual['stock_material_cogs'],
            'completedMonthsSummary' => $this->periodSummary(Carbon::create($year, 1, 1), Carbon::today()->startOfMonth()->subDay(), $calculator),
        ]);
    }

    public function saveDays(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'days' => ['array'],
            'days.*.date' => ['required', 'date'],
            'days.*.shift_one_kg' => ['nullable', 'numeric', 'min:0'],
            'days.*.shift_two_kg' => ['nullable', 'numeric', 'min:0'],
            'days.*.notes' => ['nullable', 'string'],
        ]);

        foreach ($data['days'] ?? [] as $row) {
            ProductionDay::updateOrCreate(
                ['date' => $row['date']],
                [
                    'shift_one_kg' => (float) ($row['shift_one_kg'] ?? 0),
                    'shift_two_kg' => (float) ($row['shift_two_kg'] ?? 0),
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }

        return redirect()->route('production.index', ['year' => $data['year'], 'month' => $data['month']])->with('status', 'Daily production saved.');
    }

    public function saveExpenses(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'average_income_per_ton' => ['nullable', 'numeric', 'min:0'],
            'electricity_bill' => ['nullable', 'numeric', 'min:0'],
            'total_salaries' => ['nullable', 'numeric', 'min:0'],
            'rent' => ['nullable', 'numeric', 'min:0'],
            'misc' => ['nullable', 'numeric', 'min:0'],
            'social_security' => ['nullable', 'numeric', 'min:0'],
            'other_expenses' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        MonthlyExpense::updateOrCreate(
            ['year' => $data['year'], 'month' => $data['month']],
            [
                'average_income_per_ton' => (float) ($data['average_income_per_ton'] ?? 140),
                'electricity_bill' => (float) ($data['electricity_bill'] ?? 0),
                'total_salaries' => (float) ($data['total_salaries'] ?? 0),
                'rent' => (float) ($data['rent'] ?? 0),
                'misc' => (float) ($data['misc'] ?? 0),
                'social_security' => (float) ($data['social_security'] ?? 0),
                'other_expenses' => (float) ($data['other_expenses'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return redirect()->route('production.index', ['year' => $data['year'], 'month' => $data['month']])->with('status', 'Monthly expenses saved.');
    }

    private function periodSummary(Carbon $start, Carbon $end, ApicoCalculator $calculator): array
    {
        if ($end->lt($start)) {
            return [
                'from' => $start,
                'to' => $end,
                'production_kg' => 0,
                'production_tons' => 0,
                'production_days' => 0,
                'income' => 0,
                'expenses' => 0,
                'cost_per_ton' => 0,
                'daily_cost' => 0,
                'profit_loss' => 0,
            ];
        }

        $entries = ProductionDay::whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();
        $productionKg = round($entries->sum(fn (ProductionDay $day) => $day->total_kg), 3);
        $productionTons = round($productionKg / 1000, 3);
        $productionDays = $entries->filter(fn (ProductionDay $day) => $day->total_kg > 0)->count();
        $actualIncome = round(
            (float) RecycleOut::whereBetween('date', [$start, $end])->sum('total_amount')
            + (float) StockSale::whereBetween('date', [$start, $end])->sum('sales_value'),
            3
        );
        $actual = $calculator->actualProfitSummary($start->toDateString(), $end->toDateString());
        $monthlyExpenses = MonthlyExpense::where(function ($query) use ($start, $end) {
            $query->where('year', '>', $start->year)
                ->orWhere(fn ($query) => $query->where('year', $start->year)->where('month', '>=', $start->month));
        })
            ->where(function ($query) use ($end) {
                $query->where('year', '<', $end->year)
                    ->orWhere(fn ($query) => $query->where('year', $end->year)->where('month', '<=', $end->month));
            })
            ->get();
        $expenses = $monthlyExpenses->sum(fn (MonthlyExpense $expense) => $expense->total_expenses);
        $expensesByMonth = $monthlyExpenses->keyBy(fn (MonthlyExpense $expense) => $expense->year.'-'.$expense->month);
        $productionIncome = 0;
        $cursor = $start->copy()->startOfMonth();

        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->max($start);
            $monthEnd = $cursor->copy()->endOfMonth()->min($end);
            $monthKg = $entries
                ->filter(fn (ProductionDay $day) => $day->date->betweenIncluded($monthStart, $monthEnd))
                ->sum(fn (ProductionDay $day) => (float) $day->total_kg);
            $expense = $expensesByMonth[$cursor->year.'-'.$cursor->month] ?? null;
            $productionIncome += ($monthKg / 1000) * (float) ($expense?->average_income_per_ton ?? 140);
            $cursor->addMonth();
        }
        $productionIncome = round($productionIncome, 3);

        return [
            'from' => $start,
            'to' => $end,
            'production_kg' => $productionKg,
            'production_tons' => $productionTons,
            'production_days' => $productionDays,
            'income' => $productionIncome,
            'actual_income' => $actualIncome,
            'expenses' => round((float) $expenses, 3),
            'cost_per_ton' => $productionTons > 0 ? round((float) $expenses / $productionTons, 3) : 0,
            'daily_cost' => $productionDays > 0 ? round((float) $expenses / $productionDays, 3) : 0,
            'profit_loss' => round($productionIncome - (float) $expenses, 3),
            'actual_profit_loss' => $actual['actual_profit_loss'],
            'stock_material_cogs' => $actual['stock_material_cogs'],
        ];
    }
}

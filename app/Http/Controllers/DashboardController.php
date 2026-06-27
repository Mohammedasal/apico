<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Services\ApicoCalculator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ApicoCalculator $calculator)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $dateRange = fn ($query) => $query
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $to));

        $recycleOutKg = RecycleOut::query()->tap($dateRange)->sum('weight_kg');
        $productionKg = RecycleOut::query()->tap($dateRange)->sum('recycled_out_kg');
        $customers = Customer::all();
        $customerBalances = $customers->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'remaining_kg' => $calculator->customerWeightDifference($customer),
            'remaining_jod' => $calculator->customerBalance($customer),
        ]);
        $receivables = $customerBalances->sum(fn (array $customer) => max(0, $customer['remaining_jod']));
        $productionDays = RecycleOut::query()
            ->tap($dateRange)
            ->where('date', '!=', '1900-01-01')
            ->distinct()
            ->count('date');
        $wasteKg = RecycleOut::query()->tap($dateRange)->sum('waste_kg');
        $stockProfit = $calculator->stockProfitSummary($from, $to);
        $actualProfit = $calculator->actualProfitSummary($from, $to);
        $lastCompletedMonthCost = $calculator->lastCompletedMonthActualCostPerTon();
        $performanceYear = (int) $request->input('performance_year', Carbon::today()->year);

        return view('dashboard', [
            'from' => $from,
            'to' => $to,
            'performanceYear' => $performanceYear,
            'monthlyPerformance' => $this->monthlyPerformance($calculator, $performanceYear),
            'topCustomerKgBalances' => $customerBalances
                ->sortByDesc('remaining_kg')
                ->take(5)
                ->values(),
            'topCustomerJodBalances' => $customerBalances
                ->sortByDesc('remaining_jod')
                ->take(5)
                ->values(),
            'customerCount' => $customers->count(),
            'receivables' => round((float) $receivables, 3),
            'recycleInKg' => RecycleIn::query()->tap($dateRange)->sum('weight_kg'),
            'recycleOutKg' => $recycleOutKg,
            'productionKg' => $productionKg,
            'productionDays' => $productionDays,
            'dailyProductionAverage' => $productionDays > 0 ? round((float) $productionKg / $productionDays, 3) : 0,
            'wasteKg' => $wasteKg,
            'wastePercentage' => $recycleOutKg > 0 ? round(((float) $wasteKg / (float) $recycleOutKg) * 100, 2) : 0,
            'payments' => Payment::query()->tap($dateRange)->sum('amount'),
            'stockProfit' => $stockProfit['profit'],
            'stockRevenue' => $stockProfit['revenue'],
            'stockMaterialCogs' => $stockProfit['material_cogs'],
            'stockRecycleCost' => $stockProfit['recycle_cost'],
            'actualCostPerTon' => $lastCompletedMonthCost['cost_per_ton'],
            'actualCostPerTonLabel' => $lastCompletedMonthCost['label'],
            'actualFactoryProfitLoss' => $actualProfit['actual_profit_loss'],
            'actualOperatingExpenses' => $actualProfit['operating_expenses'],
            'remainingStock' => $calculator->remainingStockWeight(),
            'purchases' => StockPurchase::query()->tap($dateRange)->sum('total_cost'),
        ]);
    }

    private function monthlyPerformance(ApicoCalculator $calculator, int $year): array
    {
        return collect(range(1, 12))
            ->map(function (int $month) use ($calculator, $year) {
                $start = Carbon::create($year, $month, 1);
                $end = $start->copy()->endOfMonth();
                $summary = $calculator->actualProfitSummary($start->toDateString(), $end->toDateString());

                return [
                    'label' => $start->format('M'),
                    'income' => round((float) $summary['recycle_income'] + (float) $summary['stock_revenue'], 3),
                    'expenses' => round((float) $summary['operating_expenses'], 3),
                    'material_cogs' => round((float) $summary['stock_material_cogs'], 3),
                    'profit_loss' => round((float) $summary['actual_profit_loss'], 3),
                ];
            })
            ->all();
    }
}

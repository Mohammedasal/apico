<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Services\ApicoCalculator;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ApicoCalculator $calculator)
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $dateRange = fn ($query) => $query
            ->when($from, fn ($query) => $query->where('date', '>=', $from))
            ->when($to, fn ($query) => $query->where('date', '<=', $to));

        $recycleOutKg = RecycleOut::query()->tap($dateRange)->sum('weight_kg');
        $productionKg = RecycleOut::query()->tap($dateRange)->sum('recycled_out_kg');
        $customers = Customer::all();
        $receivables = $customers
            ->sum(fn (Customer $customer) => max(0, $calculator->customerBalance($customer)));
        $productionDays = RecycleOut::query()
            ->tap($dateRange)
            ->where('date', '!=', '1900-01-01')
            ->distinct()
            ->count('date');
        $wasteKg = RecycleOut::query()->tap($dateRange)->sum('waste_kg');
        $stockProfit = $calculator->stockProfitSummary($from, $to);
        $actualProfit = $calculator->actualProfitSummary($from, $to);
        $lastCompletedMonthCost = $calculator->lastCompletedMonthActualCostPerTon();

        return view('dashboard', [
            'from' => $from,
            'to' => $to,
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
}

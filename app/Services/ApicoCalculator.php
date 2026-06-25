<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\MonthlyExpense;
use App\Models\ProductionDay;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ApicoCalculator
{
    public function recycleTotal(float $weightKg, float $ratePerKg): float
    {
        return round($weightKg * $ratePerKg, 3);
    }

    public function stockSaleValues(float $weightKg, float $sellingPrice, float $purchaseCost, float $granulationCost): array
    {
        $salesValue = round($weightKg * $sellingPrice, 3);
        $purchaseCostValue = round($weightKg * $purchaseCost, 3);
        $granulationCostValue = round($weightKg * $granulationCost, 3);

        return [
            'sales_value' => $salesValue,
            'purchase_cost_value' => $purchaseCostValue,
            'granulation_cost_value' => $granulationCostValue,
            'net_profit' => round($salesValue - $purchaseCostValue - $granulationCostValue, 3),
        ];
    }

    public function remainingStockWeight(?int $materialId = null): float
    {
        $purchased = StockPurchase::query()
            ->when($materialId, fn ($query) => $query->where('material_id', $materialId))
            ->sum('weight_kg');

        $sold = StockSale::query()
            ->when($materialId, fn ($query) => $query->where('material_id', $materialId))
            ->sum('weight_kg');

        return round((float) $purchased - (float) $sold, 3);
    }

    public function stockProfitSummary(?string $from = null, ?string $to = null): array
    {
        $dateRange = fn ($query) => $query
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $to));

        $revenue = (float) StockSale::query()->tap($dateRange)->sum('sales_value');
        $materialCogs = $this->stockMaterialCogs($from, $to);
        $sales = StockSale::query()
            ->tap($dateRange)
            ->get(['date', 'weight_kg'])
            ->groupBy(fn (StockSale $sale) => $sale->date->format('Y-m'));

        $recycleCost = $sales->sum(function (Collection $monthSales, string $yearMonth) {
            [$year, $month] = array_map('intval', explode('-', $yearMonth));
            $monthStart = Carbon::create($year, $month, 1);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $productionKg = ProductionDay::whereDate('date', '>=', $monthStart->toDateString())
                ->whereDate('date', '<=', $monthEnd->toDateString())
                ->get()
                ->sum(fn (ProductionDay $day) => (float) $day->total_kg);
            $productionTons = $productionKg / 1000;

            if ($productionTons <= 0) {
                return 0;
            }

            $expense = MonthlyExpense::where('year', $year)->where('month', $month)->first();
            $costPerTon = ((float) ($expense?->total_expenses ?? 0)) / $productionTons;
            $soldTons = $monthSales->sum(fn (StockSale $sale) => (float) $sale->weight_kg) / 1000;

            return $soldTons * $costPerTon;
        });

        return [
            'revenue' => round($revenue, 3),
            'material_cogs' => round($materialCogs, 3),
            'purchases' => round($materialCogs, 3),
            'recycle_cost' => round((float) $recycleCost, 3),
            'profit' => round($revenue - $materialCogs - (float) $recycleCost, 3),
            'sold_kg' => round((float) StockSale::query()->tap($dateRange)->sum('weight_kg'), 3),
        ];
    }

    public function actualCostPerTon(int $year, int $month): float
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();
        $productionKg = ProductionDay::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->sum(fn (ProductionDay $day) => (float) $day->total_kg);

        if ($productionKg <= 0) {
            return 0;
        }

        $expenses = (float) (MonthlyExpense::where('year', $year)->where('month', $month)->first()?->total_expenses ?? 0);

        return round($expenses / ($productionKg / 1000), 3);
    }

    public function lastCompletedMonthActualCostPerTon(): array
    {
        $month = Carbon::today()->startOfMonth()->subMonth();

        return [
            'year' => $month->year,
            'month' => $month->month,
            'label' => $month->format('M Y'),
            'cost_per_ton' => $this->actualCostPerTon($month->year, $month->month),
        ];
    }

    public function actualCostPerTonForPeriod(?string $from = null, ?string $to = null): float
    {
        $dateRange = fn ($query) => $query
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $to));
        $productionKg = ProductionDay::query()
            ->tap($dateRange)
            ->get()
            ->sum(fn (ProductionDay $day) => (float) $day->total_kg);

        if ($productionKg <= 0) {
            return 0;
        }

        return round($this->operatingExpenses($from, $to) / ($productionKg / 1000), 3);
    }

    public function actualProfitSummary(?string $from = null, ?string $to = null): array
    {
        $dateRange = fn ($query) => $query
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $to));
        $stock = $this->stockProfitSummary($from, $to);
        $recycleIncome = (float) RecycleOut::query()->tap($dateRange)->sum('total_amount');
        $operatingExpenses = $this->operatingExpenses($from, $to);

        return [
            'recycle_income' => round($recycleIncome, 3),
            'stock_revenue' => $stock['revenue'],
            'stock_material_cogs' => $stock['material_cogs'],
            'operating_expenses' => round($operatingExpenses, 3),
            'actual_cost_per_ton' => $this->actualCostPerTonForPeriod($from, $to),
            'actual_profit_loss' => round($recycleIncome + $stock['revenue'] - $stock['material_cogs'] - $operatingExpenses, 3),
        ];
    }

    public function operatingExpenses(?string $from = null, ?string $to = null): float
    {
        $start = $from ? Carbon::parse($from)->startOfMonth() : $this->firstActivityDate()?->startOfMonth();
        $end = $to ? Carbon::parse($to)->startOfMonth() : $this->lastActivityDate()?->startOfMonth();

        if (! $start || ! $end || $end->lt($start)) {
            return 0;
        }

        return (float) MonthlyExpense::where(function ($query) use ($start, $end) {
            $query->where('year', '>', $start->year)
                ->orWhere(fn ($query) => $query->where('year', $start->year)->where('month', '>=', $start->month));
        })
            ->where(function ($query) use ($end) {
                $query->where('year', '<', $end->year)
                    ->orWhere(fn ($query) => $query->where('year', $end->year)->where('month', '<=', $end->month));
            })
            ->get()
            ->sum(fn (MonthlyExpense $expense) => $expense->total_expenses);
    }

    public function stockMaterialCogs(?string $from = null, ?string $to = null): float
    {
        $fromDate = $from ? Carbon::parse($from)->toDateString() : null;
        $toDate = $to ? Carbon::parse($to)->toDateString() : null;
        $inventory = [];
        $cogs = 0.0;

        $purchases = StockPurchase::query()
            ->when($toDate, fn ($query) => $query->whereDate('date', '<=', $toDate))
            ->get(['date', 'material_id', 'weight_kg', 'total_cost'])
            ->map(fn (StockPurchase $purchase) => [
                'type' => 'purchase',
                'date' => $purchase->date->toDateString(),
                'sort' => 10,
                'material_id' => $purchase->material_id,
                'weight_kg' => (float) $purchase->weight_kg,
                'amount' => (float) $purchase->total_cost,
            ]);
        $sales = StockSale::query()
            ->when($toDate, fn ($query) => $query->whereDate('date', '<=', $toDate))
            ->get(['date', 'material_id', 'weight_kg'])
            ->map(fn (StockSale $sale) => [
                'type' => 'sale',
                'date' => $sale->date->toDateString(),
                'sort' => 20,
                'material_id' => $sale->material_id,
                'weight_kg' => (float) $sale->weight_kg,
                'amount' => 0.0,
            ]);

        $transactions = $purchases
            ->merge($sales)
            ->sortBy([['date', 'asc'], ['sort', 'asc']])
            ->values();

        foreach ($transactions as $transaction) {
            $key = $transaction['material_id'] ?? 0;
            $inventory[$key] ??= ['kg' => 0.0, 'value' => 0.0];
            $averageCost = $inventory[$key]['kg'] > 0 ? $inventory[$key]['value'] / $inventory[$key]['kg'] : 0.0;

            if ($transaction['type'] === 'purchase') {
                $inventory[$key]['kg'] += $transaction['weight_kg'];
                $inventory[$key]['value'] += $transaction['weight_kg'] < 0 && $transaction['amount'] == 0.0
                    ? $transaction['weight_kg'] * $averageCost
                    : $transaction['amount'];
                continue;
            }

            $saleCogs = $transaction['weight_kg'] * $averageCost;

            if ((! $fromDate || $transaction['date'] >= $fromDate) && (! $toDate || $transaction['date'] <= $toDate)) {
                $cogs += $saleCogs;
            }

            $inventory[$key]['kg'] -= $transaction['weight_kg'];
            $inventory[$key]['value'] -= $saleCogs;

            if (abs($inventory[$key]['kg']) < 0.0005) {
                $inventory[$key] = ['kg' => 0.0, 'value' => 0.0];
            }
        }

        return round($cogs, 3);
    }

    public function customerBalance(Customer $customer, ?string $beforeDate = null): float
    {
        $queryDate = fn ($query) => $beforeDate ? $query->whereDate('date', '<', $beforeDate) : $query;

        $recycleOut = RecycleOut::where('customer_id', $customer->id)->tap($queryDate)->sum('total_amount');
        $stockSales = StockSale::where('customer_id', $customer->id)->tap($queryDate)->sum('sales_value');
        $payments = Payment::where('customer_id', $customer->id)->tap($queryDate)->sum('amount');

        return round((float) $customer->opening_balance + (float) $recycleOut + (float) $stockSales - (float) $payments, 3);
    }

    public function customerWeightDifference(Customer $customer, ?string $beforeDate = null): float
    {
        $queryDate = fn ($query) => $beforeDate ? $query->whereDate('date', '<', $beforeDate) : $query;

        $in = RecycleIn::where('customer_id', $customer->id)->tap($queryDate)->sum('weight_kg');
        $out = RecycleOut::where('customer_id', $customer->id)->tap($queryDate)->sum('weight_kg');

        return round((float) $customer->opening_weight_balance_kg + (float) $in - (float) $out, 3);
    }

    public function customerStatement(Customer $customer, ?string $from, ?string $to): array
    {
        $fromDate = $from ?: '1900-01-01';
        $toDate = $to ?: Carbon::today()->toDateString();
        $runningBalance = $this->customerBalance($customer, $fromDate);
        $runningWeight = $this->customerWeightDifference($customer, $fromDate);
        $tables = $this->customerStatementTables($customer, $fromDate, $toDate);

        $rows = $this->customerTransactions($customer, $fromDate, $toDate)
            ->sortBy([['date', 'asc'], ['sort', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (array $row) use (&$runningBalance, &$runningWeight) {
                $runningBalance = round($runningBalance + $row['balance_delta'], 3);
                $runningWeight = round($runningWeight + $row['weight_delta'], 3);
                $row['running_balance'] = $runningBalance;
                $row['running_weight'] = $runningWeight;

                return $row;
            });
        $paymentBalances = $rows
            ->where('type', 'Payment')
            ->keyBy('id')
            ->map(fn (array $row) => $row['running_balance']);

        $tables['payments']->each(function (Payment $payment) use ($paymentBalances) {
            $payment->setAttribute('remaining_balance', $paymentBalances[$payment->id] ?? null);
        });

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'opening_balance' => $this->customerBalance($customer, $fromDate),
            'opening_weight' => $this->customerWeightDifference($customer, $fromDate),
            'total_in_kg' => round($tables['recycle_ins']->sum('weight_kg'), 3),
            'total_recycle_out_kg' => round($tables['recycle_outs']->sum('weight_kg'), 3),
            'total_sales_out_kg' => round($tables['stock_sales']->sum('weight_kg'), 3),
            'period_recycle_charges' => round($rows->where('type', 'Recycle Out')->sum('amount'), 3),
            'period_stock_charges' => round($rows->where('type', 'Stock Sale')->sum('amount'), 3),
            'period_total_charges' => round($rows->where('amount', '>', 0)->sum('amount'), 3),
            'period_payments' => round(abs($rows->where('type', 'Payment')->sum('amount')), 3),
            'closing_balance' => $runningBalance,
            'closing_weight' => $runningWeight,
            'transactions' => $rows,
            'tables' => $tables,
        ];
    }

    public function supplierBalance(Supplier $supplier, ?string $beforeDate = null): float
    {
        $queryDate = fn ($query) => $beforeDate ? $query->whereDate('date', '<', $beforeDate) : $query;

        $purchases = StockPurchase::where('supplier_id', $supplier->id)->tap($queryDate)->sum('total_cost');
        $payments = SupplierPayment::where('supplier_id', $supplier->id)->tap($queryDate)->sum('amount');

        return round((float) $supplier->opening_balance + (float) $purchases - (float) $payments, 3);
    }

    public function supplierStatement(Supplier $supplier, ?string $from, ?string $to): array
    {
        $fromDate = $from ?: '1900-01-01';
        $toDate = $to ?: Carbon::today()->toDateString();
        $runningBalance = $this->supplierBalance($supplier, $fromDate);
        $between = fn ($query) => $query->whereDate('date', '>=', $fromDate)->whereDate('date', '<=', $toDate);
        $purchases = StockPurchase::with('material')
            ->where('supplier_id', $supplier->id)
            ->tap($between)
            ->orderBy('date')
            ->orderBy('id')
            ->get();
        $payments = SupplierPayment::where('supplier_id', $supplier->id)
            ->tap($between)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $transactions = collect()
            ->merge($purchases->map(fn (StockPurchase $purchase) => [
                'id' => $purchase->id,
                'sort' => 10,
                'date' => $purchase->date->toDateString(),
                'type' => 'Purchase',
                'description' => $purchase->material?->name ?: '-',
                'weight_kg' => (float) $purchase->weight_kg,
                'amount' => (float) $purchase->total_cost,
                'balance_delta' => (float) $purchase->total_cost,
                'notes' => $purchase->notes,
            ]))
            ->merge($payments->map(fn (SupplierPayment $payment) => [
                'id' => $payment->id,
                'sort' => 20,
                'date' => $payment->date->toDateString(),
                'type' => 'Payment',
                'description' => $payment->payment_method ?: str_replace('_', ' ', ucfirst($payment->payment_type ?? 'cash')),
                'weight_kg' => 0,
                'amount' => -1 * (float) $payment->amount,
                'balance_delta' => -1 * (float) $payment->amount,
                'notes' => $payment->notes,
            ]))
            ->sortBy([['date', 'asc'], ['sort', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (array $row) use (&$runningBalance) {
                $runningBalance = round($runningBalance + $row['balance_delta'], 3);
                $row['running_balance'] = $runningBalance;

                return $row;
            });

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'opening_balance' => $this->supplierBalance($supplier, $fromDate),
            'period_purchases' => round((float) $purchases->sum('total_cost'), 3),
            'period_purchase_kg' => round((float) $purchases->sum('weight_kg'), 3),
            'period_payments' => round((float) $payments->sum('amount'), 3),
            'closing_balance' => $runningBalance,
            'purchases' => $purchases,
            'payments' => $payments,
            'transactions' => $transactions,
        ];
    }

    private function firstActivityDate(): ?Carbon
    {
        $dates = collect([
            RecycleOut::min('date'),
            StockSale::min('date'),
            StockPurchase::min('date'),
            ProductionDay::min('date'),
        ])->filter();

        return $dates->isEmpty() ? null : Carbon::parse($dates->min());
    }

    private function lastActivityDate(): ?Carbon
    {
        $dates = collect([
            RecycleOut::max('date'),
            StockSale::max('date'),
            StockPurchase::max('date'),
            ProductionDay::max('date'),
        ])->filter();

        return $dates->isEmpty() ? null : Carbon::parse($dates->max());
    }

    private function customerStatementTables(Customer $customer, string $from, string $to): array
    {
        $between = fn ($query) => $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);

        return [
            'recycle_ins' => RecycleIn::with('material')
                ->where('customer_id', $customer->id)
                ->tap($between)
                ->orderBy('date')
                ->orderBy('id')
                ->get(),
            'recycle_outs' => RecycleOut::with('material')
                ->where('customer_id', $customer->id)
                ->tap($between)
                ->orderBy('date')
                ->orderBy('id')
                ->get(),
            'payments' => Payment::where('customer_id', $customer->id)
                ->tap($between)
                ->orderBy('date')
                ->orderBy('id')
                ->get(),
            'stock_sales' => StockSale::with('material')
                ->where('customer_id', $customer->id)
                ->tap($between)
                ->orderBy('date')
                ->orderBy('id')
                ->get(),
        ];
    }

    private function customerTransactions(Customer $customer, string $from, string $to): Collection
    {
        $between = fn ($query) => $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);

        return collect()
            ->merge(RecycleIn::with('material')->where('customer_id', $customer->id)->tap($between)->get()->map(fn ($item) => [
                'id' => $item->id,
                'sort' => 10,
                'date' => $item->date->toDateString(),
                'type' => 'Recycle In',
                'description' => $item->material?->name ?: '-',
                'weight_delta' => (float) $item->weight_kg,
                'display_weight' => (float) $item->weight_kg,
                'balance_delta' => 0,
                'amount' => 0,
                'notes' => $item->notes,
            ]))
            ->merge(RecycleOut::with('material')->where('customer_id', $customer->id)->tap($between)->get()->map(fn ($item) => [
                'id' => $item->id,
                'sort' => 20,
                'date' => $item->date->toDateString(),
                'type' => 'Recycle Out',
                'description' => $this->recycleOutDescription($item),
                'weight_delta' => -1 * (float) $item->weight_kg,
                'display_weight' => -1 * (float) $item->weight_kg,
                'balance_delta' => (float) $item->total_amount,
                'amount' => (float) $item->total_amount,
                'notes' => $item->notes,
            ]))
            ->merge(StockSale::with('material')->where('customer_id', $customer->id)->tap($between)->get()->map(fn ($item) => [
                'id' => $item->id,
                'sort' => 30,
                'date' => $item->date->toDateString(),
                'type' => 'Stock Sale',
                'description' => $item->material?->name ?: '-',
                'weight_delta' => 0,
                'display_weight' => -1 * (float) $item->weight_kg,
                'balance_delta' => (float) $item->sales_value,
                'amount' => (float) $item->sales_value,
                'notes' => $item->notes,
            ]))
            ->merge(Payment::where('customer_id', $customer->id)->tap($between)->get()->map(fn ($item) => [
                'id' => $item->id,
                'sort' => 40,
                'date' => $item->date->toDateString(),
                'type' => 'Payment',
                'description' => $item->payment_method ?: 'Payment',
                'weight_delta' => 0,
                'display_weight' => 0,
                'balance_delta' => -1 * (float) $item->amount,
                'amount' => -1 * (float) $item->amount,
                'notes' => $item->notes,
            ]));
    }

    private function recycleOutDescription(RecycleOut $item): string
    {
        $parts = [];

        if ($item->material?->name) {
            $parts[] = $item->material->name;
        }

        foreach ([
            __('Recycled') => (float) $item->recycled_out_kg,
            __('Waste') => (float) $item->waste_kg,
            __('Non-recycled') => (float) $item->non_recycled_kg,
        ] as $label => $value) {
            if (abs($value) >= 0.0005) {
                $parts[] = $label.': '.number_format($value, 3).' '.__('kg');
            }
        }

        return implode(' | ', $parts);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Material;
use App\Services\ApicoCalculator;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    public function monthly(Request $request, ApicoCalculator $calculator)
    {
        $year = (int) $request->input('year', now()->year);
        $fromMonth = max(1, min(12, (int) $request->input('from_month', 1)));
        $toMonth = max($fromMonth, min(12, (int) $request->input('to_month', 12)));
        $search = trim((string) $request->input('q', ''));

        $months = $this->monthlyRows($year, $fromMonth, $toMonth, $calculator)
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row) => str_contains(strtolower($row['month']), strtolower($search))))
            ->values();

        $columns = $this->monthlyColumns();

        return view('reports.monthly', compact('year', 'fromMonth', 'toMonth', 'search', 'months', 'columns'));
    }

    public function monthlyExport(Request $request, ApicoCalculator $calculator, SimpleXlsxExporter $exporter)
    {
        $year = (int) $request->input('year', now()->year);
        $fromMonth = max(1, min(12, (int) $request->input('from_month', 1)));
        $toMonth = max($fromMonth, min(12, (int) $request->input('to_month', 12)));
        $search = trim((string) $request->input('q', ''));
        $columns = $this->selectedColumns($this->monthlyColumns(), $request);
        $rows = $this->monthlyRows($year, $fromMonth, $toMonth, $calculator)
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row) => str_contains(strtolower($row['month']), strtolower($search))))
            ->values();

        return $exporter->download(
            'monthly-report-'.$year.'.xlsx',
            $this->exportHeaders($columns),
            $this->exportRows($rows, $columns)
        );
    }

    private function monthlyRows(int $year, int $fromMonth, int $toMonth, ApicoCalculator $calculator): Collection
    {
        return collect(range($fromMonth, $toMonth))->map(function (int $month) use ($year, $calculator) {
            $from = sprintf('%d-%02d-01', $year, $month);
            $to = date('Y-m-t', strtotime($from));

            $dateRange = fn ($query) => $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
            $recycleOutKg = RecycleOut::query()->tap($dateRange)->sum('weight_kg');
            $wasteKg = RecycleOut::query()->tap($dateRange)->sum('waste_kg');

            return [
                'month' => date('M Y', strtotime($from)),
                'recycle_in_kg' => RecycleIn::query()->tap($dateRange)->sum('weight_kg'),
                'recycle_out_kg' => $recycleOutKg,
                'recycled_out_kg' => RecycleOut::query()->tap($dateRange)->sum('recycled_out_kg'),
                'waste_kg' => $wasteKg,
                'waste_percentage' => $recycleOutKg > 0 ? round(((float) $wasteKg / (float) $recycleOutKg) * 100, 2) : 0,
                'non_recycled_kg' => RecycleOut::query()->tap($dateRange)->sum('non_recycled_kg'),
                'payments' => Payment::query()->tap($dateRange)->sum('amount'),
                'stock_sales' => StockSale::query()->tap($dateRange)->sum('sales_value'),
                'stock_profit' => $calculator->stockProfitSummary($from, $to)['profit'],
                'actual_cost_per_ton' => $calculator->actualCostPerTonForPeriod($from, $to),
                'actual_profit_loss' => $calculator->actualProfitSummary($from, $to)['actual_profit_loss'],
            ];
        });
    }

    public function customerStatement(Customer $customer, Request $request, ApicoCalculator $calculator)
    {
        $statement = $calculator->customerStatement(
            $customer,
            $request->input('from', now()->startOfMonth()->toDateString()),
            $request->input('to', now()->toDateString())
        );

        return view('customers.show', compact('customer', 'statement'));
    }

    public function stockProfit(Request $request, ApicoCalculator $calculator)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $search = trim((string) $request->input('q', ''));
        $customerId = $request->integer('customer_id') ?: null;
        $materialId = $request->integer('material_id') ?: null;
        $sales = $this->stockProfitSales($from, $to, $search, $customerId, $materialId)->get();

        return view('reports.stock-profit', [
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'customerId' => $customerId,
            'materialId' => $materialId,
            'customers' => Customer::orderBy('name')->get(),
            'materials' => Material::orderBy('name')->get(),
            'sales' => $sales,
            'summary' => $calculator->stockProfitSummary($from, $to),
            'remainingStock' => $calculator->remainingStockWeight(),
            'columns' => $this->stockProfitColumns(),
        ]);
    }

    public function stockProfitExport(Request $request, SimpleXlsxExporter $exporter)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $search = trim((string) $request->input('q', ''));
        $customerId = $request->integer('customer_id') ?: null;
        $materialId = $request->integer('material_id') ?: null;
        $columns = $this->selectedColumns($this->stockProfitColumns(), $request);
        $rows = $this->stockProfitSales($from, $to, $search, $customerId, $materialId)->get()->map(fn (StockSale $sale) => [
            'date' => $sale->date?->toDateString(),
            'customer' => $sale->customer?->name,
            'material' => $sale->material?->name ?: '-',
            'weight_kg' => number_format((float) $sale->weight_kg, 3, '.', ''),
            'selling_price_per_kg' => number_format((float) $sale->selling_price_per_kg, 3, '.', ''),
            'sales_value' => number_format((float) $sale->sales_value, 3, '.', ''),
            'net_profit' => number_format((float) $sale->net_profit, 3, '.', ''),
            'notes' => $sale->notes,
        ]);

        return $exporter->download('stock-profit.xlsx', $this->exportHeaders($columns), $this->exportRows($rows, $columns));
    }

    private function stockProfitSales(string $from, string $to, string $search, ?int $customerId, ?int $materialId)
    {
        return StockSale::with(['customer', 'material'])
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($materialId, fn ($query) => $query->where('material_id', $materialId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('notes', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('material', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('date')
            ->latest('id');
    }

    public function alerts(Request $request, ApicoCalculator $calculator)
    {
        $alerts = $this->alertRows($calculator);
        $type = trim((string) $request->input('type', ''));
        $search = trim((string) $request->input('q', ''));

        $alerts = $alerts
            ->when($type !== '', fn (Collection $rows) => $rows->where('type', $type))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row) => str_contains(strtolower($row['message']), strtolower($search))))
            ->values();
        $types = $this->alertRows($calculator)->pluck('type')->unique()->values();
        $columns = $this->alertColumns();

        return view('reports.alerts', compact('alerts', 'types', 'type', 'search', 'columns'));
    }

    public function alertsExport(Request $request, ApicoCalculator $calculator, SimpleXlsxExporter $exporter)
    {
        $type = trim((string) $request->input('type', ''));
        $search = trim((string) $request->input('q', ''));
        $columns = $this->selectedColumns($this->alertColumns(), $request);
        $alerts = $this->alertRows($calculator)
            ->when($type !== '', fn (Collection $rows) => $rows->where('type', $type))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row) => str_contains(strtolower($row['message']), strtolower($search))))
            ->values();

        return $exporter->download('alerts.xlsx', $this->exportHeaders($columns), $this->exportRows($alerts, $columns));
    }

    private function alertRows(ApicoCalculator $calculator): Collection
    {
        $alerts = collect();

        RecycleOut::where('weight_kg', '>', 0)->where('rate_per_kg', 0)->get()->each(fn ($row) => $alerts->push(['type' => 'Zero recycle out price', 'message' => "Recycle out #{$row->id} has zero price."]));
        Payment::where('amount', '<', 0)->get()->each(fn ($row) => $alerts->push(['type' => 'Negative payment', 'message' => "Payment #{$row->id} is negative."]));
        Customer::where('status', 'inactive')->get()->each(function (Customer $customer) use ($alerts, $calculator) {
            if ($calculator->customerBalance($customer) != 0.0) {
                $alerts->push(['type' => 'Inactive customer with balance', 'message' => "{$customer->name} has a non-zero balance."]);
            }
        });

        return $alerts;
    }

    private function monthlyColumns(): array
    {
        return [
            'month' => __('Month'),
            'recycle_in_kg' => __('Recycle In Kg'),
            'recycle_out_kg' => __('Total Out Kg'),
            'recycled_out_kg' => __('Recycled Out Kg'),
            'waste_kg' => __('Waste Kg'),
            'waste_percentage' => __('Waste %'),
            'non_recycled_kg' => __('Non-Recycled Kg'),
            'payments' => __('Payments'),
            'stock_sales' => __('Stock Sales'),
            'actual_cost_per_ton' => __('Actual Cost/Ton'),
            'stock_profit' => __('Actual Stock Profit'),
            'actual_profit_loss' => __('Actual P&L'),
        ];
    }

    private function stockProfitColumns(): array
    {
        return [
            'date' => __('Date'),
            'customer' => __('Customer'),
            'material' => __('Material'),
            'weight_kg' => __('Kg'),
            'selling_price_per_kg' => __('Selling Price/Kg'),
            'sales_value' => __('Sales Value'),
            'net_profit' => __('Net Profit'),
            'notes' => __('Notes'),
        ];
    }

    private function alertColumns(): array
    {
        return [
            'type' => __('Type'),
            'message' => __('Message'),
        ];
    }

    private function selectedColumns(array $columns, Request $request): array
    {
        $selected = $request->input('columns', array_keys($columns));
        $selected = is_array($selected) ? $selected : array_keys($columns);

        return collect($columns)->only($selected)->all() ?: $columns;
    }

    private function exportHeaders(array $columns): array
    {
        return array_values($columns);
    }

    private function exportRows(iterable $rows, array $columns): array
    {
        return collect($rows)->map(function ($row) use ($columns) {
            $row = is_array($row) ? $row : (array) $row;

            return collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all();
        })->all();
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AgingReportService
{
    public function customers(string $asOf): Collection
    {
        return Customer::orderBy('name')->get()->map(function (Customer $customer) use ($asOf) {
            $transactions = collect([
                ['date' => '1900-01-01', 'sort' => 0, 'amount' => (float) $customer->opening_balance],
            ])->merge(RecycleOut::where('customer_id', $customer->id)->whereDate('date', '<=', $asOf)->get()
                ->map(fn (RecycleOut $row) => ['date' => $row->date->toDateString(), 'sort' => 10, 'amount' => (float) $row->total_amount]))
                ->merge(StockSale::where('customer_id', $customer->id)->whereDate('date', '<=', $asOf)->get()
                    ->map(fn (StockSale $row) => ['date' => $row->date->toDateString(), 'sort' => 20, 'amount' => (float) $row->sales_value]))
                ->merge(Payment::where('customer_id', $customer->id)->whereDate('date', '<=', $asOf)->get()
                    ->map(fn (Payment $row) => ['date' => $row->date->toDateString(), 'sort' => 30, 'amount' => -1 * (float) $row->amount]));

            return ['id' => $customer->id, 'name' => $customer->name] + $this->age($transactions, $asOf);
        })->filter(fn (array $row) => abs($row['balance']) >= 0.0005)->values();
    }

    public function suppliers(string $asOf): Collection
    {
        return Supplier::orderBy('name')->get()->map(function (Supplier $supplier) use ($asOf) {
            $transactions = collect([
                ['date' => '1900-01-01', 'sort' => 0, 'amount' => (float) $supplier->opening_balance],
            ])->merge(StockPurchase::where('supplier_id', $supplier->id)->whereDate('date', '<=', $asOf)->get()
                ->map(fn (StockPurchase $row) => ['date' => $row->date->toDateString(), 'sort' => 10, 'amount' => (float) $row->total_cost]))
                ->merge(SupplierPayment::where('supplier_id', $supplier->id)->whereDate('date', '<=', $asOf)->get()
                    ->map(fn (SupplierPayment $row) => ['date' => $row->date->toDateString(), 'sort' => 20, 'amount' => -1 * (float) $row->amount]));

            return ['id' => $supplier->id, 'name' => $supplier->name] + $this->age($transactions, $asOf);
        })->filter(fn (array $row) => abs($row['balance']) >= 0.0005)->values();
    }

    private function age(Collection $transactions, string $asOf): array
    {
        $openItems = [];
        $unappliedCredit = 0.0;

        foreach ($transactions->sortBy([['date', 'asc'], ['sort', 'asc']]) as $transaction) {
            $amount = (float) $transaction['amount'];

            if ($amount > 0) {
                $applied = min($amount, $unappliedCredit);
                $amount -= $applied;
                $unappliedCredit -= $applied;

                if ($amount > 0.0005) {
                    $openItems[] = ['date' => $transaction['date'], 'amount' => $amount];
                }

                continue;
            }

            $credit = abs($amount);
            foreach ($openItems as &$item) {
                if ($credit <= 0.0005) {
                    break;
                }

                $applied = min($credit, $item['amount']);
                $item['amount'] -= $applied;
                $credit -= $applied;
            }
            unset($item);
            $openItems = array_values(array_filter($openItems, fn (array $item) => $item['amount'] > 0.0005));
            $unappliedCredit += $credit;
        }

        $buckets = ['current' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'over_90' => 0.0];
        $asOfDate = Carbon::parse($asOf)->startOfDay();

        foreach ($openItems as $item) {
            $age = Carbon::parse($item['date'])->startOfDay()->diffInDays($asOfDate, false);
            $bucket = match (true) {
                $age <= 30 => 'current',
                $age <= 60 => 'days_31_60',
                $age <= 90 => 'days_61_90',
                default => 'over_90',
            };
            $buckets[$bucket] += $item['amount'];
        }

        $buckets = collect($buckets)->map(fn (float $amount) => round($amount, 3))->all();
        $openTotal = array_sum($buckets);

        return $buckets + [
            'unapplied_credit' => round($unappliedCredit, 3),
            'balance' => round($openTotal - $unappliedCredit, 3),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\RecycleOut;
use App\Models\Setting;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\SupplierPayment;

class AccountingDashboardController extends Controller
{
    public function __invoke()
    {
        $sourceCoverage = $this->sourceCoverage();

        return view('accounting.dashboard', [
            'accountingEnabled' => Setting::where('key', 'accounting_enabled')->value('value') === '1',
            'accountCount' => ChartOfAccount::count(),
            'mappingCount' => AccountMapping::where('is_active', true)->count(),
            'postedEntryCount' => JournalEntry::where('status', 'posted')->count(),
            'reversedEntryCount' => JournalEntry::where('status', 'reversed')->count(),
            'bankAccountCount' => BankAccount::where('is_active', true)->count(),
            'cashAccountCount' => CashAccount::where('is_active', true)->count(),
            'recentEntries' => JournalEntry::with('lines')->latest('entry_date')->latest('id')->limit(8)->get(),
            'sourceCoverage' => $sourceCoverage,
        ]);
    }

    private function sourceCoverage(): array
    {
        $posted = JournalEntry::where('source_module', 'operations')
            ->where('status', 'posted')
            ->whereNull('reversal_of_journal_entry_id')
            ->get(['source_type', 'source_id', 'posting_type'])
            ->mapWithKeys(fn (JournalEntry $entry) => [
                $entry->source_type.':'.$entry->source_id.':'.$entry->posting_type => true,
            ]);
        $expected = collect();
        RecycleOut::each(function (RecycleOut $row) use ($expected) {
            if (abs((float) $row->total_amount) >= 0.0005) {
                $expected->push('RecycleOut:'.$row->id.':recycle_out_revenue');
            }
        });
        Payment::each(function (Payment $row) use ($expected) {
            if (abs((float) $row->amount) >= 0.0005) {
                $expected->push('Payment:'.$row->id.':customer_payment');
            }
        });
        StockPurchase::each(function (StockPurchase $row) use ($expected) {
            if (abs((float) $row->total_cost) >= 0.0005) {
                $expected->push('StockPurchase:'.$row->id.':stock_purchase');
            }
        });
        StockSale::each(function (StockSale $row) use ($expected) {
            if (abs((float) $row->sales_value) >= 0.0005) {
                $expected->push('StockSale:'.$row->id.':stock_sale_revenue');
            }
            if (abs((float) $row->weight_kg) >= 0.0005) {
                $expected->push('StockSale:'.$row->id.':stock_sale_cogs');
            }
        });
        SupplierPayment::each(function (SupplierPayment $row) use ($expected) {
            if (abs((float) $row->amount) >= 0.0005) {
                $expected->push('SupplierPayment:'.$row->id.':supplier_payment');
            }
        });
        $covered = $expected->filter(fn (string $key) => $posted->has($key))->count();
        $total = $expected->count();

        return [
            'covered' => $covered,
            'total' => $total,
            'missing' => $total - $covered,
            'percentage' => $total > 0 ? round(($covered / $total) * 100, 2) : 100.0,
        ];
    }
}

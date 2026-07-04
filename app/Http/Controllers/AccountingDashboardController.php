<?php

namespace App\Http\Controllers;

use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Setting;

class AccountingDashboardController extends Controller
{
    public function __invoke()
    {
        return view('accounting.dashboard', [
            'accountingEnabled' => Setting::where('key', 'accounting_enabled')->value('value') === '1',
            'accountCount' => ChartOfAccount::count(),
            'mappingCount' => AccountMapping::where('is_active', true)->count(),
            'postedEntryCount' => JournalEntry::where('status', 'posted')->count(),
            'reversedEntryCount' => JournalEntry::where('status', 'reversed')->count(),
            'bankAccountCount' => BankAccount::where('is_active', true)->count(),
            'cashAccountCount' => CashAccount::where('is_active', true)->count(),
            'recentEntries' => JournalEntry::with('lines')->latest('entry_date')->latest('id')->limit(8)->get(),
        ]);
    }
}

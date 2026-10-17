<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingFinancialReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_ledger_profit_loss_and_balance_sheet_reconcile(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $cash = $this->account('1110', 'Cash', 'asset', 'debit');
        $revenue = $this->account('4100', 'Revenue', 'revenue', 'credit');
        $expense = $this->account('6200', 'Rent', 'expense', 'debit');
        $payable = $this->account('2100', 'Payable', 'liability', 'credit');

        $this->entry('JE-OPEN', '2025-12-31', [
            [$cash, 50, 0],
            [$payable, 0, 50],
        ]);
        $this->entry('JE-SALE', '2026-01-10', [
            [$cash, 100, 0],
            [$revenue, 0, 100],
        ]);
        $this->entry('JE-RENT', '2026-01-15', [
            [$expense, 30, 0],
            [$cash, 0, 30],
        ]);

        $ledger = $this->actingAs($accountant)->get(route('accounting.reports.general-ledger', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
            'account_id' => $cash->id,
        ]));
        $ledger->assertOk();
        $this->assertSame(50.0, $ledger->viewData('openingBalance'));
        $this->assertSame(120.0, $ledger->viewData('closingBalance'));

        $profitLoss = $this->actingAs($accountant)->get(route('accounting.reports.profit-loss', [
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ]));
        $profitLoss->assertOk();
        $this->assertSame(100.0, $profitLoss->viewData('revenue'));
        $this->assertSame(30.0, $profitLoss->viewData('expenses'));
        $this->assertSame(70.0, $profitLoss->viewData('netProfit'));

        $balanceSheet = $this->actingAs($accountant)->get(route('accounting.reports.balance-sheet', ['to' => '2026-01-31']));
        $balanceSheet->assertOk();
        $this->assertSame(120.0, $balanceSheet->viewData('assets'));
        $this->assertSame(50.0, $balanceSheet->viewData('liabilities'));
        $this->assertSame(70.0, $balanceSheet->viewData('currentEarnings'));
        $this->assertSame(0.0, $balanceSheet->viewData('difference'));
    }

    private function account(string $code, string $name, string $type, string $normalBalance): ChartOfAccount
    {
        return ChartOfAccount::create([
            'code' => $code,
            'name_en' => $name,
            'name_ar' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'is_posting' => true,
            'is_active' => true,
        ]);
    }

    private function entry(string $number, string $date, array $lines): JournalEntry
    {
        $entry = JournalEntry::create([
            'entry_no' => $number,
            'entry_date' => $date,
            'memo_en' => $number,
            'memo_ar' => $number,
            'status' => 'posted',
            'is_auto' => false,
            'posted_at' => $date.' 12:00:00',
        ]);

        foreach ($lines as [$account, $debit, $credit]) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        return $entry;
    }
}

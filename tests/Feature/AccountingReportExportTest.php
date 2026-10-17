<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class AccountingReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_export_every_accounting_report(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);

        foreach ([
            'accounting.reports.general-ledger.export',
            'accounting.reports.profit-loss.export',
            'accounting.reports.balance-sheet.export',
            'accounting.reports.receivables-aging.export',
            'accounting.reports.payables-aging.export',
            'accounting.reports.cash-bank.export',
        ] as $route) {
            $response = $this->actingAs($accountant)->get(route($route, [
                'from' => '2026-01-01',
                'to' => '2026-12-31',
            ]));

            $response->assertOk();
            $this->assertStringContainsString(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                (string) $response->headers->get('content-type'),
                $route
            );
        }
    }

    public function test_general_ledger_export_honors_selected_columns_and_filters(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant', 'is_active' => true]);
        $cash = $this->account('1110', 'Cash', 'asset', 'debit');
        $revenue = $this->account('4100', 'Revenue', 'revenue', 'credit');
        $entry = JournalEntry::create([
            'entry_no' => 'JE-EXPORT-1',
            'entry_date' => '2026-04-10',
            'memo_en' => 'Export filter check',
            'memo_ar' => 'Export filter check',
            'status' => 'posted',
            'is_auto' => false,
            'posted_at' => '2026-04-10 12:00:00',
        ]);
        foreach ([[$cash, 75, 0], [$revenue, 0, 75]] as [$account, $debit, $credit]) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        $response = $this->actingAs($accountant)->get(route('accounting.reports.general-ledger.export', [
            'from' => '2026-04-01',
            'to' => '2026-04-30',
            'account_id' => $cash->id,
            'columns' => ['date', 'entry_no', 'running_balance'],
        ]));

        $response->assertOk();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertIsString($worksheet);
        $this->assertStringContainsString('JE-EXPORT-1', $worksheet);
        $this->assertStringContainsString('Running Balance', $worksheet);
        $this->assertStringNotContainsString('Export filter check', $worksheet);
        $this->assertStringNotContainsString('Debit', $worksheet);
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
}

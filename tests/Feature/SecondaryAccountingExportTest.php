<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\SalaryAdvance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class SecondaryAccountingExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_salary_advance_export_honors_filters_and_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $included = Employee::create(['name_en' => 'Included Employee', 'base_salary' => 300, 'is_active' => true]);
        $excluded = Employee::create(['name_en' => 'Excluded Employee', 'base_salary' => 300, 'is_active' => true]);
        SalaryAdvance::create(['employee_id' => $included->id, 'payment_date' => '2026-07-05', 'amount' => 30, 'payment_type' => 'cash', 'status' => 'posted']);
        SalaryAdvance::create(['employee_id' => $excluded->id, 'payment_date' => '2026-08-05', 'amount' => 50, 'payment_type' => 'cash', 'status' => 'posted']);

        $response = $this->actingAs($admin)->get(route('accounting.salary-advances.export', [
            'year' => 2026,
            'month' => 7,
            'employee_id' => $included->id,
            'columns' => ['date', 'employee', 'amount'],
        ]));

        $response->assertOk()->assertDownload('salary-advances-2026-07.xlsx');
        $worksheet = $this->worksheet($response->baseResponse);
        $this->assertStringContainsString('Included Employee', $worksheet);
        $this->assertStringNotContainsString('Excluded Employee', $worksheet);
        $this->assertStringNotContainsString('Payment Type', $worksheet);
    }

    public function test_journal_export_honors_filters_and_columns(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        JournalEntry::create(['entry_no' => 'JE-INCLUDED', 'entry_date' => '2026-07-05', 'memo_en' => 'Included memo', 'source_module' => 'manual', 'status' => 'posted', 'is_auto' => false]);
        JournalEntry::create(['entry_no' => 'JE-EXCLUDED', 'entry_date' => '2026-07-06', 'memo_en' => 'Excluded memo', 'source_module' => 'operations', 'status' => 'posted', 'is_auto' => true]);

        $response = $this->actingAs($admin)->get(route('accounting.journals.export', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'source_module' => 'manual',
            'columns' => ['date', 'entry_no', 'memo'],
        ]));

        $response->assertOk()->assertDownload('journal-entries-'.now()->format('Y-m-d').'.xlsx');
        $worksheet = $this->worksheet($response->baseResponse);
        $this->assertStringContainsString('JE-INCLUDED', $worksheet);
        $this->assertStringNotContainsString('JE-EXCLUDED', $worksheet);
        $this->assertStringNotContainsString('Debit', $worksheet);
    }

    public function test_audit_export_honors_filters_and_columns(): void
    {
        $admin = User::factory()->create(['name' => 'Audit Admin', 'role' => 'admin', 'is_active' => true]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'included_action', 'model_type' => User::class, 'model_id' => $admin->id, 'after' => ['name' => 'Included Value'], 'created_at' => '2026-07-05 10:00:00']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'excluded_action', 'model_type' => User::class, 'model_id' => $admin->id, 'after' => ['name' => 'Excluded Value'], 'created_at' => '2026-08-05 10:00:00']);

        $response = $this->actingAs($admin)->get(route('audit-logs.export', [
            'q' => 'included_action',
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'columns' => ['time', 'user', 'action'],
        ]));

        $response->assertOk()->assertDownload('audit-log-'.now()->format('Y-m-d').'.xlsx');
        $worksheet = $this->worksheet($response->baseResponse);
        $this->assertStringContainsString('Included Action', $worksheet);
        $this->assertStringNotContainsString('Excluded Action', $worksheet);
        $this->assertStringNotContainsString('IP Address', $worksheet);
    }

    private function worksheet(BinaryFileResponse $response): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()) === true);
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertIsString($worksheet);

        return $worksheet;
    }
}

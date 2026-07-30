<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RecycleIn;
use App\Models\User;
use App\Services\ApicoExcelImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class ExcelImportToleranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_linux_zip_mime_xlsx_upload_is_accepted_when_workbook_structure_is_valid(): void
    {
        $path = storage_path('app/test-linux-mime-upload.xlsx');
        $this->createWorkbook($path);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $upload = new UploadedFile($path, 'sales-sheet.xlsx', 'application/zip', null, true);

        $response = $this->actingAs($admin)->post(route('imports.sales-sheet.store'), [
            'sales_sheet' => $upload,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('recycle_ins', 1);
    }

    public function test_renamed_non_workbook_file_is_rejected_without_flushing_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        RecycleIn::create([
            'date' => '2026-07-01',
            'customer_id' => Customer::create(['name' => 'Existing', 'status' => 'active'])->id,
            'weight_kg' => 10,
            'rate_per_kg' => 0,
            'total_amount' => 0,
        ]);

        $response = $this->actingAs($admin)->post(route('imports.sales-sheet.store'), [
            'sales_sheet' => UploadedFile::fake()->createWithContent('not-excel.xlsx', 'not an Excel workbook'),
        ]);

        $response->assertSessionHasErrors('sales_sheet');
        $this->assertDatabaseCount('recycle_ins', 1);
    }

    public function test_invalid_row_is_reported_while_valid_rows_continue_importing(): void
    {
        $path = storage_path('app/test-import-with-typo.xlsx');
        $this->createWorkbook($path);

        try {
            $result = app(ApicoExcelImporter::class)->import($path);

            $this->assertSame(1, $result['totals']['recycle_in_rows']);
            $this->assertSame(2, $result['skipped_rows']);
            $this->assertCount(2, $result['issues']);

            $dateIssue = collect($result['issues'])->firstWhere('reason', 'Invalid date');
            $this->assertSame('Customer A', $dateIssue['customer']);
            $this->assertSame('Recycle In', $dateIssue['type']);
            $this->assertSame(5, $dateIssue['row']);
            $this->assertSame('Date', $dateIssue['field']);
            $this->assertSame('7/23/20226', $dateIssue['value']);
            $this->assertStringContainsString('C: 200', $dateIssue['transaction']);

            $amountIssue = collect($result['issues'])->firstWhere('reason', 'Missing required value');
            $this->assertSame('Payment', $amountIssue['type']);
            $this->assertSame('Amount', $amountIssue['field']);
            $this->assertSame('(blank)', $amountIssue['value']);

            $this->assertDatabaseCount('recycle_ins', 1);
            $this->assertDatabaseCount('payments', 0);
            $this->assertSame(100.0, (float) RecycleIn::firstOrFail()->weight_kg);
        } finally {
            @unlink($path);
        }
    }

    private function createWorkbook(string $path): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());

        foreach (range(1, 3) as $sheet) {
            $zip->addFromString("xl/worksheets/sheet{$sheet}.xml", $this->sheetXml([]));
        }

        $zip->addFromString('xl/worksheets/sheet4.xml', $this->sheetXml([
            1 => ['A' => 'Purchases'],
        ]));
        $zip->addFromString('xl/worksheets/sheet5.xml', $this->sheetXml([
            4 => ['A' => 'Valid row', 'B' => '2026-07-22', 'C' => 100, 'N' => '2026-07-22', 'P' => 'Cash'],
            5 => ['A' => 'Bad date row', 'B' => '7/23/20226', 'C' => 200],
        ]));
        $zip->addFromString('xl/worksheets/_rels/sheet5.xml.rels', $this->customerSheetRelationshipsXml());
        $zip->addFromString('xl/tables/table1.xml', $this->tableXml('Table1', 'A3:E5', 5));
        $zip->addFromString('xl/tables/table2.xml', $this->tableXml('Table2', 'G3:L3', 6));
        $zip->addFromString('xl/tables/table3.xml', $this->tableXml('Table3', 'N3:Q4', 4));
        $zip->addFromString('xl/tables/table4.xml', $this->tableXml('Table4', 'S3:X3', 6));
        $zip->close();
    }

    private function workbookXml(): string
    {
        $sheets = collect(range(1, 5))
            ->map(fn (int $sheet) => '<sheet name="'.($sheet === 5 ? 'Customer A' : "Sheet {$sheet}").'" sheetId="'.$sheet.'" r:id="rId'.$sheet.'"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        $relationships = collect(range(1, 5))
            ->map(fn (int $sheet) => '<Relationship Id="rId'.$sheet.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$sheet.'.xml"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships.'</Relationships>';
    }

    private function customerSheetRelationshipsXml(): string
    {
        $relationships = collect(range(1, 4))
            ->map(fn (int $table) => '<Relationship Id="rId'.$table.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/table" Target="../tables/table'.$table.'.xml"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships.'</Relationships>';
    }

    private function sheetXml(array $rows): string
    {
        $sheetRows = collect($rows)->map(function (array $cells, int $row) {
            $xml = collect($cells)->map(function (mixed $value, string $column) use ($row) {
                if (is_numeric($value)) {
                    return '<c r="'.$column.$row.'"><v>'.$value.'</v></c>';
                }

                return '<c r="'.$column.$row.'" t="inlineStr"><is><t>'
                    .htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    .'</t></is></c>';
            })->implode('');

            return '<row r="'.$row.'">'.$xml.'</row>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .$sheetRows.'</sheetData></worksheet>';
    }

    private function tableXml(string $name, string $reference, int $width): string
    {
        $columns = collect(range(1, $width))
            ->map(fn (int $column) => '<tableColumn id="'.$column.'" name="Column'.$column.'"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" name="'.$name.'" displayName="'.$name.'" ref="'.$reference.'">'
            .'<tableColumns count="'.$width.'">'.$columns.'</tableColumns></table>';
    }
}

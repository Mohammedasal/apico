<?php

namespace Tests\Feature;

use App\Models\MonthlyExpense;
use App\Models\ProductionDay;
use App\Services\ProductionExcelImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ProductionExcelImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_replaces_the_year_so_blank_workbook_days_do_not_remain_active(): void
    {
        ProductionDay::create(['date' => '2026-01-02', 'shift_one_kg' => 900, 'shift_two_kg' => 100]);
        MonthlyExpense::create(['year' => 2026, 'month' => 2, 'rent' => 500]);
        ProductionDay::create(['date' => '2025-01-02', 'shift_one_kg' => 700, 'shift_two_kg' => 0]);

        $path = $this->productionWorkbook([
            'B3' => 100,
            'C3' => 50,
            'B34' => 0.15,
            'B36' => 30,
            'B37' => 10,
        ]);

        $result = app(ProductionExcelImporter::class)->import($path);

        $this->assertSame(1, $result['production_days']);
        $this->assertSame(1, $result['monthly_expenses']);
        $this->assertSame(1, $result['replaced_production_days']);
        $this->assertSame(1, $result['replaced_monthly_expenses']);
        $this->assertDatabaseHas('production_days', ['date' => '2026-01-01 00:00:00', 'shift_one_kg' => 100, 'shift_two_kg' => 50]);
        $this->assertDatabaseMissing('production_days', ['date' => '2026-01-02 00:00:00']);
        $this->assertDatabaseHas('production_days', ['date' => '2025-01-02 00:00:00']);
        $this->assertDatabaseMissing('monthly_expenses', ['year' => 2026, 'month' => 2]);
    }

    private function productionWorkbook(array $cells): string
    {
        $path = tempnam(sys_get_temp_dir(), 'apico-production-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="2026" sheetId="1" r:id="rId1"/></sheets></workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>
XML);

        $rows = collect($cells)
            ->groupBy(fn ($value, string $reference) => (int) preg_replace('/\D/', '', $reference), preserveKeys: true)
            ->map(function ($rowCells, int $row) {
                $xml = collect($rowCells)->map(function ($value, string $reference) {
                    return sprintf('<c r="%s"><v>%s</v></c>', $reference, $value);
                })->implode('');

                return sprintf('<row r="%d">%s</row>', $row, $xml);
            })
            ->implode('');

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }
}

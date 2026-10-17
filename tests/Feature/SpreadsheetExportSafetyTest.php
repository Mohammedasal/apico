<?php

namespace Tests\Feature;

use App\Services\SimpleXlsxExporter;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetExportSafetyTest extends TestCase
{
    public function test_exported_user_values_cannot_become_spreadsheet_formulas(): void
    {
        $response = app(SimpleXlsxExporter::class)->download('safety.xlsx', ['Value'], [
            ['=2+2'],
            ['+SUM(1,2)'],
            ['-10+20'],
            ['@cmd'],
        ]);
        $path = $response->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertStringNotContainsString('<f>', $worksheet);
        $this->assertSame(5, substr_count($worksheet, 't="inlineStr"'));
        $this->assertStringContainsString('<t>=2+2</t>', $worksheet);
        $this->assertStringContainsString('<t>+SUM(1,2)</t>', $worksheet);
        $this->assertStringContainsString('<t>-10+20</t>', $worksheet);
        $this->assertStringContainsString('<t>@cmd</t>', $worksheet);
    }
}

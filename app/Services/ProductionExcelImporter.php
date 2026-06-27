<?php

namespace App\Services;

use App\Models\MonthlyExpense;
use App\Models\ProductionDay;
use Carbon\Carbon;
use ZipArchive;

class ProductionExcelImporter
{
    private array $sharedStrings = [];
    private array $sheets = [];
    private ZipArchive $zip;

    public function import(string $path): array
    {
        $this->open($path);

        try {
            $sheet = $this->productionSheet();
            $year = (int) $sheet['name'];
            $cells = $this->sheetCells($sheet['path']);
            $daysImported = $this->importProductionDays($cells, $year);
            $expensesImported = $this->importMonthlyExpenses($cells, $year);

            return [
                'year' => $year,
                'production_days' => $daysImported,
                'monthly_expenses' => $expensesImported,
            ];
        } finally {
            $this->zip->close();
        }
    }

    private function importProductionDays(array $cells, int $year): int
    {
        $count = 0;

        foreach ($this->monthBlocks() as $month => $block) {
            for ($row = 3; $row <= 33; $row++) {
                $day = $row - 2;

                if (! checkdate($month, $day, $year)) {
                    continue;
                }

                $shiftOne = $this->number($cells[$block['shift_one'].$row] ?? null);
                $shiftTwo = $this->number($cells[$block['shift_two'].$row] ?? null);

                if ($shiftOne <= 0 && $shiftTwo <= 0) {
                    continue;
                }

                $date = Carbon::create($year, $month, $day)->toDateString();
                $productionDay = ProductionDay::whereDate('date', $date)->first() ?? new ProductionDay(['date' => $date]);
                $productionDay->fill([
                    'shift_one_kg' => $shiftOne,
                    'shift_two_kg' => $shiftTwo,
                ])->save();

                $count++;
            }
        }

        return $count;
    }

    private function importMonthlyExpenses(array $cells, int $year): int
    {
        $count = 0;

        foreach ($this->monthBlocks() as $month => $block) {
            $column = $block['summary'];
            $productionTons = $this->number($cells[$column.'34'] ?? null);
            $averageIncome = $this->number($cells[$column.'36'] ?? null);
            $electricity = $this->number($cells[$column.'37'] ?? null);
            $salaries = $this->number($cells[$column.'39'] ?? null);
            $rent = $this->number($cells[$column.'41'] ?? null);
            $misc = $this->number($cells[$column.'43'] ?? null);
            $socialSecurity = $this->number($cells[$column.'45'] ?? null);
            $accounting = $this->number($cells[$column.'46'] ?? null);
            $transportation = $this->number($cells[$column.'47'] ?? null);

            if (($productionTons + $averageIncome + $electricity + $salaries + $rent + $misc + $socialSecurity + $accounting + $transportation) <= 0) {
                continue;
            }

            MonthlyExpense::updateOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'average_income_per_ton' => $productionTons > 0 ? round($averageIncome / $productionTons, 3) : 0,
                    'electricity_bill' => $electricity,
                    'total_salaries' => $salaries,
                    'rent' => $rent,
                    'misc' => $misc,
                    'social_security' => $socialSecurity,
                    'other_expenses' => round($accounting + $transportation, 3),
                    'notes' => 'Imported from production workbook.',
                ]
            );

            $count++;
        }

        return $count;
    }

    private function open(string $path): void
    {
        $this->zip = new ZipArchive();
        $this->zip->open($path);
        $this->sharedStrings = $this->sharedStrings();
        $this->sheets = $this->sheets();
    }

    private function productionSheet(): array
    {
        foreach ($this->sheets as $sheet) {
            if (preg_match('/^\d{4}$/', $sheet['name'])) {
                return $sheet;
            }
        }

        throw new \RuntimeException('Production year sheet was not found.');
    }

    private function monthBlocks(): array
    {
        return [
            1 => ['shift_one' => 'B', 'shift_two' => 'C', 'summary' => 'B'],
            2 => ['shift_one' => 'G', 'shift_two' => 'H', 'summary' => 'F'],
            3 => ['shift_one' => 'L', 'shift_two' => 'M', 'summary' => 'K'],
            4 => ['shift_one' => 'Q', 'shift_two' => 'R', 'summary' => 'P'],
            5 => ['shift_one' => 'V', 'shift_two' => 'W', 'summary' => 'U'],
            6 => ['shift_one' => 'AA', 'shift_two' => 'AB', 'summary' => 'Z'],
            7 => ['shift_one' => 'AF', 'shift_two' => 'AG', 'summary' => 'AE'],
            8 => ['shift_one' => 'AK', 'shift_two' => 'AL', 'summary' => 'AJ'],
            9 => ['shift_one' => 'AP', 'shift_two' => 'AQ', 'summary' => 'AO'],
            10 => ['shift_one' => 'AU', 'shift_two' => 'AV', 'summary' => 'AT'],
            11 => ['shift_one' => 'AZ', 'shift_two' => 'BA', 'summary' => 'AY'],
            12 => ['shift_one' => 'BE', 'shift_two' => 'BF', 'summary' => 'BD'],
        ];
    }

    private function sheets(): array
    {
        $workbook = simplexml_load_string($this->zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string($this->zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relMap = [];

        foreach ($rels->Relationship as $rel) {
            $relMap[(string) $rel['Id']] = 'xl/'.(string) $rel['Target'];
        }

        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheets = [];

        foreach ($workbook->sheets->sheet as $sheet) {
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sheets[] = [
                'name' => (string) $sheet['name'],
                'path' => $relMap[(string) $attrs['id']],
            ];
        }

        return $sheets;
    }

    private function sharedStrings(): array
    {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];
        $shared = simplexml_load_string($xml);

        foreach ($shared->si as $item) {
            $text = '';

            if (isset($item->t)) {
                $text = (string) $item->t;
            } else {
                foreach ($item->r as $run) {
                    $text .= (string) $run->t;
                }
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function sheetCells(string $path): array
    {
        $xml = simplexml_load_string($this->zip->getFromName($path));
        $cells = [];

        foreach ($xml->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's' && $value !== '') {
                    $value = $this->sharedStrings[(int) $value] ?? $value;
                } elseif ($type === 'inlineStr') {
                    $value = (string) $cell->is->t;
                }

                $cells[$ref] = $value;
            }
        }

        return $cells;
    }

    private function number(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 3);
    }
}

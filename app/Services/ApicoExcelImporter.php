<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use ZipArchive;

class ApicoExcelImporter
{
    private array $sharedStrings = [];
    private array $sheets = [];
    private ZipArchive $zip;

    public function preview(string $path): array
    {
        $this->open($path);

        try {
            $purchaseRows = $this->purchaseRows();
            $customers = collect($this->sheets)
                ->slice(4)
                ->reject(fn (array $sheet) => trim($sheet['name']) === 'Main Template')
                ->map(fn (array $sheet) => $this->customerPreview($sheet))
                ->values();

            return [
                'purchases' => [
                    'rows' => count($purchaseRows),
                    'weight_kg' => round(array_sum(array_column($purchaseRows, 'weight_kg')), 3),
                    'total_cost' => round(array_sum(array_column($purchaseRows, 'total_cost')), 3),
                ],
                'customers' => $customers,
                'totals' => [
                    'customers' => $customers->count(),
                    'recycle_in_rows' => $customers->sum('counts.recycle_in'),
                    'recycle_out_rows' => $customers->sum('counts.recycle_out'),
                    'payment_rows' => $customers->sum('counts.payments'),
                    'stock_sale_rows' => $customers->sum('counts.stock_sales'),
                    'recycle_in_kg' => round($customers->sum('imported.recycle_in_kg'), 3),
                    'recycle_out_kg' => round($customers->sum('imported.recycle_out_kg'), 3),
                    'recycle_out_amount' => round($customers->sum('imported.recycle_out_amount'), 3),
                    'payments' => round($customers->sum('imported.payments'), 3),
                    'stock_sales_amount' => round($customers->sum('imported.stock_sales_amount'), 3),
                ],
            ];
        } finally {
            $this->zip->close();
        }
    }

    public function import(string $path): array
    {
        $preview = $this->preview($path);
        $this->open($path);

        try {
            foreach ($this->purchaseRows() as $row) {
                $supplier = Supplier::firstOrCreate(
                    ['name' => $row['supplier_name']],
                    ['status' => 'active']
                );
                $row['supplier_id'] = $supplier->id;
                StockPurchase::create($row);
            }

            foreach (array_slice($this->sheets, 4) as $sheet) {
                if (trim($sheet['name']) === 'Main Template') {
                    continue;
                }

                $customer = Customer::firstOrCreate(['name' => $sheet['name']], ['status' => 'active']);
                $tables = $this->customerTables($sheet);

                foreach ($tables['recycle_ins'] as $row) {
                    RecycleIn::create($row + ['customer_id' => $customer->id]);
                }

                foreach ($tables['recycle_outs'] as $row) {
                    RecycleOut::create($row + ['customer_id' => $customer->id]);
                }

                foreach ($tables['payments'] as $row) {
                    Payment::create($row + ['customer_id' => $customer->id]);
                }

                foreach ($tables['stock_sales'] as $row) {
                    StockSale::create($row + ['customer_id' => $customer->id]);
                }
            }

            return $preview;
        } finally {
            $this->zip->close();
        }
    }

    public function dateIssues(string $path): array
    {
        $this->open($path);

        try {
            $issues = [];

            foreach ($this->purchaseRows() as $row) {
                if ($this->hasDateIssue($row['date'])) {
                    $issues[] = [
                        'customer' => 'Purchases sheet',
                        'type' => 'Purchase',
                        'date' => $this->issueDateLabel($row['date']),
                        'transaction' => number_format((float) $row['weight_kg'], 3).' kg x '.number_format((float) $row['cost_per_kg'], 3).' = '.number_format((float) $row['total_cost'], 3).' JOD',
                    ];
                }
            }

            foreach (array_slice($this->sheets, 4) as $sheet) {
                if (trim($sheet['name']) === 'Main Template') {
                    continue;
                }

                $tables = $this->customerTables($sheet);

                foreach ($tables['recycle_ins'] as $row) {
                    if ($this->hasDateIssue($row['date'])) {
                        $issues[] = [
                            'customer' => $sheet['name'],
                            'type' => 'Recycle In',
                            'date' => $this->issueDateLabel($row['date']),
                            'transaction' => number_format((float) $row['weight_kg'], 3).' kg'.($row['notes'] ? ' | '.$row['notes'] : ''),
                        ];
                    }
                }

                foreach ($tables['recycle_outs'] as $row) {
                    if ($this->hasDateIssue($row['date'])) {
                        $issues[] = [
                            'customer' => $sheet['name'],
                            'type' => 'Recycle Out',
                            'date' => $this->issueDateLabel($row['date']),
                            'transaction' => number_format((float) $row['recycled_out_kg'], 3).' recycled, '.number_format((float) $row['waste_kg'], 3).' waste, '.number_format((float) $row['non_recycled_kg'], 3).' non-recycled | '.number_format((float) $row['rate_per_kg'], 3).' = '.number_format((float) $row['total_amount'], 3).' JOD'.($row['notes'] ? ' | '.$row['notes'] : ''),
                        ];
                    }
                }

                foreach ($tables['payments'] as $row) {
                    if ($this->hasDateIssue($row['date'])) {
                        $issues[] = [
                            'customer' => $sheet['name'],
                            'type' => 'Payment',
                            'date' => $this->issueDateLabel($row['date']),
                            'transaction' => number_format((float) $row['amount'], 3).' JOD'.($row['payment_method'] ? ' | '.$row['payment_method'] : '').($row['notes'] ? ' | '.$row['notes'] : ''),
                        ];
                    }
                }

                foreach ($tables['stock_sales'] as $row) {
                    if ($this->hasDateIssue($row['date'])) {
                        $issues[] = [
                            'customer' => $sheet['name'],
                            'type' => 'Stock Sale',
                            'date' => $this->issueDateLabel($row['date']),
                            'transaction' => number_format((float) $row['weight_kg'], 3).' kg x '.number_format((float) $row['selling_price_per_kg'], 3).' = '.number_format((float) $row['sales_value'], 3).' JOD'.($row['notes'] ? ' | '.$row['notes'] : ''),
                        ];
                    }
                }
            }

            return $issues;
        } finally {
            $this->zip->close();
        }
    }

    private function customerPreview(array $sheet): array
    {
        $tables = $this->customerTables($sheet);
        $summary = $this->summaryRow($sheet);
        $imported = [
            'recycle_in_kg' => round(array_sum(array_column($tables['recycle_ins'], 'weight_kg')), 3),
            'recycle_out_kg' => round(array_sum(array_column($tables['recycle_outs'], 'weight_kg')), 3),
            'recycle_out_amount' => round(array_sum(array_column($tables['recycle_outs'], 'total_amount')), 3),
            'payments' => round(array_sum(array_column($tables['payments'], 'amount')), 3),
            'stock_sales_kg' => round(array_sum(array_column($tables['stock_sales'], 'weight_kg')), 3),
            'stock_sales_amount' => round(array_sum(array_column($tables['stock_sales'], 'sales_value')), 3),
        ];

        return [
            'name' => $sheet['name'],
            'counts' => [
                'recycle_in' => count($tables['recycle_ins']),
                'recycle_out' => count($tables['recycle_outs']),
                'payments' => count($tables['payments']),
                'stock_sales' => count($tables['stock_sales']),
            ],
            'summary' => $summary,
            'imported' => $imported,
            'diff' => [
                'recycle_in_kg' => round(($summary['recycle_in_kg'] ?? 0) - $imported['recycle_in_kg'], 3),
                'recycle_out_kg' => round(($summary['recycle_out_kg'] ?? 0) - $imported['recycle_out_kg'], 3),
                'recycle_out_amount' => round(($summary['recycle_out_amount'] ?? 0) - $imported['recycle_out_amount'], 3),
                'payments' => round(($summary['payments'] ?? 0) - $imported['payments'], 3),
                'stock_sales_kg' => round(($summary['stock_sales_kg'] ?? 0) - $imported['stock_sales_kg'], 3),
                'stock_sales_amount' => round(($summary['stock_sales_amount'] ?? 0) - $imported['stock_sales_amount'], 3),
            ],
        ];
    }

    private function purchaseRows(): array
    {
        $rows = [];
        $cells = $this->sheetCells($this->sheets[3]['path']);
        $purchaseTable = collect($this->sheetTables($this->sheets[3]['path']))
            ->first(fn (array $table) => $table['name'] === 'Table18'
                || $table['display_name'] === 'Table18'
                || in_array('supplier name', array_map('strtolower', $table['headers']), true));

        $startRow = $purchaseTable['start_row'] ?? 1;
        $endRow = $purchaseTable['end_row'] ?? max(array_keys($cells));
        $columns = $purchaseTable
            ? $this->columnsFrom($purchaseTable['start_col'], $purchaseTable['width'])
            : ['B', 'C', 'D', 'E'];
        $supplierColumn = $purchaseTable
            ? $this->columnForHeader($purchaseTable, 'supplier name')
            : null;
        [$dateColumn, $weightColumn, $rateColumn, $totalColumn] = array_pad(array_slice($columns, 0, 4), 4, null);

        foreach (range($startRow + 1, $endRow) as $rowNumber) {
            $row = $cells[$rowNumber] ?? [];

            if (! $dateColumn || ! $weightColumn || ! $this->has($row, $dateColumn) || ! $this->has($row, $weightColumn)) {
                continue;
            }

            $weight = $this->number($row[$weightColumn] ?? null);

            if ($weight == 0.0) {
                continue;
            }

            $rate = $this->number($rateColumn ? ($row[$rateColumn] ?? null) : null);
            $supplierName = trim((string) ($supplierColumn ? ($row[$supplierColumn] ?? '') : ''));

            $rows[] = [
                'date' => $this->date($row[$dateColumn]),
                'supplier_name' => $supplierName !== '' ? $supplierName : 'Excel Import',
                'material_id' => null,
                'weight_kg' => $weight,
                'cost_per_kg' => $rate,
                'total_cost' => $this->number($totalColumn ? ($row[$totalColumn] ?? $weight * $rate) : $weight * $rate),
                'notes' => 'Imported from purchases sheet',
            ];
        }

        return $rows;
    }

    private function customerTables(array $sheet): array
    {
        $recycleIns = $recycleOuts = $payments = $stockSales = [];
        $cells = $this->sheetCells($sheet['path']);
        $tables = collect($this->sheetTables($sheet['path']))
            ->sortBy('start_col_number')
            ->values();

        foreach ($tables as $table) {
            $isReceiveTable = $table['table_index'] === 1;
            $isRecycleOutTable = $table['table_index'] === 2;
            $isPaymentTable = $table['table_index'] === 3;
            $isStockSaleTable = $table['table_index'] === 4;

            foreach (range($table['start_row'] + 1, $table['end_row']) as $rowNumber) {
                $row = $cells[$rowNumber] ?? [];
                $columns = $this->columnsFrom($table['start_col'], $table['width']);

                if ($isReceiveTable && $this->has($row, $columns[2])) {
                    $recycleIns[] = [
                        'date' => $this->dateOrDefault($row[$columns[1]] ?? null),
                        'material_id' => null,
                        'weight_kg' => $this->number($row[$columns[2]]),
                        'rate_per_kg' => 0,
                        'total_amount' => 0,
                        'notes' => $this->notes([$row[$columns[0]] ?? null, $row[$columns[3]] ?? null, $row[$columns[4]] ?? null]),
                    ];
                }

                if ($isPaymentTable && $this->has($row, $columns[1])) {
                    $paymentNoteColumn = $columns[3] ?? null;
                    $dateValue = $row[$columns[0]] ?? null;
                    $methodValue = $row[$columns[2]] ?? null;
                    $noteValue = $paymentNoteColumn ? ($row[$paymentNoteColumn] ?? null) : null;
                    $chequeDueDate = null;

                    if (! $this->hasValue($dateValue) && is_numeric($methodValue)) {
                        $dateValue = $methodValue;
                        $methodValue = null;
                    }

                    if ($this->hasValue($dateValue) && is_numeric($methodValue) && (float) $methodValue > 30000) {
                        $chequeDueDate = $this->date($methodValue);
                        $methodValue = 'Cheque';
                    }

                    $chequeDueDate ??= $this->chequeDueDate($dateValue, $methodValue, $noteValue);
                    $paymentType = $chequeDueDate ? 'cheque' : $this->paymentType($methodValue, $noteValue);

                    $payments[] = [
                        'date' => $this->dateOrDefault($dateValue),
                        'amount' => $this->number($row[$columns[1]]),
                        'payment_type' => $paymentType,
                        'payment_method' => $this->text($methodValue),
                        'reference_no' => null,
                        'bank_name' => null,
                        'cheque_due_date' => $paymentType === 'cheque' ? $chequeDueDate : null,
                        'cheque_status' => 'pending',
                        'notes' => $this->notes([
                            $this->hasValue($dateValue) ? null : 'Missing payment date in Excel',
                            $noteValue,
                        ]),
                    ];
                }

                if (($isRecycleOutTable || $isStockSaleTable) && $this->has($row, $columns[2])) {
                    $weight = $this->number($row[$columns[2]]);
                    $rate = $this->number($row[$columns[3]] ?? null);
                    $amount = $this->number($row[$columns[4]] ?? ($weight * $rate));
                    $noteColumn = $columns[5] ?? null;
                    $dateValue = $row[$columns[1]] ?? null;

                    if ($isRecycleOutTable) {
                        $recycleOuts[] = [
                            'date' => $this->dateOrDefault($dateValue),
                            'material_id' => null,
                            'weight_kg' => $weight,
                            'recycled_out_kg' => $rate > 0 ? $weight : 0,
                            'waste_kg' => $rate > 0 ? 0 : $weight,
                            'non_recycled_kg' => 0,
                            'rate_per_kg' => $rate,
                            'total_amount' => $amount,
                            'notes' => $this->notes([
                                $this->hasValue($dateValue) ? null : 'Missing recycle-out date in Excel',
                                $row[$columns[0]] ?? null,
                                $noteColumn ? ($row[$noteColumn] ?? null) : null,
                            ]),
                        ];
                    }

                    if ($isStockSaleTable) {
                        $stockSales[] = [
                            'date' => $this->dateOrDefault($dateValue),
                            'material_id' => null,
                            'weight_kg' => $weight,
                            'selling_price_per_kg' => $rate,
                            'sales_value' => $amount,
                            'purchase_cost_per_kg' => 0,
                            'granulation_cost_per_kg' => 0,
                            'net_profit' => $amount,
                            'notes' => $this->notes([
                                $this->hasValue($dateValue) ? null : 'Missing stock-sale date in Excel',
                                $row[$columns[0]] ?? null,
                                $noteColumn ? ($row[$noteColumn] ?? null) : null,
                            ]),
                        ];
                    }
                }
            }
        }

        return [
            'recycle_ins' => $recycleIns,
            'recycle_outs' => $recycleOuts,
            'payments' => $payments,
            'stock_sales' => $stockSales,
        ];
    }

    private function sheetTables(string $sheetPath): array
    {
        $sheetName = pathinfo($sheetPath, PATHINFO_FILENAME);
        $relsPath = dirname($sheetPath).'/_rels/'.$sheetName.'.xml.rels';
        $rels = $this->xml($relsPath);

        if (! $rels) {
            return [];
        }

        $tables = [];

        foreach ($rels->Relationship as $relationship) {
            $target = (string) $relationship['Target'];

            if (! str_contains($target, 'tables/')) {
                continue;
            }

            $tablePath = 'xl/'.str_replace('../', '', $target);
            $tableXml = $this->xml($tablePath);
            [$start, $end] = explode(':', (string) $tableXml['ref']);
            [$startCol, $startRow] = $this->splitCellRef($start);
            [, $endRow] = $this->splitCellRef($end);
            $width = $this->columnNumber($this->splitCellRef($end)[0]) - $this->columnNumber($startCol) + 1;
            $headers = [];

            foreach ($tableXml->tableColumns->tableColumn ?? [] as $column) {
                $headers[] = (string) $column['name'];
            }

            $tables[] = [
                'name' => (string) $tableXml['name'],
                'display_name' => (string) ($tableXml['displayName'] ?? $tableXml['name']),
                'ref' => (string) $tableXml['ref'],
                'headers' => $headers,
                'width' => $width,
                'start_col' => $startCol,
                'start_col_number' => $this->columnNumber($startCol),
                'start_row' => $startRow,
                'end_row' => $endRow,
                'table_index' => null,
            ];
        }

        return collect($tables)
            ->sortBy('start_col_number')
            ->values()
            ->map(function (array $table, int $index) {
                $table['table_index'] = $index + 1;
                return $table;
            })
            ->all();
    }

    private function columnForHeader(array $table, string $header): ?string
    {
        $headers = array_map(fn (string $value) => strtolower(trim($value)), $table['headers']);
        $index = array_search(strtolower(trim($header)), $headers, true);

        if ($index === false) {
            return null;
        }

        return $this->columnsFrom($table['start_col'], $table['width'])[$index] ?? null;
    }

    private function summaryRow(array $sheet): array
    {
        $row = $this->sheetCells($sheet['path'])[2] ?? [];

        return [
            'recycle_in_kg' => $this->number($row['A'] ?? null),
            'recycle_out_kg' => $this->number($row['B'] ?? null),
            'weight_difference_kg' => $this->number($row['C'] ?? null),
            'recycle_out_amount' => $this->number($row['D'] ?? null),
            'stock_sales_amount' => $this->number($row['E'] ?? null),
            'payments' => $this->number($row['F'] ?? null),
            'receivable' => $this->number($row['G'] ?? null),
            'stock_sales_kg' => $this->number($row['H'] ?? null),
        ];
    }

    private function open(string $path): void
    {
        $this->zip = new ZipArchive();
        $this->zip->open($path);
        $this->sharedStrings = $this->loadSharedStrings();
        $this->sheets = $this->loadSheets();
    }

    private function loadSharedStrings(): array
    {
        $xml = $this->xml('xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $node) {
            $strings[] = $this->xmlText($node);
        }

        return $strings;
    }

    private function loadSheets(): array
    {
        $workbook = $this->xml('xl/workbook.xml');
        $relationships = $this->xml('xl/_rels/workbook.xml.rels');
        $targets = [];

        foreach ($relationships->Relationship as $relationship) {
            $targets[(string) $relationship['Id']] = 'xl/'.(string) $relationship['Target'];
        }

        $sheets = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes('r', true);
            $rid = (string) $attributes['id'];
            $sheets[] = [
                'name' => (string) $sheet['name'],
                'path' => $targets[$rid],
            ];
        }

        return $sheets;
    }

    private function sheetCells(string $path): array
    {
        $xml = $this->xml($path);
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $rowNumber = (int) $row['r'];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $ref);
                $rows[$rowNumber][$column] = $this->cellValue($cell);
            }
        }

        return $rows;
    }

    private function cellValue(\SimpleXMLElement $cell): mixed
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            return $this->sharedStrings[(int) $cell->v] ?? null;
        }

        if ($type === 'inlineStr') {
            return $this->xmlText($cell->is);
        }

        return isset($cell->v) ? (string) $cell->v : null;
    }

    private function splitCellRef(string $ref): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $matches);

        return [$matches[1], (int) $matches[2]];
    }

    private function columnsFrom(string $startColumn, int $count): array
    {
        $start = $this->columnNumber($startColumn);

        return collect(range($start, $start + $count - 1))
            ->map(fn (int $number) => $this->columnName($number))
            ->all();
    }

    private function columnNumber(string $column): int
    {
        $number = 0;

        foreach (str_split($column) as $letter) {
            $number = $number * 26 + ord($letter) - ord('A') + 1;
        }

        return $number;
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(($number % 26) + ord('A')).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xml(string $path): ?\SimpleXMLElement
    {
        $contents = $this->zip->getFromName($path);

        return $contents === false ? null : simplexml_load_string($contents);
    }

    private function has(array $row, string $column): bool
    {
        return isset($row[$column]) && trim((string) $row[$column]) !== '';
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 3);
    }

    private function date(mixed $value): string
    {
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    private function dateOrDefault(mixed $value): string
    {
        if (! $this->hasValue($value)) {
            return '1900-01-01';
        }

        return $this->date($value);
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    private function notes(array $parts): ?string
    {
        return collect($parts)->map(fn ($part) => $this->text($part))->filter()->implode(' | ') ?: null;
    }

    private function hasDateIssue(string $date): bool
    {
        return $date === '1900-01-01' || Carbon::parse($date)->year === 2025;
    }

    private function issueDateLabel(string $date): string
    {
        return $date === '1900-01-01' ? 'No date' : $date;
    }

    private function xmlText(\SimpleXMLElement $node): string
    {
        $dom = dom_import_simplexml($node);
        $text = '';

        if ($dom) {
            foreach ($dom->getElementsByTagName('t') as $textNode) {
                $text .= $textNode->textContent;
            }
        }

        return trim($text !== '' ? $text : (string) $node);
    }

    private function paymentType(mixed ...$values): string
    {
        $text = mb_strtolower(collect($values)->map(fn ($value) => $this->text($value))->filter()->implode(' '));

        return str_contains($text, 'شيك') || str_contains($text, 'cheque') || str_contains($text, 'check')
            ? 'cheque'
            : 'cash';
    }

    private function chequeDueDate(mixed $paymentDate, mixed ...$values): ?string
    {
        $text = collect($values)->map(fn ($value) => $this->text($value))->filter()->implode(' ');

        if (! preg_match('/(?:شيك|cheque|check)\\s*(?:ب?تاريخ|يتاريخ|ب|date)?\\s*(\\d{1,2})[\\/\\-](\\d{1,2})(?:[\\/\\-](\\d{2,4}))?/iu', $text, $matches)) {
            return null;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];

        if ($month > 12 && $day <= 12) {
            [$day, $month] = [$month, $day];
        }

        $year = isset($matches[3]) && $matches[3] !== ''
            ? (int) $matches[3]
            : Carbon::parse($this->dateOrDefault($paymentDate))->year;

        if ($year < 100) {
            $year += 2000;
        }

        try {
            return Carbon::createSafe($year, $month, $day)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

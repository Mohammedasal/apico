<?php

use App\Models\Customer;
use App\Models\ChequeOut;
use App\Models\MonthlyExpense;
use App\Models\Payment;
use App\Models\ProductionDay;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Services\ApicoExcelImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('apico:backup-database', function () {
    $database = database_path('database.sqlite');

    if (! File::exists($database)) {
        $this->error('SQLite database file was not found.');

        return self::FAILURE;
    }

    $directory = storage_path('app/backups');
    File::ensureDirectoryExists($directory);

    $target = $directory.'/apico-'.now()->format('Y-m-d-His').'.sqlite';
    File::copy($database, $target);

    $this->info('Database backup created: '.$target);

    return self::SUCCESS;
})->purpose('Create a timestamped SQLite database backup');

Artisan::command('apico:date-issues {path}', function (ApicoExcelImporter $importer) {
    $path = (string) $this->argument('path');

    if (! File::exists($path)) {
        $this->error('Excel file was not found: '.$path);

        return self::FAILURE;
    }

    $issues = $importer->dateIssues($path);

    if ($issues === []) {
        $this->info('No 2025 or missing-date transactions found.');

        return self::SUCCESS;
    }

    $this->warn('Transactions dated 2025 or missing a date:');
    $this->table(
        ['Client', 'Type', 'Date', 'Transaction'],
        collect($issues)->map(fn (array $issue) => [
            $issue['customer'],
            $issue['type'],
            $issue['date'],
            $issue['transaction'],
        ])->all()
    );

    return self::SUCCESS;
})->purpose('List imported Excel transactions dated 2025 or missing a date');

Artisan::command('apico:import-production {path} {--year=2026}', function () {
    $path = (string) $this->argument('path');
    $year = (int) $this->option('year');

    if (! File::exists($path)) {
        $this->error('Excel file was not found: '.$path);

        return self::FAILURE;
    }

    $zip = new ZipArchive();
    $zip->open($path);
    $xml = fn (string $name) => simplexml_load_string($zip->getFromName($name));
    $workbook = $xml('xl/workbook.xml');
    $relationships = $xml('xl/_rels/workbook.xml.rels');
    $targets = [];

    foreach ($relationships->Relationship as $relationship) {
        $targets[(string) $relationship['Id']] = 'xl/'.(string) $relationship['Target'];
    }

    $sheets = [];
    foreach ($workbook->sheets->sheet as $sheet) {
        $attributes = $sheet->attributes('r', true);
        $sheets[] = ['name' => (string) $sheet['name'], 'path' => $targets[(string) $attributes['id']]];
    }

    $cells = function (string $sheetPath) use ($xml) {
        $sheet = $xml($sheetPath);
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowNumber = (int) $row['r'];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $ref);
                $rows[$rowNumber][$column] = isset($cell->v) ? (string) $cell->v : null;
            }
        }
        return $rows;
    };

    $productionRows = $cells($sheets[0]['path']);
    $groups = [
        1 => ['B', 'C', 'B'], 2 => ['G', 'H', 'F'], 3 => ['L', 'M', 'K'], 4 => ['Q', 'R', 'P'],
        5 => ['V', 'W', 'U'], 6 => ['AA', 'AB', 'Z'], 7 => ['AF', 'AG', 'AE'], 8 => ['AK', 'AL', 'AJ'],
        9 => ['AP', 'AQ', 'AO'], 10 => ['AU', 'AV', 'AT'], 11 => ['AZ', 'BA', 'AY'], 12 => ['BE', 'BF', 'BD'],
    ];
    $productionCount = 0;
    $expenseCount = 0;

    foreach ($groups as $month => [$shiftOneColumn, $shiftTwoColumn, $summaryColumn]) {
        $daysInMonth = Carbon\Carbon::create($year, $month, 1)->daysInMonth;
        foreach (range(1, $daysInMonth) as $day) {
            $rowNumber = $day + 2;
            $shiftOne = round((float) ($productionRows[$rowNumber][$shiftOneColumn] ?? 0), 3);
            $shiftTwo = round((float) ($productionRows[$rowNumber][$shiftTwoColumn] ?? 0), 3);

            if ($shiftOne === 0.0 && $shiftTwo === 0.0) {
                continue;
            }

            $date = Carbon\Carbon::create($year, $month, $day)->toDateString();
            $productionDay = ProductionDay::whereDate('date', $date)->first() ?? new ProductionDay(['date' => $date]);
            $productionDay->fill([
                'shift_one_kg' => $shiftOne,
                'shift_two_kg' => $shiftTwo,
                'notes' => 'Imported from production workbook',
            ])->save();
            $productionCount++;
        }

        MonthlyExpense::updateOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'average_income_per_ton' => ($productionRows[34][$summaryColumn] ?? 0) > 0
                    ? round((float) ($productionRows[36][$summaryColumn] ?? 0) / (float) $productionRows[34][$summaryColumn], 3)
                    : 140,
                'electricity_bill' => round((float) ($productionRows[37][$summaryColumn] ?? 0), 3),
                'total_salaries' => round((float) ($productionRows[39][$summaryColumn] ?? 0), 3),
                'rent' => round((float) ($productionRows[41][$summaryColumn] ?? 0), 3),
                'misc' => round((float) ($productionRows[43][$summaryColumn] ?? 0), 3),
                'social_security' => round((float) ($productionRows[45][$summaryColumn] ?? 0), 3),
                'other_expenses' => round((float) ($productionRows[46][$summaryColumn] ?? 0) + (float) ($productionRows[47][$summaryColumn] ?? 0), 3),
                'notes' => 'Imported from production workbook',
            ]
        );
        $expenseCount++;
    }

    $chequeRows = $cells($sheets[1]['path']);
    $chequeCount = 0;

    foreach ($chequeRows as $rowNumber => $row) {
        $amount = round((float) ($row['E'] ?? 0), 3);
        $dateSerial = $row['F'] ?? null;

        if ($rowNumber < 3 || $amount <= 0 || ! is_numeric($dateSerial)) {
            continue;
        }

        ChequeOut::updateOrCreate(
            ['cheque_number' => 'PROD-'.$rowNumber, 'amount' => $amount],
            [
                'payee' => 'Excel Import',
                'due_date' => Carbon\Carbon::create(1899, 12, 30)->addDays((int) $dateSerial)->toDateString(),
                'status' => filled($row['H'] ?? null) ? 'cleared' : 'pending',
                'notes' => 'Imported from APICO Production row '.$rowNumber,
            ]
        );
        $chequeCount++;
    }

    $zip->close();
    $this->info("Imported {$productionCount} production days, {$expenseCount} monthly expense rows, and {$chequeCount} outgoing cheques.");

    return self::SUCCESS;
})->purpose('Import APICO production daily rows and outgoing cheque monitor data');

Artisan::command('apico:import-excel {path} {--commit : Import the workbook into the database} {--wipe : Delete imported APICO transaction data before import}', function (ApicoExcelImporter $importer) {
    $path = (string) $this->argument('path');

    if (! File::exists($path)) {
        $this->error('Excel file was not found: '.$path);

        return self::FAILURE;
    }

    if ($this->option('wipe')) {
        if (! $this->option('commit')) {
            $this->warn('--wipe only runs together with --commit.');
        } else {
            DB::transaction(function () {
                StockSale::query()->delete();
                StockPurchase::query()->delete();
                Payment::query()->delete();
                RecycleOut::query()->delete();
                RecycleIn::query()->delete();
                Customer::query()->where('name', '!=', 'Sample Customer')->delete();
            });
            $this->info('Existing APICO imported records were deleted.');
        }
    }

    $result = $this->option('commit')
        ? $importer->import($path)
        : $importer->preview($path);

    $this->info($this->option('commit') ? 'Import completed.' : 'Preview only. Add --commit to import.');
    $this->newLine();
    $this->table(['Metric', 'Value'], [
        ['Customers', $result['totals']['customers']],
        ['Purchases rows', $result['purchases']['rows']],
        ['Purchases kg', number_format($result['purchases']['weight_kg'], 3)],
        ['Purchases JOD', number_format($result['purchases']['total_cost'], 3)],
        ['Recycle in rows', $result['totals']['recycle_in_rows']],
        ['Recycle out rows', $result['totals']['recycle_out_rows']],
        ['Payment rows', $result['totals']['payment_rows']],
        ['Stock sale rows', $result['totals']['stock_sale_rows']],
        ['Recycle in kg', number_format($result['totals']['recycle_in_kg'], 3)],
        ['Recycle out kg', number_format($result['totals']['recycle_out_kg'], 3)],
        ['Recycle out JOD', number_format($result['totals']['recycle_out_amount'], 3)],
        ['Payments JOD', number_format($result['totals']['payments'], 3)],
        ['Stock sales JOD', number_format($result['totals']['stock_sales_amount'], 3)],
    ]);

    $mismatches = $result['customers']
        ->filter(fn ($customer) => collect($customer['diff'])->contains(fn ($diff) => abs((float) $diff) > 0.01))
        ->map(fn ($customer) => [
            $customer['name'],
            number_format($customer['diff']['recycle_in_kg'], 3),
            number_format($customer['diff']['recycle_out_kg'], 3),
            number_format($customer['diff']['recycle_out_amount'], 3),
            number_format($customer['diff']['payments'], 3),
            number_format($customer['diff']['stock_sales_kg'], 3),
            number_format($customer['diff']['stock_sales_amount'], 3),
        ])
        ->values();

    if ($mismatches->isEmpty()) {
        $this->info('All customer summary totals match the parsed transaction tables.');
    } else {
        $this->warn('Customer total differences detected. Positive means Excel summary is higher than parsed rows.');
        $this->table(
            ['Customer', 'In kg', 'Out kg', 'Out JOD', 'Payments', 'Stock kg', 'Stock JOD'],
            $mismatches->all()
        );
    }

    return self::SUCCESS;
})->purpose('Preview or import APICO Excel workbook data');

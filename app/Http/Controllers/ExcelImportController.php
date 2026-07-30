<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Services\ApicoExcelImporter;
use App\Services\ProductionExcelImporter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ExcelImportController extends Controller
{
    public function store(Request $request, ApicoExcelImporter $importer)
    {
        $data = $request->validate([
            'sales_sheet' => $this->excelWorkbookRules(),
        ]);

        $path = $data['sales_sheet']->storeAs(
            'imports',
            'apico-sales-'.now()->format('Y-m-d-His').'.xlsx'
        );
        $fullPath = Storage::path($path);
        $this->backupDatabase();

        $result = DB::transaction(function () use ($importer, $fullPath, $request) {
            $this->wipeImportedData();
            $userId = $request->user()->id;

            $result = $importer->import($fullPath);

            foreach ([StockSale::class, StockPurchase::class, Payment::class, RecycleOut::class, RecycleIn::class] as $model) {
                $model::query()->update(['created_by' => $userId, 'updated_by' => null]);
            }

            return $result;
        });
        $dateIssues = $importer->dateIssues($fullPath);
        $skippedRows = (int) ($result['skipped_rows'] ?? 0);
        $status = 'Sales sheet imported. Existing imported transactions were flushed first.';

        if ($skippedRows > 0) {
            $status .= " {$skippedRows} invalid transaction row(s) were skipped and listed for manual correction.";
        }

        return redirect()
            ->route('dashboard')
            ->with('status', $status)
            ->with('import_result', $result)
            ->with('import_issues', $result['issues'] ?? [])
            ->with('date_issues', $dateIssues);
    }

    public function production(Request $request, ProductionExcelImporter $importer)
    {
        $data = $request->validate([
            'production_sheet' => $this->excelWorkbookRules(),
        ]);

        $path = $data['production_sheet']->storeAs(
            'imports',
            'apico-production-'.now()->format('Y-m-d-His').'.xlsx'
        );

        $result = DB::transaction(function () use ($importer, $path) {
            return $importer->import(Storage::path($path));
        });

        return redirect()
            ->route('production.index', [
                'year' => $result['year'],
                'month' => now()->month,
            ])
            ->with('status', 'Production sheet imported. P&L production and expenses were updated.')
            ->with('production_import_result', $result);
    }

    private function backupDatabase(): void
    {
        $database = database_path('database.sqlite');

        if (! File::exists($database)) {
            return;
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        File::copy($database, $directory.'/apico-before-import-'.now()->format('Y-m-d-His').'.sqlite');
    }

    private function excelWorkbookRules(): array
    {
        return [
            'required',
            'file',
            'max:20480',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (! $value instanceof UploadedFile || ! $value->isValid()) {
                    return;
                }

                if (strtolower($value->getClientOriginalExtension()) !== 'xlsx') {
                    $fail(__('Upload a valid Excel .xlsx workbook.'));

                    return;
                }

                $zip = new ZipArchive;
                $opened = $zip->open($value->getRealPath());
                $validWorkbook = $opened === true && $zip->locateName('xl/workbook.xml') !== false;

                if ($opened === true) {
                    $zip->close();
                }

                if (! $validWorkbook) {
                    $fail(__('Upload a valid Excel .xlsx workbook.'));
                }
            },
        ];
    }

    private function wipeImportedData(): void
    {
        StockSale::query()->delete();
        StockPurchase::query()->delete();
        Supplier::query()->whereDoesntHave('payments')->delete();
        Payment::query()->delete();
        RecycleOut::query()->delete();
        RecycleIn::query()->delete();
        Customer::query()->where('name', '!=', 'Sample Customer')->delete();
    }
}

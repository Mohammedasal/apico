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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ExcelImportController extends Controller
{
    public function store(Request $request, ApicoExcelImporter $importer)
    {
        $data = $request->validate([
            'sales_sheet' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ]);

        $path = $data['sales_sheet']->storeAs(
            'imports',
            'apico-sales-'.now()->format('Y-m-d-His').'.xlsx'
        );
        $fullPath = Storage::path($path);
        $dateIssues = $importer->dateIssues($fullPath);
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

        return redirect()
            ->route('dashboard')
            ->with('status', 'Sales sheet imported. Existing imported transactions were flushed first.')
            ->with('import_result', $result)
            ->with('date_issues', $dateIssues);
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

<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\RecycleIn;
use App\Models\RecycleOut;
use App\Models\StockPurchase;
use App\Models\StockSale;
use App\Models\Supplier;
use App\Services\AccountingPostingService;
use App\Services\ApicoExcelImporter;
use App\Services\AuditService;
use App\Services\DatabaseBackupService;
use App\Services\ProductionExcelImporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
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
        $result = $importer->preview($fullPath);
        $dateIssues = $importer->dateIssues($fullPath);
        $request->session()->put('sales_import_staging', [
            'path' => $path,
            'hash' => hash_file('sha256', $fullPath),
            'user_id' => $request->user()->id,
            'staged_at' => now()->toIso8601String(),
            'result' => $result,
            'date_issues' => $dateIssues,
        ]);

        return view('imports.sales-preview', compact('result', 'dateIssues'));
    }

    public function commit(
        Request $request,
        ApicoExcelImporter $importer,
        AccountingPostingService $posting,
        DatabaseBackupService $backups,
        AuditService $audit
    ) {
        $request->validate(['confirm' => ['accepted']]);
        $staging = $request->session()->get('sales_import_staging');

        abort_unless(is_array($staging) && ($staging['user_id'] ?? null) === $request->user()->id, 422, __('No staged sales import was found.'));
        abort_if(Carbon::parse($staging['staged_at'])->lt(now()->subHours(2)), 422, __('The staged sales import has expired. Upload it again.'));
        $fullPath = Storage::path($staging['path']);
        abort_unless(Storage::exists($staging['path']) && hash_file('sha256', $fullPath) === $staging['hash'], 422, __('The staged workbook could not be verified.'));

        try {
            $backupPath = $backups->create('apico-before-sales-import');
        } catch (Throwable $exception) {
            report($exception);

            return response()
                ->view('imports.sales-preview', [
                    'result' => $staging['result'],
                    'dateIssues' => $staging['date_issues'] ?? [],
                    'backupError' => __('The database backup failed verification. No records were changed.'),
                ], 503);
        }

        $before = [
            'recycle_in_rows' => RecycleIn::count(),
            'recycle_out_rows' => RecycleOut::count(),
            'payment_rows' => Payment::count(),
            'purchase_rows' => StockPurchase::count(),
            'stock_sale_rows' => StockSale::count(),
        ];
        $result = DB::transaction(function () use ($importer, $fullPath, $request, $posting, $audit, $before, $backupPath) {
            $this->reverseImportedAccounting($posting, $request);
            $this->wipeImportedData();
            $userId = $request->user()->id;

            $result = $importer->import($fullPath);

            foreach ([StockSale::class, StockPurchase::class, Payment::class, RecycleOut::class, RecycleIn::class] as $model) {
                $model::query()->update(['created_by' => $userId, 'updated_by' => null]);
            }
            RecycleOut::where('amount_variance', '!=', 0)->update(['approved_at' => now()]);
            $this->postImportedAccounting($posting, $request);
            $audit->recordEvent('sales_workbook_imported', ApicoExcelImporter::class, null, $before, [
                'result' => $result,
                'backup' => $backupPath ? basename($backupPath) : null,
            ]);

            return $result;
        });
        $dateIssues = $staging['date_issues'] ?? [];
        $skippedRows = (int) ($result['skipped_rows'] ?? 0);
        $status = 'Sales sheet imported. Existing imported transactions were flushed first.';

        if ($skippedRows > 0) {
            $status .= " {$skippedRows} invalid transaction row(s) were skipped and listed for manual correction.";
        }

        $request->session()->forget('sales_import_staging');

        return redirect()
            ->route('dashboard')
            ->with('status', $status)
            ->with('import_result', $result)
            ->with('import_issues', $result['issues'] ?? [])
            ->with('date_issues', $dateIssues)
            ->with('backup_path', $backupPath ? basename($backupPath) : null);
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

        $fullPath = Storage::path($path);
        $result = $importer->preview($fullPath);
        $request->session()->put('production_import_staging', [
            'path' => $path,
            'hash' => hash_file('sha256', $fullPath),
            'user_id' => $request->user()->id,
            'staged_at' => now()->toIso8601String(),
            'result' => $result,
        ]);

        return view('imports.production-preview', compact('result'));
    }

    public function productionCommit(
        Request $request,
        ProductionExcelImporter $importer,
        DatabaseBackupService $backups,
        AuditService $audit
    ) {
        $request->validate(['confirm' => ['accepted']]);
        $staging = $request->session()->get('production_import_staging');
        abort_unless(is_array($staging) && ($staging['user_id'] ?? null) === $request->user()->id, 422, __('No staged production import was found.'));
        abort_if(Carbon::parse($staging['staged_at'])->lt(now()->subHours(2)), 422, __('The staged production import has expired. Upload it again.'));
        $fullPath = Storage::path($staging['path']);
        abort_unless(Storage::exists($staging['path']) && hash_file('sha256', $fullPath) === $staging['hash'], 422, __('The staged workbook could not be verified.'));

        try {
            $backupPath = $backups->create('apico-before-production-import');
        } catch (Throwable $exception) {
            report($exception);

            return response()
                ->view('imports.production-preview', [
                    'result' => $staging['result'],
                    'backupError' => __('The database backup failed verification. No records were changed.'),
                ], 503);
        }
        $before = [
            'year' => $staging['result']['year'],
            'production_days' => $staging['result']['existing_production_days'],
            'monthly_expenses' => $staging['result']['existing_monthly_expenses'],
        ];
        $result = DB::transaction(function () use ($importer, $fullPath, $audit, $before, $backupPath) {
            $result = $importer->import($fullPath);
            $audit->recordEvent('production_workbook_imported', ProductionExcelImporter::class, null, $before, [
                'result' => $result,
                'backup' => $backupPath ? basename($backupPath) : null,
            ]);

            return $result;
        });
        $request->session()->forget('production_import_staging');

        return redirect()
            ->route('production.index', [
                'year' => $result['year'],
                'month' => now()->month,
            ])
            ->with('status', 'Production sheet imported. P&L production and expenses were updated.')
            ->with('production_import_result', $result)
            ->with('backup_path', $backupPath ? basename($backupPath) : null);
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

    private function reverseImportedAccounting(AccountingPostingService $posting, Request $request): void
    {
        foreach ([StockSale::class, StockPurchase::class, Payment::class, RecycleOut::class] as $model) {
            $model::query()->orderBy('id')->each(
                fn ($record) => $posting->reverseOperational($record, $request->user())
            );
        }
    }

    private function postImportedAccounting(AccountingPostingService $posting, Request $request): void
    {
        foreach ([RecycleOut::class, Payment::class, StockPurchase::class, StockSale::class] as $model) {
            $model::query()->orderBy('date')->orderBy('id')->each(
                fn ($record) => $posting->postOperational($record, $request->user())
            );
        }
    }
}

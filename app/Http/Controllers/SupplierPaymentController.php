<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    public function index(Request $request)
    {
        return view('supplier-payments.index', [
            'payments' => $this->filteredQuery($request)->paginate(25)->withQueryString(),
            'filters' => $this->filters($request),
            'suppliers' => Supplier::orderBy('name')->get(),
            'columns' => $this->exportColumns(),
        ]);
    }

    public function export(Request $request, SimpleXlsxExporter $exporter)
    {
        $columns = collect($this->exportColumns())
            ->only($request->input('columns', array_keys($this->exportColumns())))
            ->all() ?: $this->exportColumns();
        $rows = $this->filteredQuery($request)->get()->map(fn (SupplierPayment $payment) => [
            'date' => $payment->date?->toDateString(),
            'supplier' => $payment->supplier?->name,
            'amount' => number_format((float) $payment->amount, 3, '.', ''),
            'payment_type' => __(ucwords(str_replace('_', ' ', $payment->payment_type ?? 'cash'))),
            'reference_no' => $payment->reference_no,
            'cheque_due_date' => $payment->cheque_due_date?->toDateString(),
            'cheque_status' => $payment->payment_type === 'cheque' ? __(ucfirst($payment->cheque_status ?? 'pending')) : '',
            'notes' => $payment->notes,
        ]);

        return $exporter->download(
            'supplier-payments-'.now()->format('Y-m-d').'.xlsx',
            array_values($columns),
            $rows->map(fn (array $row) => collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all())->all()
        );
    }

    public function create()
    {
        $recentPayment = session('recent_supplier_payment')
            ? SupplierPayment::with('supplier')->find(session('recent_supplier_payment'))
            : null;

        return view('supplier-payments.form', [
            'payment' => new SupplierPayment(['date' => now(), 'payment_type' => 'cash', 'cheque_status' => 'pending']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'cashAccounts' => CashAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'recentPayment' => $recentPayment,
        ]);
    }

    public function store(Request $request, AccountingPostingService $posting)
    {
        $payment = DB::transaction(function () use ($request, $posting) {
            $payment = SupplierPayment::create($this->validated($request) + ['created_by' => $request->user()->id]);
            $posting->postOperational($payment, $request->user());

            return $payment;
        });

        return redirect()
            ->route('supplier-payments.create')
            ->with('recent_supplier_payment', $payment->id)
            ->with('status', __('Supplier payment saved.'));
    }

    public function edit(SupplierPayment $supplierPayment)
    {
        return view('supplier-payments.form', [
            'payment' => $supplierPayment,
            'suppliers' => Supplier::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'cashAccounts' => CashAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'recentPayment' => null,
        ]);
    }

    public function update(Request $request, SupplierPayment $supplierPayment, AccountingPostingService $posting)
    {
        if ($supplierPayment->payment_type === 'cheque' && $supplierPayment->cheque_status !== 'pending') {
            throw ValidationException::withMessages([
                'cheque_status' => __('Settled cheques must be corrected from the cheque settlement page.'),
            ]);
        }
        DB::transaction(function () use ($request, $supplierPayment, $posting) {
            $supplierPayment->update($this->validated($request, $supplierPayment) + ['updated_by' => $request->user()->id]);
            $posting->repostOperational($supplierPayment->fresh(), $request->user());
        });

        return redirect()->route('supplier-payments.index')->with('status', 'Supplier payment updated.');
    }

    public function destroy(
        SupplierPayment $supplierPayment,
        Request $request,
        AccountingPostingService $posting,
        AuditService $audit
    ) {
        DB::transaction(function () use ($supplierPayment, $request, $posting, $audit) {
            $before = $supplierPayment->toArray();
            $posting->reverseOperational($supplierPayment, $request->user());
            $audit->record('supplier_payment_deleted', $supplierPayment, $before, null);
            $supplierPayment->delete();
        });

        return redirect()->route('supplier-payments.index')->with('status', __('Supplier payment deleted.'));
    }

    private function filteredQuery(Request $request)
    {
        $filters = $this->filters($request);
        $sortable = [
            'date' => 'date',
            'amount' => 'amount',
            'payment_type' => 'payment_type',
            'cheque_due_date' => 'cheque_due_date',
        ];
        $sort = $sortable[$filters['sort']] ?? 'date';

        return SupplierPayment::with(['supplier', 'creator', 'editor'])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $search = $filters['q'];
                $query->where(function ($query) use ($search) {
                    $query->where('notes', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhere('bank_name', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['supplier_id'], fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($filters['from'], fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'], fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->when($filters['payment_type'], fn ($query, $type) => $query->where('payment_type', $type))
            ->when($filters['min_amount'] !== null, fn ($query) => $query->where('amount', '>=', $filters['min_amount']))
            ->when($filters['max_amount'] !== null, fn ($query) => $query->where('amount', '<=', $filters['max_amount']))
            ->when($filters['sort'] === 'supplier', fn ($query) => $query->orderBy(
                Supplier::select('name')->whereColumn('suppliers.id', 'supplier_payments.supplier_id'),
                $filters['direction']
            ), fn ($query) => $query->orderBy($sort, $filters['direction']))
            ->orderBy('id', $filters['direction']);
    }

    private function filters(Request $request): array
    {
        $sort = in_array($request->input('sort'), ['date', 'supplier', 'amount', 'payment_type', 'cheque_due_date'], true)
            ? $request->input('sort')
            : 'date';

        return [
            'q' => trim((string) $request->input('q', '')),
            'supplier_id' => $request->integer('supplier_id') ?: null,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'payment_type' => in_array($request->input('payment_type'), ['cash', 'cheque', 'bank_transfer', 'exchange_of_goods'], true)
                ? $request->input('payment_type')
                : null,
            'min_amount' => is_numeric($request->input('min_amount')) ? (float) $request->input('min_amount') : null,
            'max_amount' => is_numeric($request->input('max_amount')) ? (float) $request->input('max_amount') : null,
            'sort' => $sort,
            'direction' => $request->input('direction') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function exportColumns(): array
    {
        return [
            'date' => __('Date'),
            'supplier' => __('Supplier'),
            'amount' => __('Amount'),
            'payment_type' => __('Type'),
            'reference_no' => __('Reference'),
            'cheque_due_date' => __('Cheque Due Date'),
            'cheque_status' => __('Cheque Status'),
            'notes' => __('Notes'),
        ];
    }

    private function validated(Request $request, ?SupplierPayment $payment = null): array
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric'],
            'payment_type' => ['required', 'in:cash,cheque,bank_transfer,exchange_of_goods'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'cash_account_id' => ['nullable', 'exists:cash_accounts,id'],
            'cheque_due_date' => ['nullable', 'date'],
            'cheque_status' => ['nullable', 'in:pending,cleared,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['cheque_status'] = $data['payment_type'] === 'cheque' ? ($payment?->cheque_status ?? 'pending') : 'pending';
        $data['cheque_due_date'] = $data['payment_type'] === 'cheque' ? ($data['cheque_due_date'] ?? null) : null;

        return $data;
    }
}

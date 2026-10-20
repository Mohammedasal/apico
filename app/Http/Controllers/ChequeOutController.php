<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ChequeOut;
use App\Models\ExpenseVoucher;
use App\Models\SupplierPayment;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use App\Services\ChequeDueService;
use App\Services\FormSubmissionGuard;
use App\Services\SimpleXlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChequeOutController extends Controller
{
    public function index(Request $request, ChequeDueService $dueService)
    {
        return view('cheques.out', $this->viewData(request: $request, dueService: $dueService));
    }

    public function store(Request $request, AuditService $audit, FormSubmissionGuard $forms)
    {
        $data = $this->validated($request);
        $forms->consume('cheque-out', $request->input('_submission_token'));
        $cheque = ChequeOut::create($data + ['created_by' => $request->user()->id]);
        $audit->record('outgoing_cheque_created', $cheque, null, $cheque->toArray());

        return redirect()->route('cheques-out.index')->with('status', __('Outgoing cheque saved.'));
    }

    public function edit(ChequeOut $chequeOut, Request $request, ChequeDueService $dueService)
    {
        return view('cheques.out', $this->viewData($chequeOut, $request, $dueService));
    }

    public function update(Request $request, ChequeOut $chequeOut, AuditService $audit, FormSubmissionGuard $forms)
    {
        $data = $this->validated($request);
        $forms->assertFresh($chequeOut, $request->input('_record_version'));
        $before = $chequeOut->toArray();
        $chequeOut->update($data + ['updated_by' => $request->user()->id]);
        $audit->record('outgoing_cheque_updated', $chequeOut, $before, $chequeOut->fresh()->toArray());

        return redirect()->route('cheques-out.index')->with('status', __('Outgoing cheque updated.'));
    }

    public function updateSupplier(
        Request $request,
        SupplierPayment $supplierPayment,
        AccountingPostingService $posting,
        AuditService $audit,
        FormSubmissionGuard $forms
    ) {
        abort_unless($supplierPayment->payment_type === 'cheque', 404);
        $data = $this->settlementData($request);
        $forms->assertFresh($supplierPayment, $request->input('_record_version'));

        DB::transaction(function () use ($supplierPayment, $data, $request, $posting, $audit) {
            $before = $supplierPayment->toArray();
            $changed = $this->settlementChanged($supplierPayment, $data);
            $supplierPayment->update($data + [
                'cheque_status_updated_at' => now(),
                'cheque_status_updated_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            if ($changed) {
                $posting->repostSupplierChequeSettlement($supplierPayment->fresh(), $request->user());
            }
            $audit->record('supplier_cheque_status_updated', $supplierPayment, $before, $supplierPayment->fresh()->toArray());
        });

        return back()->with('status', __('Supplier cheque status updated.'));
    }

    public function updateExpense(
        Request $request,
        ExpenseVoucher $expense,
        AccountingPostingService $posting,
        AuditService $audit,
        FormSubmissionGuard $forms
    ) {
        abort_unless($expense->payment_type === 'cheque' && $expense->status === 'posted', 404);
        $data = $this->settlementData($request);
        $forms->assertFresh($expense, $request->input('_record_version'));

        DB::transaction(function () use ($expense, $data, $request, $posting, $audit) {
            $before = $expense->toArray();
            $changed = $this->settlementChanged($expense, $data);
            $expense->update($data + [
                'cheque_status_updated_at' => now(),
                'cheque_status_updated_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            if ($changed) {
                $posting->repostExpenseChequeSettlement($expense->fresh(), $request->user());
            }
            $audit->record('expense_cheque_status_updated', $expense, $before, $expense->fresh()->toArray());
        });

        return back()->with('status', __('Expense cheque status updated.'));
    }

    public function export(Request $request, ChequeDueService $dueService, SimpleXlsxExporter $exporter)
    {
        $filters = $this->filters($request);
        $availableColumns = $this->exportColumns();
        $columns = collect($availableColumns)
            ->only($request->input('columns', array_keys($availableColumns)))
            ->all() ?: $availableColumns;
        $rows = collect();

        if (in_array($filters['source'], ['', 'manual'], true)) {
            $rows->push(...$this->manualQuery($filters)->get()->map(fn (ChequeOut $cheque) => [
                'source' => __('Manual Monitor'),
                'due_date' => $cheque->due_date?->toDateString(),
                'payee' => $cheque->payee,
                'transaction_date' => $cheque->issue_date?->toDateString(),
                'amount' => number_format((float) $cheque->amount, 3, '.', ''),
                'bank' => $cheque->bank_name,
                'cheque_number' => $cheque->cheque_number,
                'status' => __(ucfirst($cheque->status)),
                'due_state' => $this->translatedDueState($dueService, $cheque->due_date, $cheque->status),
                'settlement_date' => '',
                'notes' => $cheque->notes,
            ]));
        }
        if (in_array($filters['source'], ['', 'supplier'], true)) {
            $rows->push(...$this->supplierQuery($filters)->get()->map(fn (SupplierPayment $payment) => [
                'source' => __('Supplier Payment'),
                'due_date' => $payment->cheque_due_date?->toDateString(),
                'payee' => $payment->supplier?->name,
                'transaction_date' => $payment->date?->toDateString(),
                'amount' => number_format((float) $payment->amount, 3, '.', ''),
                'bank' => $payment->bank_name,
                'cheque_number' => $payment->reference_no,
                'status' => __(ucfirst($payment->cheque_status ?? 'pending')),
                'due_state' => $this->translatedDueState($dueService, $payment->cheque_due_date, $payment->cheque_status),
                'settlement_date' => $payment->cheque_settlement_date?->toDateString(),
                'notes' => $payment->notes,
            ]));
        }
        if (in_array($filters['source'], ['', 'expense'], true)) {
            $rows->push(...$this->expenseQuery($filters)->get()->map(fn (ExpenseVoucher $expense) => [
                'source' => __('Expense'),
                'due_date' => $expense->cheque_due_date?->toDateString(),
                'payee' => $expense->category?->localized_name,
                'transaction_date' => $expense->expense_date?->toDateString(),
                'amount' => number_format((float) $expense->paid_amount, 3, '.', ''),
                'bank' => '',
                'cheque_number' => $expense->reference,
                'status' => __(ucfirst($expense->cheque_status ?? 'pending')),
                'due_state' => $this->translatedDueState($dueService, $expense->cheque_due_date, $expense->cheque_status),
                'settlement_date' => $expense->cheque_settlement_date?->toDateString(),
                'notes' => $expense->notes,
            ]));
        }

        $exportRows = $rows->map(fn (array $row) => collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all())->all();

        return $exporter->download(
            'cheques-out-'.now()->format('Y-m-d').'.xlsx',
            array_values($columns),
            $exportRows,
            [[__('Cheques Out'), ($filters['from'] ?: __('Beginning')).' '.__('to').' '.($filters['to'] ?: now()->toDateString())]]
        );
    }

    private function viewData(?ChequeOut $record = null, ?Request $request = null, ?ChequeDueService $dueService = null): array
    {
        $dueService ??= app(ChequeDueService::class);
        $request ??= request();
        $filters = $this->filters($request);
        $cheques = $this->manualQuery($filters)->paginate(30, ['*'], 'manual_page')->withQueryString();
        $supplierCheques = $this->supplierQuery($filters)->paginate(30, ['*'], 'supplier_page')->withQueryString();
        $expenseCheques = $this->expenseQuery($filters)->paginate(30, ['*'], 'expense_page')->withQueryString();

        $cheques->getCollection()->each(fn (ChequeOut $cheque) => $cheque->setAttribute('due_state', $dueService->state($cheque->due_date, $cheque->status)));
        $supplierCheques->each(fn (SupplierPayment $payment) => $payment->setAttribute('due_state', $dueService->state($payment->cheque_due_date, $payment->cheque_status)));
        $expenseCheques->each(fn (ExpenseVoucher $expense) => $expense->setAttribute('due_state', $dueService->state($expense->cheque_due_date, $expense->cheque_status)));

        return [
            'cheques' => $cheques,
            'supplierCheques' => $supplierCheques,
            'expenseCheques' => $expenseCheques,
            'dueSummary' => $dueService->summary()['outgoing'],
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'record' => $record,
            'filters' => $filters,
        ];
    }

    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q')),
            'source' => in_array($request->input('source'), ['manual', 'supplier', 'expense'], true) ? $request->input('source') : '',
            'status' => in_array($request->input('status'), ['pending', 'cleared', 'cancelled'], true) ? $request->input('status') : null,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'min_amount' => $request->filled('min_amount') ? (float) $request->input('min_amount') : null,
            'max_amount' => $request->filled('max_amount') ? (float) $request->input('max_amount') : null,
            'direction' => $request->input('direction') === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function manualQuery(array $filters): Builder
    {
        return ChequeOut::with(['creator', 'editor'])
            ->when($filters['source'] !== '' && $filters['source'] !== 'manual', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('due_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('due_date', '<=', $to))
            ->when(! is_null($filters['min_amount']), fn (Builder $query) => $query->where('amount', '>=', $filters['min_amount']))
            ->when(! is_null($filters['max_amount']), fn (Builder $query) => $query->where('amount', '<=', $filters['max_amount']))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('payee', 'like', "%{$filters['q']}%")
                ->orWhere('bank_name', 'like', "%{$filters['q']}%")
                ->orWhere('cheque_number', 'like', "%{$filters['q']}%")
                ->orWhere('notes', 'like', "%{$filters['q']}%")))
            ->orderBy('due_date', $filters['direction'])->orderBy('id', $filters['direction']);
    }

    private function supplierQuery(array $filters): Builder
    {
        return SupplierPayment::with(['supplier', 'creator', 'editor', 'chequeBankAccount'])
            ->where('payment_type', 'cheque')
            ->when($filters['source'] !== '' && $filters['source'] !== 'supplier', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('cheque_status', $status))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('cheque_due_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('cheque_due_date', '<=', $to))
            ->when(! is_null($filters['min_amount']), fn (Builder $query) => $query->where('amount', '>=', $filters['min_amount']))
            ->when(! is_null($filters['max_amount']), fn (Builder $query) => $query->where('amount', '<=', $filters['max_amount']))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('bank_name', 'like', "%{$filters['q']}%")
                ->orWhere('reference_no', 'like', "%{$filters['q']}%")
                ->orWhere('notes', 'like', "%{$filters['q']}%")
                ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', "%{$filters['q']}%"))))
            ->orderBy('cheque_due_date', $filters['direction'])->orderBy('id', $filters['direction']);
    }

    private function expenseQuery(array $filters): Builder
    {
        return ExpenseVoucher::with(['category', 'creator', 'editor', 'chequeBankAccount'])
            ->where('payment_type', 'cheque')->where('status', 'posted')
            ->when($filters['source'] !== '' && $filters['source'] !== 'expense', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('cheque_status', $status))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('cheque_due_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('cheque_due_date', '<=', $to))
            ->when(! is_null($filters['min_amount']), fn (Builder $query) => $query->where('paid_amount', '>=', $filters['min_amount']))
            ->when(! is_null($filters['max_amount']), fn (Builder $query) => $query->where('paid_amount', '<=', $filters['max_amount']))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('voucher_no', 'like', "%{$filters['q']}%")
                ->orWhere('reference', 'like', "%{$filters['q']}%")
                ->orWhere('notes', 'like', "%{$filters['q']}%")
                ->orWhereHas('category', fn (Builder $query) => $query->where('name_en', 'like', "%{$filters['q']}%")->orWhere('name_ar', 'like', "%{$filters['q']}%"))))
            ->orderBy('cheque_due_date', $filters['direction'])->orderBy('id', $filters['direction']);
    }

    private function exportColumns(): array
    {
        return [
            'source' => __('Source'),
            'due_date' => __('Due Date'),
            'payee' => __('Payee'),
            'transaction_date' => __('Transaction Date'),
            'amount' => __('Amount'),
            'bank' => __('Bank'),
            'cheque_number' => __('Cheque No.'),
            'status' => __('Status'),
            'due_state' => __('Due State'),
            'settlement_date' => __('Settlement Date'),
            'notes' => __('Notes'),
        ];
    }

    private function translatedDueState(ChequeDueService $dueService, mixed $date, ?string $status): string
    {
        return __(str($dueService->state($date, $status))->replace('_', ' ')->title()->toString());
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'payee' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'cheque_number' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,cleared,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function settlementData(Request $request): array
    {
        $data = $request->validate([
            'cheque_status' => ['required', 'in:pending,cleared,cancelled'],
            'cheque_settlement_date' => ['nullable', 'date', 'required_unless:cheque_status,pending'],
            'cheque_bank_account_id' => ['nullable', 'exists:bank_accounts,id', 'required_if:cheque_status,cleared'],
        ]);

        if ($data['cheque_status'] === 'pending') {
            $data['cheque_settlement_date'] = null;
            $data['cheque_bank_account_id'] = null;
        } elseif ($data['cheque_status'] !== 'cleared') {
            $data['cheque_bank_account_id'] = null;
        }

        return $data;
    }

    private function settlementChanged(object $cheque, array $data): bool
    {
        return $cheque->cheque_status !== $data['cheque_status']
            || $cheque->cheque_settlement_date?->toDateString() !== ($data['cheque_settlement_date'] ?? null)
            || $cheque->cheque_bank_account_id !== ($data['cheque_bank_account_id'] ?? null);
    }
}

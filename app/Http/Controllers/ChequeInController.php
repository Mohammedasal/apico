<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use App\Services\ChequeDueService;
use App\Services\FormSubmissionGuard;
use App\Services\SimpleXlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChequeInController extends Controller
{
    public function index(Request $request, ChequeDueService $dueService)
    {
        $filters = $this->filters($request);
        $cheques = $this->filteredQuery($filters)
            ->paginate(50)
            ->withQueryString();
        $cheques->getCollection()->each(fn (Payment $payment) => $payment->setAttribute(
            'due_state',
            $dueService->state($payment->cheque_due_date, $payment->cheque_status)
        ));

        return view('cheques.in', [
            'filters' => $filters,
            'dueSummary' => $dueService->summary()['incoming'],
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'cheques' => $cheques,
        ]);
    }

    public function export(Request $request, ChequeDueService $dueService, SimpleXlsxExporter $exporter)
    {
        $filters = $this->filters($request);
        $availableColumns = $this->exportColumns();
        $columns = collect($availableColumns)
            ->only($request->input('columns', array_keys($availableColumns)))
            ->all() ?: $availableColumns;
        $rows = $this->filteredQuery($filters)->get()->map(function (Payment $payment) use ($columns, $dueService) {
            $row = [
                'due_date' => $payment->cheque_due_date?->toDateString(),
                'customer' => $payment->customer?->name,
                'payment_date' => $payment->date?->toDateString(),
                'amount' => number_format((float) $payment->amount, 3, '.', ''),
                'bank' => $payment->bank_name,
                'cheque_number' => $payment->reference_no,
                'status' => __(ucfirst($payment->cheque_status ?? 'pending')),
                'due_state' => __(str($dueService->state($payment->cheque_due_date, $payment->cheque_status))->replace('_', ' ')->title()->toString()),
                'settlement_date' => $payment->cheque_settlement_date?->toDateString(),
                'settlement_bank' => $payment->chequeBankAccount?->localized_name,
                'notes' => $payment->notes,
            ];

            return collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all();
        })->all();

        return $exporter->download(
            'cheques-in-'.now()->format('Y-m-d').'.xlsx',
            array_values($columns),
            $rows,
            [[__('Cheques In'), ($filters['from'] ?: __('Beginning')).' '.__('to').' '.($filters['to'] ?: now()->toDateString())]]
        );
    }

    public function update(
        Request $request,
        Payment $payment,
        AccountingPostingService $posting,
        AuditService $audit,
        FormSubmissionGuard $forms
    ) {
        abort_unless($payment->payment_type === 'cheque', 404);

        $data = $request->validate([
            'cheque_status' => ['required', 'in:pending,collected,bounced,cancelled'],
            'cheque_settlement_date' => ['nullable', 'date', 'required_unless:cheque_status,pending'],
            'cheque_bank_account_id' => ['nullable', 'exists:bank_accounts,id', 'required_if:cheque_status,collected'],
        ]);
        $forms->assertFresh($payment, $request->input('_record_version'));
        if ($data['cheque_status'] === 'pending') {
            $data['cheque_settlement_date'] = null;
            $data['cheque_bank_account_id'] = null;
        } elseif ($data['cheque_status'] !== 'collected') {
            $data['cheque_bank_account_id'] = null;
        }

        DB::transaction(function () use ($payment, $data, $request, $posting, $audit) {
            $before = $payment->toArray();
            $changed = $payment->cheque_status !== $data['cheque_status']
                || $payment->cheque_settlement_date?->toDateString() !== ($data['cheque_settlement_date'] ?? null)
                || $payment->cheque_bank_account_id !== ($data['cheque_bank_account_id'] ?? null);

            $payment->update($data + [
                'cheque_status_updated_at' => now(),
                'cheque_status_updated_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            if ($changed) {
                $posting->repostIncomingChequeSettlement($payment->fresh(), $request->user());
            }
            $audit->record('incoming_cheque_status_updated', $payment, $before, $payment->fresh()->toArray());
        });

        return redirect()->route('cheques-in.index')->with('status', __('Incoming cheque status updated.'));
    }

    private function filters(Request $request): array
    {
        $sort = $request->input('sort', 'due_date');

        return [
            'q' => trim((string) $request->input('q')),
            'status' => in_array($request->input('status'), ['pending', 'collected', 'bounced', 'cancelled'], true) ? $request->input('status') : null,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'min_amount' => $request->filled('min_amount') ? (float) $request->input('min_amount') : null,
            'max_amount' => $request->filled('max_amount') ? (float) $request->input('max_amount') : null,
            'sort' => in_array($sort, ['due_date', 'customer', 'payment_date', 'amount', 'status'], true) ? $sort : 'due_date',
            'direction' => $request->input('direction') === 'desc' ? 'desc' : 'asc',
        ];
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = Payment::with(['customer', 'creator', 'editor', 'chequeBankAccount'])
            ->where('payment_type', 'cheque')
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('cheque_status', $status))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->whereDate('cheque_due_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->whereDate('cheque_due_date', '<=', $to))
            ->when(! is_null($filters['min_amount']), fn (Builder $query) => $query->where('amount', '>=', $filters['min_amount']))
            ->when(! is_null($filters['max_amount']), fn (Builder $query) => $query->where('amount', '<=', $filters['max_amount']))
            ->when($filters['q'] !== '', function (Builder $query) use ($filters) {
                $query->where(function (Builder $query) use ($filters) {
                    $search = $filters['q'];
                    $query->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('bank_name', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            });

        $column = match ($filters['sort']) {
            'due_date' => 'cheque_due_date',
            'payment_date' => 'date',
            default => $filters['sort'],
        };
        if ($column === 'customer') {
            $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'payments.customer_id'),
                $filters['direction']
            );
        } else {
            $query->orderBy($column, $filters['direction']);
        }

        return $query->orderBy('id', $filters['direction']);
    }

    private function exportColumns(): array
    {
        return [
            'due_date' => __('Due Date'),
            'customer' => __('Customer'),
            'payment_date' => __('Payment Date'),
            'amount' => __('Amount'),
            'bank' => __('Bank'),
            'cheque_number' => __('Cheque No.'),
            'status' => __('Status'),
            'due_state' => __('Due State'),
            'settlement_date' => __('Settlement Date'),
            'settlement_bank' => __('Settlement Bank'),
            'notes' => __('Notes'),
        ];
    }
}

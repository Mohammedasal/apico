<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\ChequeOut;
use App\Models\ExpenseVoucher;
use App\Models\SupplierPayment;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChequeOutController extends Controller
{
    public function index()
    {
        return view('cheques.out', $this->viewData());
    }

    public function store(Request $request)
    {
        ChequeOut::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('cheques-out.index')->with('status', __('Outgoing cheque saved.'));
    }

    public function edit(ChequeOut $chequeOut)
    {
        return view('cheques.out', $this->viewData($chequeOut));
    }

    public function update(Request $request, ChequeOut $chequeOut)
    {
        $chequeOut->update($this->validated($request) + ['updated_by' => $request->user()->id]);

        return redirect()->route('cheques-out.index')->with('status', __('Outgoing cheque updated.'));
    }

    public function updateSupplier(
        Request $request,
        SupplierPayment $supplierPayment,
        AccountingPostingService $posting,
        AuditService $audit
    ) {
        abort_unless($supplierPayment->payment_type === 'cheque', 404);
        $data = $this->settlementData($request);

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
        AuditService $audit
    ) {
        abort_unless($expense->payment_type === 'cheque' && $expense->status === 'posted', 404);
        $data = $this->settlementData($request);

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

    private function viewData(?ChequeOut $record = null): array
    {
        return [
            'cheques' => ChequeOut::with(['creator', 'editor'])->orderBy('due_date')->orderBy('id')->paginate(50),
            'supplierCheques' => SupplierPayment::with(['supplier', 'creator', 'editor', 'chequeBankAccount'])
                ->where('payment_type', 'cheque')->orderBy('cheque_due_date')->orderBy('date')->get(),
            'expenseCheques' => ExpenseVoucher::with(['category', 'creator', 'editor', 'chequeBankAccount'])
                ->where('payment_type', 'cheque')->where('status', 'posted')->orderBy('cheque_due_date')->orderBy('expense_date')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'record' => $record,
        ];
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

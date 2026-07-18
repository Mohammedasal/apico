<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Payment;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChequeInController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');

        return view('cheques.in', [
            'status' => $status,
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'cheques' => Payment::with(['customer', 'creator', 'editor', 'chequeBankAccount'])
                ->where('payment_type', 'cheque')
                ->when($status, fn ($query) => $query->where('cheque_status', $status))
                ->orderBy('cheque_due_date')
                ->orderBy('date')
                ->paginate(50),
        ]);
    }

    public function update(
        Request $request,
        Payment $payment,
        AccountingPostingService $posting,
        AuditService $audit
    ) {
        abort_unless($payment->payment_type === 'cheque', 404);

        $data = $request->validate([
            'cheque_status' => ['required', 'in:pending,collected,bounced,cancelled'],
            'cheque_settlement_date' => ['nullable', 'date', 'required_unless:cheque_status,pending'],
            'cheque_bank_account_id' => ['nullable', 'exists:bank_accounts,id', 'required_if:cheque_status,collected'],
        ]);
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
}

<?php

namespace App\Services;

use App\Models\ChequeOut;
use App\Models\ExpenseVoucher;
use App\Models\Payment;
use App\Models\SupplierPayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ChequeDueService
{
    public function state(?CarbonInterface $dueDate, ?string $settlementStatus): string
    {
        if (($settlementStatus ?? 'pending') !== 'pending') {
            return 'settled';
        }

        if (! $dueDate) {
            return 'no_due_date';
        }

        $today = today();
        $date = $dueDate->copy()->startOfDay();

        if ($date->lt($today)) {
            return 'overdue';
        }

        if ($date->isSameDay($today)) {
            return 'due_today';
        }

        return $date->lte($today->copy()->addDays(7)) ? 'upcoming' : 'scheduled';
    }

    public function summary(): array
    {
        $incoming = Payment::where('payment_type', 'cheque')->get()
            ->map(fn (Payment $payment) => $this->state($payment->cheque_due_date, $payment->cheque_status));
        $outgoing = collect()
            ->merge(SupplierPayment::where('payment_type', 'cheque')->get()
                ->map(fn (SupplierPayment $payment) => $this->state($payment->cheque_due_date, $payment->cheque_status)))
            ->merge(ExpenseVoucher::where('payment_type', 'cheque')->where('status', 'posted')->get()
                ->map(fn (ExpenseVoucher $expense) => $this->state($expense->cheque_due_date, $expense->cheque_status)))
            ->merge(ChequeOut::all()
                ->map(fn (ChequeOut $cheque) => $this->state($cheque->due_date, $cheque->status)));

        return [
            'as_of' => today()->toDateString(),
            'incoming' => $this->counts($incoming),
            'outgoing' => $this->counts($outgoing),
        ];
    }

    private function counts(Collection $states): array
    {
        return collect(['overdue', 'due_today', 'upcoming', 'scheduled', 'no_due_date', 'settled'])
            ->mapWithKeys(fn (string $state) => [$state => $states->filter(fn (string $value) => $value === $state)->count()])
            ->all();
    }
}

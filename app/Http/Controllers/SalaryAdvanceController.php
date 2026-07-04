<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryAdvanceController extends Controller
{
    public function store(
        Request $request,
        PayrollRun $payroll,
        AccountingPostingService $posting,
        AuditService $audit
    ) {
        if ($payroll->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Salary advances can only be added to draft payroll.')]);
        }

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_type' => ['required', 'in:cash,bank_transfer'],
            'cash_account_id' => ['nullable', 'exists:cash_accounts,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $line = $payroll->lines()->where('employee_id', $data['employee_id'])->first();
        if (! $line) {
            throw ValidationException::withMessages(['employee_id' => __('The employee is not included in this payroll run.')]);
        }
        $paymentDate = Carbon::parse($data['payment_date']);
        if ($paymentDate->year !== $payroll->period_year || $paymentDate->month !== $payroll->period_month) {
            throw ValidationException::withMessages(['payment_date' => __('The advance date must be within the payroll month.')]);
        }

        $alreadyAdvanced = (float) $payroll->advances()
            ->where('employee_id', $data['employee_id'])
            ->where('status', 'posted')
            ->sum('amount');
        if (round($alreadyAdvanced + (float) $data['amount'], 3) > (float) $line->net_salary) {
            throw ValidationException::withMessages(['amount' => __('Salary advances cannot exceed the employee net salary.')]);
        }

        DB::transaction(function () use ($payroll, $data, $request, $posting, $audit) {
            $advance = $payroll->advances()->create($data + [
                'status' => 'posted',
                'created_by' => $request->user()->id,
            ]);
            $posting->postSalaryAdvance($advance, $request->user());
            $audit->record('salary_advance_created', $advance, null, $advance->toArray());
        });

        return back()->with('status', __('Salary advance posted.'));
    }

    public function cancel(
        PayrollRun $payroll,
        SalaryAdvance $advance,
        Request $request,
        AccountingPostingService $posting,
        AuditService $audit
    ) {
        if ($advance->payroll_run_id !== $payroll->id) {
            abort(404);
        }
        if ($payroll->status !== 'draft' || $advance->status !== 'posted') {
            throw ValidationException::withMessages(['status' => __('Only posted advances on draft payroll can be cancelled.')]);
        }

        DB::transaction(function () use ($advance, $request, $posting, $audit) {
            $before = $advance->toArray();
            $posting->reverseSalaryAdvance($advance, $request->user());
            $advance->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
            ]);
            $audit->record('salary_advance_cancelled', $advance, $before, $advance->fresh()->toArray());
        });

        return back()->with('status', __('Salary advance cancelled.'));
    }
}

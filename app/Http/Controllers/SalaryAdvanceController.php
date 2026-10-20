<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use App\Services\FormSubmissionGuard;
use App\Services\SimpleXlsxExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryAdvanceController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['year', 'month', 'employee_id', 'status']);
        $year = $request->integer('year') ?: now()->year;
        $month = $request->integer('month') ?: now()->month;

        $query = $this->filteredQuery($year, $month, $request);

        $totalPosted = (float) (clone $query)->where('status', 'posted')->sum('amount');
        $advances = $query
            ->with(['employee', 'payrollRun', 'cashAccount', 'bankAccount', 'creator'])
            ->latest('payment_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('accounting.salary-advances.index', [
            'advances' => $advances,
            'employees' => Employee::orderBy('name_en')->get(),
            'filters' => array_merge($filters, ['year' => $year, 'month' => $month]),
            'totalPosted' => $totalPosted,
        ]);
    }

    public function export(Request $request, SimpleXlsxExporter $exporter)
    {
        $year = $request->integer('year') ?: now()->year;
        $month = $request->integer('month') ?: now()->month;
        $availableColumns = $this->exportColumns();
        $columns = collect($availableColumns)
            ->only($request->input('columns', array_keys($availableColumns)))
            ->all() ?: $availableColumns;
        $rows = $this->filteredQuery($year, $month, $request)
            ->with(['employee', 'payrollRun', 'cashAccount', 'bankAccount', 'creator'])
            ->latest('payment_date')->latest('id')->get()
            ->map(function (SalaryAdvance $advance) use ($columns) {
                $row = [
                    'date' => $advance->payment_date?->toDateString(),
                    'employee' => $advance->employee?->localized_name,
                    'amount' => number_format((float) $advance->amount, 3, '.', ''),
                    'payment_type' => $advance->payment_type === 'cash' ? __('Cash') : __('Bank Transfer'),
                    'account' => $advance->cashAccount?->localized_name ?? $advance->bankAccount?->localized_name ?? __('Default Account'),
                    'reference' => $advance->reference,
                    'payroll_period' => $advance->payrollRun ? sprintf('%04d-%02d', $advance->payrollRun->period_year, $advance->payrollRun->period_month) : __('Pending Month-End Payroll'),
                    'status' => __(ucfirst($advance->status)),
                    'notes' => $advance->notes,
                    'created_by' => $advance->creator?->name ?? __('System'),
                ];

                return collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all();
            })->all();

        return $exporter->download(
            sprintf('salary-advances-%04d-%02d.xlsx', $year, $month),
            array_values($columns),
            $rows,
            [[__('Salary Advances'), sprintf('%04d-%02d', $year, $month)]]
        );
    }

    public function create(Request $request)
    {
        return view('accounting.salary-advances.create', [
            'employees' => Employee::where('is_active', true)->orderBy('name_en')->get(),
            'cashAccounts' => CashAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'selectedEmployee' => $request->integer('employee_id'),
            'paymentDate' => $request->date('payment_date')?->toDateString() ?? now()->toDateString(),
        ]);
    }

    public function store(Request $request, AccountingPostingService $posting, AuditService $audit, FormSubmissionGuard $forms)
    {
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
        $forms->consume('salary-advance', $request->input('_submission_token'));

        $paymentDate = Carbon::parse($data['payment_date']);
        $payroll = PayrollRun::where('period_year', $paymentDate->year)
            ->where('period_month', $paymentDate->month)
            ->first();

        if ($payroll && $payroll->status !== 'draft') {
            throw ValidationException::withMessages([
                'payment_date' => __('Payroll for this month is already finalized.'),
            ]);
        }

        $payrollId = $payroll?->lines()->where('employee_id', $data['employee_id'])->exists()
            ? $payroll->id
            : null;

        DB::transaction(function () use ($payrollId, $data, $request, $posting, $audit) {
            $advance = SalaryAdvance::create($data + [
                'payroll_run_id' => $payrollId,
                'status' => 'posted',
                'created_by' => $request->user()->id,
            ]);
            $posting->postSalaryAdvance($advance, $request->user());
            $audit->record('salary_advance_created', $advance, null, $advance->toArray());
        });

        return redirect()->route('accounting.salary-advances.index', [
            'year' => $paymentDate->year,
            'month' => $paymentDate->month,
        ])->with('status', __('Salary advance posted.'));
    }

    public function cancel(
        SalaryAdvance $advance,
        Request $request,
        AccountingPostingService $posting,
        AuditService $audit
    ) {
        $advance->loadMissing('payrollRun');
        if ($advance->status !== 'posted' || ($advance->payrollRun && $advance->payrollRun->status !== 'draft')) {
            throw ValidationException::withMessages([
                'status' => __('Only unsettled salary advances can be cancelled.'),
            ]);
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

    private function filteredQuery(int $year, int $month, Request $request)
    {
        return SalaryAdvance::query()
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->when($request->input('employee_id'), fn ($query, $employee) => $query->where('employee_id', $employee))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status));
    }

    private function exportColumns(): array
    {
        return [
            'date' => __('Date'),
            'employee' => __('Employee'),
            'amount' => __('Amount'),
            'payment_type' => __('Payment Type'),
            'account' => __('Account'),
            'reference' => __('Reference'),
            'payroll_period' => __('Payroll Period'),
            'status' => __('Status'),
            'notes' => __('Notes'),
            'created_by' => __('Created By'),
        ];
    }
}

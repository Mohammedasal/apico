<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        return view('accounting.payroll.index', [
            'runs' => $this->filteredQuery($request)->latest('period_year')->latest('period_month')->paginate(24)->withQueryString(),
            'filters' => $request->only(['year', 'month', 'status', 'employee_id']),
            'employees' => Employee::orderBy('name_en')->get(),
        ]);
    }

    public function create()
    {
        return view('accounting.payroll.form', $this->formData(new PayrollRun([
            'period_year' => now()->year,
            'period_month' => now()->month,
            'payroll_date' => now()->endOfMonth(),
            'status' => 'draft',
        ])));
    }

    public function store(Request $request, AuditService $audit)
    {
        [$runData, $lines] = $this->validated($request);

        $run = DB::transaction(function () use ($runData, $lines, $request, $audit) {
            $run = PayrollRun::create($runData + ['status' => 'draft', 'created_by' => $request->user()->id]);
            $run->lines()->createMany($lines);
            $this->recalculateTotals($run);
            $this->syncSalaryAdvances($run);
            $audit->record('payroll_created', $run, null, $run->fresh('lines')->toArray());

            return $run;
        });

        return redirect()->route('accounting.payroll.show', $run)->with('status', __('Draft payroll created.'));
    }

    public function show(PayrollRun $payroll)
    {
        return view('accounting.payroll.show', [
            'payroll' => $payroll->load([
                'lines.employee', 'advances.employee', 'advances.cashAccount', 'advances.bankAccount',
                'advances.creator', 'cashAccount', 'bankAccount', 'socialSecurityCashAccount',
                'socialSecurityBankAccount', 'creator', 'poster', 'payer',
            ]),
            'journalEntries' => JournalEntry::with('lines')
                ->where('source_module', 'accounting')
                ->where(function ($query) use ($payroll) {
                    $query->where(function ($query) use ($payroll) {
                        $query->where('source_type', class_basename($payroll))
                            ->where('source_id', $payroll->id);
                    })->orWhere(function ($query) use ($payroll) {
                        $query->where('source_type', 'SalaryAdvance')
                            ->whereIn('source_id', $payroll->advances->pluck('id'));
                    });
                })
                ->orderBy('id')
                ->get(),
            'cashAccounts' => CashAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
        ]);
    }

    public function edit(PayrollRun $payroll)
    {
        $this->assertDraft($payroll);

        return view('accounting.payroll.form', $this->formData($payroll->load('lines')));
    }

    public function update(Request $request, PayrollRun $payroll, AuditService $audit)
    {
        $this->assertDraft($payroll);
        [$runData, $lines] = $this->validated($request, $payroll);

        DB::transaction(function () use ($payroll, $runData, $lines, $request, $audit) {
            $before = $payroll->load('lines')->toArray();
            $payroll->update($runData + ['updated_by' => $request->user()->id]);
            $payroll->lines()->delete();
            $payroll->lines()->createMany($lines);
            $this->recalculateTotals($payroll);
            $this->syncSalaryAdvances($payroll);
            $audit->record('payroll_updated', $payroll, $before, $payroll->fresh('lines')->toArray());
        });

        return redirect()->route('accounting.payroll.show', $payroll)->with('status', __('Draft payroll updated.'));
    }

    public function post(PayrollRun $payroll, Request $request, AccountingPostingService $posting, AuditService $audit)
    {
        $this->assertDraft($payroll);

        DB::transaction(function () use ($payroll, $request, $posting, $audit) {
            $before = $payroll->toArray();
            $posting->postPayrollAccrual($payroll, $request->user());
            $payroll->load('advances');
            $fullyAdvanced = $payroll->remaining_salary <= 0;
            $payroll->update([
                'status' => $fullyAdvanced ? 'paid' : 'posted',
                'posted_at' => now(),
                'posted_by' => $request->user()->id,
                'paid_at' => $fullyAdvanced ? now() : null,
                'paid_by' => $fullyAdvanced ? $request->user()->id : null,
            ]);
            $audit->record('payroll_posted', $payroll, $before, $payroll->fresh()->toArray());
        });

        return back()->with('status', __('Payroll accrual posted.'));
    }

    public function pay(PayrollRun $payroll, Request $request, AccountingPostingService $posting, AuditService $audit)
    {
        if ($payroll->status !== 'posted') {
            throw ValidationException::withMessages(['status' => __('Only posted payroll can be paid.')]);
        }

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_type' => ['required', 'in:cash,bank_transfer'],
            'cash_account_id' => ['nullable', 'exists:cash_accounts,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($payroll, $data, $request, $posting, $audit) {
            $before = $payroll->toArray();
            $payroll->update($data + ['updated_by' => $request->user()->id]);
            $posting->postPayrollPayment($payroll->fresh(), $request->user());
            $payroll->update(['status' => 'paid', 'paid_at' => now(), 'paid_by' => $request->user()->id]);
            $audit->record('payroll_paid', $payroll, $before, $payroll->fresh()->toArray());
        });

        return back()->with('status', __('Payroll payment posted.'));
    }

    public function paySocialSecurity(PayrollRun $payroll, Request $request, AccountingPostingService $posting, AuditService $audit)
    {
        if (! in_array($payroll->status, ['posted', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => __('Only posted or paid payroll can have social security settled.')]);
        }
        if ($payroll->social_security_paid_at) {
            throw ValidationException::withMessages(['status' => __('Social security is already settled for this payroll.')]);
        }

        $data = $request->validate([
            'social_security_payment_date' => ['required', 'date'],
            'social_security_payment_type' => ['required', 'in:cash,bank_transfer'],
            'social_security_cash_account_id' => ['nullable', 'exists:cash_accounts,id'],
            'social_security_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'social_security_payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($payroll, $data, $request, $posting, $audit) {
            $before = $payroll->toArray();
            $payroll->update($data + ['updated_by' => $request->user()->id]);
            $posting->postPayrollSocialSecurityPayment($payroll->fresh(), $request->user());
            $payroll->update([
                'social_security_paid_at' => now(),
                'social_security_paid_by' => $request->user()->id,
            ]);
            $audit->record('payroll_social_security_paid', $payroll, $before, $payroll->fresh()->toArray());
        });

        return back()->with('status', __('Social security payment posted.'));
    }

    public function cancel(PayrollRun $payroll, Request $request, AccountingPostingService $posting, AuditService $audit)
    {
        if ($payroll->status === 'cancelled') {
            return back()->with('status', __('Payroll already cancelled.'));
        }

        DB::transaction(function () use ($payroll, $request, $posting, $audit) {
            $before = $payroll->toArray();
            if (in_array($payroll->status, ['draft', 'posted', 'paid'], true)) {
                $posting->reversePayrollRun($payroll, $request->user());
            }
            $payroll->advances()->where('status', 'posted')->update(['payroll_run_id' => null]);
            $payroll->update(['status' => 'cancelled', 'updated_by' => $request->user()->id]);
            $audit->record('payroll_cancelled', $payroll, $before, $payroll->fresh()->toArray());
        });

        return back()->with('status', __('Payroll cancelled.'));
    }

    public function export(Request $request, SimpleXlsxExporter $exporter)
    {
        $rows = $this->filteredQuery($request)->get()->flatMap(
            fn (PayrollRun $run) => $run->lines->map(function ($line) use ($run) {
                $advances = (float) $run->advances
                    ->where('status', 'posted')
                    ->where('employee_id', $line->employee_id)
                    ->sum('amount');

                return [
                    $run->period_year.'-'.str_pad((string) $run->period_month, 2, '0', STR_PAD_LEFT),
                    $line->employee->localized_name,
                    number_format((float) $line->gross_salary, 3, '.', ''),
                    number_format((float) $line->allowances, 3, '.', ''),
                    number_format((float) $line->employee_social_security, 3, '.', ''),
                    number_format((float) $line->deductions, 3, '.', ''),
                    number_format((float) $line->employer_social_security, 3, '.', ''),
                    number_format((float) $line->net_salary, 3, '.', ''),
                    number_format($advances, 3, '.', ''),
                    number_format(max(0, (float) $line->net_salary - $advances), 3, '.', ''),
                    __(ucfirst($run->status)),
                ];
            })
        );

        return $exporter->download('payroll-'.now()->format('Y-m-d').'.xlsx', [
            __('Period'), __('Employee'), __('Gross Salary'), __('Allowances'),
            __('Employee Social Security'), __('Other Deductions'),
            __('Employer Social Security'), __('Net Salary'), __('Advances'),
            __('Remaining'), __('Status'),
        ], $rows->all(), [[__('Payroll Report'), now()->toDateString()]]);
    }

    private function validated(Request $request, ?PayrollRun $payroll = null): array
    {
        $data = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'payroll_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array'],
            'lines.*.include' => ['nullable', 'boolean'],
            'lines.*.employee_id' => ['required', 'exists:employees,id'],
            'lines.*.gross_salary' => ['nullable', 'numeric', 'min:0'],
            'lines.*.allowances' => ['nullable', 'numeric', 'min:0'],
            'lines.*.employee_social_security' => ['nullable', 'numeric', 'min:0'],
            'lines.*.deductions' => ['nullable', 'numeric', 'min:0'],
            'lines.*.employer_social_security' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);

        $duplicate = PayrollRun::where('period_year', $data['period_year'])
            ->where('period_month', $data['period_month'])
            ->when($payroll, fn ($query) => $query->whereKeyNot($payroll->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['period_month' => __('A payroll run already exists for this period.')]);
        }

        $lines = collect($data['lines'])
            ->filter(fn ($line) => (bool) ($line['include'] ?? false))
            ->map(function ($line) {
                $gross = round((float) ($line['gross_salary'] ?? 0), 3);
                $allowances = round((float) ($line['allowances'] ?? 0), 3);
                $employeeSocial = round((float) ($line['employee_social_security'] ?? 0), 3);
                $otherDeductions = round((float) ($line['deductions'] ?? 0), 3);
                $totalDeductions = round($employeeSocial + $otherDeductions, 3);
                if (($gross + $allowances) <= 0 || $totalDeductions > ($gross + $allowances)) {
                    throw ValidationException::withMessages(['lines' => __('Payroll deductions cannot exceed gross salary plus allowances.')]);
                }

                return [
                    'employee_id' => $line['employee_id'],
                    'gross_salary' => $gross,
                    'allowances' => $allowances,
                    'employee_social_security' => $employeeSocial,
                    'deductions' => $otherDeductions,
                    'employer_social_security' => round((float) ($line['employer_social_security'] ?? 0), 3),
                    'net_salary' => round($gross + $allowances - $totalDeductions, 3),
                    'notes' => $line['notes'] ?? null,
                ];
            })->values()->all();

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('Select at least one employee for payroll.')]);
        }
        $this->assertAdvanceLimits($data['period_year'], $data['period_month'], $lines);

        return [[
            'period_year' => $data['period_year'],
            'period_month' => $data['period_month'],
            'payroll_date' => $data['payroll_date'],
            'notes' => $data['notes'] ?? null,
        ], $lines];
    }

    private function recalculateTotals(PayrollRun $payroll): void
    {
        $lines = $payroll->lines()->get();
        $payroll->update([
            'total_gross' => round((float) $lines->sum('gross_salary'), 3),
            'total_allowances' => round((float) $lines->sum('allowances'), 3),
            'total_deductions' => round((float) $lines->sum('deductions'), 3),
            'total_employee_social_security' => round((float) $lines->sum('employee_social_security'), 3),
            'total_employer_social_security' => round((float) $lines->sum('employer_social_security'), 3),
            'total_net' => round((float) $lines->sum('net_salary'), 3),
        ]);
    }

    private function assertAdvanceLimits(int $year, int $month, array $lines): void
    {
        $netByEmployee = collect($lines)->pluck('net_salary', 'employee_id');

        SalaryAdvance::whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->where('status', 'posted')
            ->selectRaw('employee_id, SUM(amount) as total')
            ->groupBy('employee_id')
            ->get()
            ->each(function ($advance) use ($netByEmployee) {
                if (! $netByEmployee->has($advance->employee_id)
                    || (float) $advance->total > (float) $netByEmployee[$advance->employee_id]) {
                    throw ValidationException::withMessages([
                        'lines' => __('Payroll must include every employee with advances, and net salary cannot be below advances.'),
                    ]);
                }
            });
    }

    private function syncSalaryAdvances(PayrollRun $payroll): void
    {
        $employeeIds = $payroll->lines()->pluck('employee_id');

        $payroll->advances()->where('status', 'posted')->update(['payroll_run_id' => null]);
        SalaryAdvance::whereNull('payroll_run_id')
            ->whereYear('payment_date', $payroll->period_year)
            ->whereMonth('payment_date', $payroll->period_month)
            ->where('status', 'posted')
            ->whereIn('employee_id', $employeeIds)
            ->update(['payroll_run_id' => $payroll->id]);
    }

    private function assertDraft(PayrollRun $payroll): void
    {
        if ($payroll->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Only draft payroll can be edited or posted.')]);
        }
    }

    private function formData(PayrollRun $payroll): array
    {
        $existingLines = $payroll->exists ? $payroll->lines->keyBy('employee_id') : collect();

        return [
            'payroll' => $payroll,
            'employees' => Employee::where('is_active', true)
                ->when($existingLines->isNotEmpty(), fn ($query) => $query->orWhereIn('id', $existingLines->keys()))
                ->orderBy('name_en')
                ->get(),
            'existingLines' => $existingLines,
        ];
    }

    private function filteredQuery(Request $request)
    {
        return PayrollRun::with(['lines.employee', 'advances', 'creator'])
            ->when($request->input('year'), fn ($query, $year) => $query->where('period_year', $year))
            ->when($request->input('month'), fn ($query, $month) => $query->where('period_month', $month))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('employee_id'), fn ($query, $employee) => $query->whereHas('lines', fn ($query) => $query->where('employee_id', $employee)));
    }
}

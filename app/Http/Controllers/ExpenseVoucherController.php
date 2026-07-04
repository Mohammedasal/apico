<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\CashAccount;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use App\Services\SimpleXlsxExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseVoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        return view('accounting.expenses.index', [
            'vouchers' => (clone $query)->latest('expense_date')->latest('id')->paginate(30)->withQueryString(),
            'totalAmount' => round((float) (clone $query)->where('status', 'posted')->sum('amount'), 3),
            'totalPaid' => round((float) (clone $query)->where('status', 'posted')->sum('paid_amount'), 3),
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name_en')->get(),
            'filters' => $request->only(['from', 'to', 'expense_category_id', 'payment_type', 'payment_status', 'status']),
            'exportColumns' => $this->exportColumns(),
        ]);
    }

    public function export(Request $request, SimpleXlsxExporter $exporter)
    {
        $columns = collect($this->exportColumns())
            ->only($request->input('columns', array_keys($this->exportColumns())))
            ->all() ?: $this->exportColumns();
        $rows = $this->filteredQuery($request)
            ->latest('expense_date')
            ->latest('id')
            ->get()
            ->map(fn (ExpenseVoucher $voucher) => [
                'date' => $voucher->expense_date->toDateString(),
                'voucher_no' => $voucher->voucher_no,
                'category' => $voucher->category->localized_name,
                'amount' => number_format((float) $voucher->amount, 3, '.', ''),
                'paid_amount' => number_format((float) $voucher->paid_amount, 3, '.', ''),
                'outstanding' => number_format((float) $voucher->amount - (float) $voucher->paid_amount, 3, '.', ''),
                'payment_type' => __(ucwords(str_replace('_', ' ', $voucher->payment_type))),
                'payment_status' => __(ucwords(str_replace('_', ' ', $voucher->payment_status))),
                'voucher_status' => __(ucfirst($voucher->status)),
                'account' => $voucher->category->defaultAccount->code.' - '.$voucher->category->defaultAccount->localized_name,
                'reference' => $voucher->reference,
                'notes' => $voucher->notes,
            ]);

        return $exporter->download(
            'expense-vouchers-'.now()->format('Y-m-d').'.xlsx',
            array_values($columns),
            $rows->map(fn (array $row) => collect(array_keys($columns))->map(fn (string $column) => $row[$column] ?? '')->all())->all(),
            [
                [__('Expense Report'), ($request->input('from') ?: __('Beginning')).' '.__('to').' '.($request->input('to') ?: now()->toDateString())],
                [__('Generated Date'), now()->toDateString()],
            ]
        );
    }

    public function create()
    {
        return view('accounting.expenses.form', $this->formData(new ExpenseVoucher([
            'expense_date' => now(),
            'payment_status' => 'paid',
            'payment_type' => 'cash',
            'status' => 'posted',
        ])));
    }

    public function store(Request $request, AccountingPostingService $posting, AuditService $audit)
    {
        $data = $this->validated($request);

        $voucher = DB::transaction(function () use ($data, $request, $posting, $audit) {
            $voucher = ExpenseVoucher::create($data + [
                'voucher_no' => $this->nextVoucherNumber($data['expense_date']),
                'status' => 'posted',
                'created_by' => $request->user()->id,
            ]);
            $posting->postExpenseVoucher($voucher, $request->user());
            $audit->record('expense_voucher_created', $voucher, null, $voucher->toArray());

            return $voucher;
        });

        return redirect()->route('accounting.expenses.show', $voucher)->with('status', __('Expense voucher posted.'));
    }

    public function show(ExpenseVoucher $expense)
    {
        return view('accounting.expenses.show', [
            'voucher' => $expense->load(['category.defaultAccount', 'cashAccount', 'bankAccount', 'payableAccount', 'creator', 'editor']),
            'journalEntries' => JournalEntry::with('lines')
                ->where('source_module', 'accounting')
                ->where('source_type', class_basename($expense))
                ->where('source_id', $expense->id)
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function edit(ExpenseVoucher $expense)
    {
        abort_if($expense->status !== 'posted', 422, 'Cancelled expense vouchers cannot be edited.');

        return view('accounting.expenses.form', $this->formData($expense));
    }

    public function update(Request $request, ExpenseVoucher $expense, AccountingPostingService $posting, AuditService $audit)
    {
        if ($expense->status !== 'posted') {
            throw ValidationException::withMessages(['status' => __('Cancelled expense vouchers cannot be edited.')]);
        }

        $data = $this->validated($request);
        DB::transaction(function () use ($expense, $data, $request, $posting, $audit) {
            $before = $expense->toArray();
            $expense->update($data + ['updated_by' => $request->user()->id]);
            $posting->repostExpenseVoucher($expense->fresh(), $request->user());
            $audit->record('expense_voucher_updated', $expense, $before, $expense->fresh()->toArray());
        });

        return redirect()->route('accounting.expenses.show', $expense)->with('status', __('Expense voucher updated.'));
    }

    public function cancel(ExpenseVoucher $expense, Request $request, AccountingPostingService $posting, AuditService $audit)
    {
        if ($expense->status === 'cancelled') {
            return back()->with('status', __('Expense voucher already cancelled.'));
        }

        DB::transaction(function () use ($expense, $request, $posting, $audit) {
            $before = $expense->toArray();
            $posting->reverseExpenseVoucher($expense, $request->user());
            $expense->update(['status' => 'cancelled', 'updated_by' => $request->user()->id]);
            $audit->record('expense_voucher_cancelled', $expense, $before, $expense->fresh()->toArray());
        });

        return redirect()->route('accounting.expenses.show', $expense)->with('status', __('Expense voucher cancelled.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['required', 'in:paid,unpaid,partially_paid'],
            'payment_type' => ['required', 'in:cash,bank_transfer,cheque,credit'],
            'cash_account_id' => ['nullable', 'exists:cash_accounts,id'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'payable_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'cheque_due_date' => ['nullable', 'date', 'required_if:payment_type,cheque'],
            'cheque_bank' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $amount = round((float) $data['amount'], 3);
        $paidAmount = round((float) ($data['paid_amount'] ?? 0), 3);

        if ($data['payment_status'] === 'paid') {
            if ($data['payment_type'] === 'credit') {
                throw ValidationException::withMessages(['payment_type' => __('Paid expenses cannot use credit payment type.')]);
            }
            $paidAmount = $amount;
        } elseif ($data['payment_status'] === 'unpaid') {
            $data['payment_type'] = 'credit';
            $paidAmount = 0;
        } elseif ($paidAmount <= 0 || $paidAmount >= $amount || $data['payment_type'] === 'credit') {
            throw ValidationException::withMessages(['paid_amount' => __('Partial payment must be greater than zero and less than the total amount.')]);
        }

        $data['amount'] = $amount;
        $data['paid_amount'] = $paidAmount;

        return $data;
    }

    private function formData(ExpenseVoucher $voucher): array
    {
        return [
            'voucher' => $voucher,
            'categories' => ExpenseCategory::where('is_active', true)->orderBy('name_en')->get(),
            'cashAccounts' => CashAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderByDesc('is_default')->orderBy('name_en')->get(),
            'payableAccounts' => ChartOfAccount::where('type', 'liability')->where('is_active', true)->where('is_posting', true)->orderBy('code')->get(),
        ];
    }

    private function nextVoucherNumber(string $date): string
    {
        return 'EV-'.date('Ym', strtotime($date)).'-'.str_pad((string) (((int) ExpenseVoucher::max('id')) + 1), 6, '0', STR_PAD_LEFT);
    }

    private function filteredQuery(Request $request)
    {
        return ExpenseVoucher::with(['category.defaultAccount', 'cashAccount', 'bankAccount', 'creator'])
            ->when($request->input('from'), fn ($query, $from) => $query->whereDate('expense_date', '>=', $from))
            ->when($request->input('to'), fn ($query, $to) => $query->whereDate('expense_date', '<=', $to))
            ->when($request->input('expense_category_id'), fn ($query, $category) => $query->where('expense_category_id', $category))
            ->when($request->input('payment_type'), fn ($query, $type) => $query->where('payment_type', $type))
            ->when($request->input('payment_status'), fn ($query, $status) => $query->where('payment_status', $status))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status));
    }

    private function exportColumns(): array
    {
        return [
            'date' => __('Date'),
            'voucher_no' => __('Voucher No.'),
            'category' => __('Category'),
            'amount' => __('Amount'),
            'paid_amount' => __('Paid Amount'),
            'outstanding' => __('Outstanding Amount'),
            'payment_type' => __('Payment Type'),
            'payment_status' => __('Payment Status'),
            'voucher_status' => __('Voucher Status'),
            'account' => __('Account'),
            'reference' => __('Reference'),
            'notes' => __('Notes'),
        ];
    }
}

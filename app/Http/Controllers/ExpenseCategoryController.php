<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        return view('accounting.expense-categories.index', [
            'categories' => ExpenseCategory::with('defaultAccount')->orderBy('name_en')->get(),
        ]);
    }

    public function create()
    {
        return view('accounting.expense-categories.form', [
            'category' => new ExpenseCategory(['is_active' => true]),
            'accounts' => $this->expenseAccounts(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $category = ExpenseCategory::create($this->validated($request) + ['created_by' => $request->user()->id]);
        $audit->record('expense_category_created', $category, null, $category->toArray());

        return redirect()->route('accounting.expense-categories.index')->with('status', __('Expense category created.'));
    }

    public function edit(ExpenseCategory $expenseCategory)
    {
        return view('accounting.expense-categories.form', [
            'category' => $expenseCategory,
            'accounts' => $this->expenseAccounts(),
        ]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory, AuditService $audit)
    {
        $before = $expenseCategory->toArray();
        $expenseCategory->update($this->validated($request) + ['updated_by' => $request->user()->id]);
        $audit->record('expense_category_updated', $expenseCategory, $before, $expenseCategory->fresh()->toArray());

        return redirect()->route('accounting.expense-categories.index')->with('status', __('Expense category updated.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'default_account_id' => [
                'required',
                Rule::exists('chart_of_accounts', 'id')->where(
                    fn ($query) => $query->where('type', 'expense')->where('is_active', true)->where('is_posting', true)
                ),
            ],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function expenseAccounts()
    {
        return ChartOfAccount::where('type', 'expense')
            ->where('is_active', true)
            ->where('is_posting', true)
            ->orderBy('code')
            ->get();
    }
}

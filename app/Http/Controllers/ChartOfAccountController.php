<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $type = $request->input('type');
        $status = $request->input('status');

        return view('accounting.accounts.index', [
            'accounts' => ChartOfAccount::with('parent')
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('name_ar', 'like', "%{$search}%");
                }))
                ->when($type, fn ($query) => $query->where('type', $type))
                ->when($status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            'search' => $search,
            'type' => $type,
            'status' => $status,
        ]);
    }

    public function create()
    {
        return view('accounting.accounts.form', [
            'account' => new ChartOfAccount(['is_active' => true, 'is_posting' => true]),
            'parents' => ChartOfAccount::where('is_posting', false)->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $account = ChartOfAccount::create($this->validated($request) + ['created_by' => $request->user()->id]);
        $audit->record('account_created', $account, null, $account->toArray());

        return redirect()->route('accounting.accounts.index')->with('status', __('Account created.'));
    }

    public function edit(ChartOfAccount $account)
    {
        return view('accounting.accounts.form', [
            'account' => $account,
            'parents' => ChartOfAccount::whereKeyNot($account->id)
                ->where('is_posting', false)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(Request $request, ChartOfAccount $account, AuditService $audit)
    {
        $before = $account->toArray();
        $account->update($this->validated($request, $account) + ['updated_by' => $request->user()->id]);
        $audit->record('account_updated', $account, $before, $account->fresh()->toArray());

        return redirect()->route('accounting.accounts.index')->with('status', __('Account updated.'));
    }

    private function validated(Request $request, ?ChartOfAccount $account = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('chart_of_accounts')->ignore($account?->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(ChartOfAccount::TYPES)],
            'normal_balance' => ['required', 'in:debit,credit'],
            'is_posting' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['parent_id'] = $data['parent_id'] ?? null;

        if ($data['parent_id']) {
            $parent = ChartOfAccount::findOrFail($data['parent_id']);

            if ($parent->is_posting) {
                throw ValidationException::withMessages(['parent_id' => __('Parent account must be a grouping account.')]);
            }
        }

        if ($account?->children()->exists() && (bool) $data['is_posting']) {
            throw ValidationException::withMessages(['is_posting' => __('An account with children cannot be a posting account.')]);
        }

        return $data;
    }
}

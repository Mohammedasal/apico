<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AuditService;
use App\Services\ExpenseSourceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::orderBy('key')->get()->keyBy('key');

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'currency' => ['required', 'string', 'max:10'],
            'default_granulation_cost_per_kg' => ['required', 'numeric', 'min:0'],
            'high_balance_threshold' => ['required', 'numeric', 'min:0'],
            'allow_stock_override' => ['required', 'boolean'],
            'accounting_enabled' => ['required', 'boolean'],
            'monthly_expense_source' => ['required', Rule::in([
                ExpenseSourceService::LEGACY,
                ExpenseSourceService::ACCOUNTING,
                ExpenseSourceService::CUTOVER,
            ])],
            'expense_cutover_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($data['monthly_expense_source'] === ExpenseSourceService::CUTOVER) {
            if (blank($data['expense_cutover_date'] ?? null)
                || ! str_ends_with($data['expense_cutover_date'], '-01')) {
                throw ValidationException::withMessages([
                    'expense_cutover_date' => __('The cutover date must be the first day of a month.'),
                ]);
            }
        }

        $before = Setting::whereIn('key', array_keys($data))->pluck('value', 'key')->all();
        DB::transaction(function () use ($data, $before, $audit) {
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            }

            $after = Setting::whereIn('key', array_keys($data))->pluck('value', 'key')->all();
            $audit->recordEvent('settings_updated', Setting::class, null, $before, $after);
        });

        return redirect()->route('settings.index')->with('status', 'Settings updated.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountingPeriodController extends Controller
{
    public function index()
    {
        return view('accounting.periods.index', [
            'periods' => AccountingPeriod::with('locker')->orderByDesc('period_year')->orderByDesc('period_month')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'between:1,12'],
        ]);
        $start = Carbon::create($data['period_year'], $data['period_month'], 1);
        $period = AccountingPeriod::firstOrCreate(
            ['period_year' => $start->year, 'period_month' => $start->month],
            ['start_date' => $start->toDateString(), 'end_date' => $start->copy()->endOfMonth()->toDateString(), 'status' => 'open']
        );
        $audit->record('accounting_period_created', $period, null, $period->toArray());

        return back()->with('status', __('Accounting period created.'));
    }

    public function lock(AccountingPeriod $period, Request $request, AuditService $audit)
    {
        $before = $period->toArray();
        $period->update(['status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()->id]);
        $audit->record('accounting_period_locked', $period, $before, $period->fresh()->toArray());

        return back()->with('status', __('Accounting period locked.'));
    }

    public function unlock(AccountingPeriod $period, AuditService $audit)
    {
        $before = $period->toArray();
        $period->update(['status' => 'open', 'locked_at' => null, 'locked_by' => null]);
        $audit->record('accounting_period_unlocked', $period, $before, $period->fresh()->toArray());

        return back()->with('status', __('Accounting period unlocked.'));
    }
}

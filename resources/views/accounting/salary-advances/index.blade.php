@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Salary Advances') }}</h1><div class="muted">{{ __('Record salary payments before the monthly payroll is prepared.') }}</div></div>
    <a class="button" href="{{ route('accounting.salary-advances.create') }}">{{ __('Add Salary Advance') }}</a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="muted">{{ __('Posted Advances') }}</div><div class="kpi">{{ number_format($totalPosted, 3) }}</div></div>
</div>

<form class="filters" method="get">
    <div><label>{{ __('Year') }}</label><input type="number" name="year" value="{{ $filters['year'] }}"></div>
    <div><label>{{ __('Month') }}</label><input type="number" min="1" max="12" name="month" value="{{ $filters['month'] }}"></div>
    <div><label>{{ __('Employee') }}</label><select class="searchable-select" name="employee_id"><option value="">{{ __('All') }}</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected(($filters['employee_id'] ?? '') == $employee->id)>{{ $employee->localized_name }}</option>@endforeach</select></div>
    <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option><option value="posted" @selected(($filters['status'] ?? '') === 'posted')>{{ __('Posted') }}</option><option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
    <div><button>{{ __('Filter') }}</button></div>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Payment Type') }}</th><th>{{ __('Account') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Payroll Period') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse ($advances as $advance)
        <tr>
            <td>{{ $advance->payment_date->toDateString() }}</td>
            <td>{{ $advance->employee->localized_name }}</td>
            <td>{{ number_format($advance->amount, 3) }}</td>
            <td>{{ $advance->payment_type === 'cash' ? __('Cash') : __('Bank Transfer') }}</td>
            <td>{{ $advance->cashAccount?->localized_name ?? $advance->bankAccount?->localized_name ?? __('Default Account') }}</td>
            <td>{{ $advance->reference ?: '-' }}</td>
            <td>@if ($advance->payrollRun)<a href="{{ route('accounting.payroll.show', $advance->payrollRun) }}">{{ sprintf('%04d-%02d', $advance->payrollRun->period_year, $advance->payrollRun->period_month) }}</a>@else<span class="muted">{{ __('Pending Month-End Payroll') }}</span>@endif</td>
            <td>{{ __(ucfirst($advance->status)) }}</td>
            <td>@if ($advance->status === 'posted' && (! $advance->payrollRun || $advance->payrollRun->status === 'draft'))<form method="post" action="{{ route('accounting.salary-advances.cancel', $advance) }}" onsubmit="return confirm('{{ __('Cancel this salary advance and reverse its journal?') }}')">@csrf<button>{{ __('Cancel') }}</button></form>@endif</td>
        </tr>
    @empty
        <tr><td colspan="9">{{ __('No salary advances for this period.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $advances->links() }}
@endsection

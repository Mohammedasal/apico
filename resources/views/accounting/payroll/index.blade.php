@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Payroll') }}</h1><div class="muted">{{ __('Monthly salary accruals and payments.') }}</div></div>
    <div style="display:flex;gap:8px"><a class="button" href="{{ route('accounting.salary-advances.create') }}">{{ __('Add Salary Advance') }}</a><a class="button" href="{{ route('accounting.payroll.create') }}">{{ __('New Payroll Run') }}</a></div>
</div>
<form class="filters" method="get">
    <div><label>{{ __('Year') }}</label><input type="number" name="year" value="{{ $filters['year'] ?? '' }}"></div>
    <div><label>{{ __('Month') }}</label><input type="number" min="1" max="12" name="month" value="{{ $filters['month'] ?? '' }}"></div>
    <div><label>{{ __('Employee') }}</label><select class="searchable-select" name="employee_id"><option value="">{{ __('All') }}</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected(($filters['employee_id'] ?? '') == $employee->id)>{{ $employee->localized_name }}</option>@endforeach</select></div>
    <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option>@foreach (['draft','posted','paid','cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></div>
    <div><button>{{ __('Filter') }}</button></div>
    <div><a class="button" href="{{ route('accounting.payroll.export', array_filter($filters)) }}">{{ __('Export Excel') }}</a></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Payroll Date') }}</th><th>{{ __('Employees') }}</th><th>{{ __('Gross') }}</th><th>{{ __('Employee Social Security') }}</th><th>{{ __('Employer Social Security') }}</th><th>{{ __('Other Deductions') }}</th><th>{{ __('Net Salary') }}</th><th>{{ __('Advances') }}</th><th>{{ __('Remaining') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @forelse ($runs as $run)
        <tr>
            <td><a href="{{ route('accounting.payroll.show', $run) }}">{{ sprintf('%04d-%02d', $run->period_year, $run->period_month) }}</a></td>
            <td>{{ $run->payroll_date->toDateString() }}</td>
            <td>{{ $run->lines->count() }}</td>
            <td>{{ number_format($run->total_gross + $run->total_allowances, 3) }}</td>
            <td>{{ number_format($run->total_employee_social_security, 3) }}</td>
            <td>{{ number_format($run->total_employer_social_security, 3) }}</td>
            <td>{{ number_format($run->total_deductions, 3) }}</td>
            <td>{{ number_format($run->total_net, 3) }}</td>
            <td>{{ number_format($run->total_advances, 3) }}</td>
            <td><strong>{{ number_format($run->remaining_salary, 3) }}</strong></td>
            <td>{{ __(ucfirst($run->status)) }}</td>
        </tr>
    @empty
        <tr><td colspan="11">{{ __('No payroll runs.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $runs->links() }}
@endsection

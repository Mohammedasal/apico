@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Employees') }}</h1><div class="muted">{{ __('Factory employee records used by payroll.') }}</div></div>
    <a class="button" href="{{ route('accounting.employees.create') }}">{{ __('New Employee') }}</a>
</div>
<form class="filters" method="get">
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search }}" placeholder="{{ __('Name, phone, or position') }}"></div>
    <div><button>{{ __('Search') }}</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Arabic Name') }}</th><th>{{ __('Position') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Base Salary') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>
    @forelse ($employees as $employee)
        <tr>
            <td>{{ $employee->name_en }}</td>
            <td dir="rtl">{{ $employee->name_ar }}</td>
            <td>{{ $employee->position }}</td>
            <td>{{ $employee->phone }}</td>
            <td>{{ number_format($employee->base_salary, 3) }}</td>
            <td>{{ $employee->is_active ? __('Active') : __('Inactive') }}</td>
            <td><a href="{{ route('accounting.employees.edit', $employee) }}">{{ __('Edit') }}</a></td>
        </tr>
    @empty
        <tr><td colspan="7">{{ __('No employees.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection

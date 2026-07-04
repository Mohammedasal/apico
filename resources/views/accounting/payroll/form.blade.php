@extends('layouts.app')

@section('content')
@php $isEdit = $payroll->exists; @endphp
<h1>{{ $isEdit ? __('Edit Draft Payroll') : __('New Payroll Run') }}</h1>
<form method="post" action="{{ $isEdit ? route('accounting.payroll.update', $payroll) : route('accounting.payroll.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Year') }}</label><input type="number" name="period_year" value="{{ old('period_year', $payroll->period_year) }}"></div>
        <div><label>{{ __('Month') }}</label><input type="number" min="1" max="12" name="period_month" value="{{ old('period_month', $payroll->period_month) }}"></div>
        <div><label>{{ __('Payroll Date') }}</label><input type="date" name="payroll_date" value="{{ old('payroll_date', $payroll->payroll_date?->toDateString()) }}"></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $payroll->notes) }}</textarea></div>
    </div>
    @error('period_month')<div class="error">{{ $message }}</div>@enderror
    @error('lines')<div class="error">{{ $message }}</div>@enderror
    <div class="table-wrap" style="margin-top:14px">
    <table>
        <thead><tr><th>{{ __('Include') }}</th><th>{{ __('Employee') }}</th><th>{{ __('Gross Salary') }}</th><th>{{ __('Allowances') }}</th><th>{{ __('Deductions') }}</th><th>{{ __('Employer Social Security') }}</th><th>{{ __('Net Salary') }}</th><th>{{ __('Notes') }}</th></tr></thead>
        <tbody>
        @foreach ($employees as $index => $employee)
            @php
                $line = $existingLines->get($employee->id);
                $included = old("lines.$index.include", $line ? '1' : '0');
                $gross = old("lines.$index.gross_salary", $line?->gross_salary ?? $employee->base_salary);
            @endphp
            <tr class="payroll-line">
                <td><input type="hidden" name="lines[{{ $index }}][employee_id]" value="{{ $employee->id }}"><input type="checkbox" name="lines[{{ $index }}][include]" value="1" @checked($included)></td>
                <td>{{ $employee->localized_name }}<div class="muted">{{ $employee->position }}</div></td>
                <td><input class="gross" type="number" step="0.001" min="0" name="lines[{{ $index }}][gross_salary]" value="{{ $gross }}"></td>
                <td><input class="allowances" type="number" step="0.001" min="0" name="lines[{{ $index }}][allowances]" value="{{ old("lines.$index.allowances", $line?->allowances ?? 0) }}"></td>
                <td><input class="deductions" type="number" step="0.001" min="0" name="lines[{{ $index }}][deductions]" value="{{ old("lines.$index.deductions", $line?->deductions ?? 0) }}"></td>
                <td><input type="number" step="0.001" min="0" name="lines[{{ $index }}][employer_social_security]" value="{{ old("lines.$index.employer_social_security", $line?->employer_social_security ?? 0) }}"></td>
                <td><strong class="net">{{ number_format((float) ($line?->net_salary ?? $employee->base_salary), 3) }}</strong></td>
                <td><input name="lines[{{ $index }}][notes]" value="{{ old("lines.$index.notes", $line?->notes) }}"></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <p><button>{{ __('Save Draft Payroll') }}</button></p>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.payroll-line').forEach(function (row) {
        const inputs = row.querySelectorAll('.gross, .allowances, .deductions');
        const net = row.querySelector('.net');
        function calculate() {
            const gross = Number.parseFloat(row.querySelector('.gross').value) || 0;
            const allowances = Number.parseFloat(row.querySelector('.allowances').value) || 0;
            const deductions = Number.parseFloat(row.querySelector('.deductions').value) || 0;
            net.textContent = (gross + allowances - deductions).toFixed(3);
        }
        inputs.forEach(function (input) { input.addEventListener('input', calculate); });
        calculate();
    });
});
</script>
@endsection

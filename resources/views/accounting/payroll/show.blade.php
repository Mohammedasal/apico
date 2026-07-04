@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Payroll') }} {{ sprintf('%04d-%02d', $payroll->period_year, $payroll->period_month) }}</h1><div class="muted">{{ $payroll->payroll_date->toDateString() }} | {{ __(ucfirst($payroll->status)) }}</div></div>
    <div style="display:flex;gap:8px">
        @if ($payroll->status === 'draft')
            <a class="button" href="{{ route('accounting.payroll.edit', $payroll) }}">{{ __('Edit') }}</a>
            <form method="post" action="{{ route('accounting.payroll.post', $payroll) }}">@csrf<button>{{ __('Post Accrual') }}</button></form>
        @endif
        @if ($payroll->status !== 'cancelled')
            <form method="post" action="{{ route('accounting.payroll.cancel', $payroll) }}" onsubmit="return confirm('{{ __('Cancel payroll and reverse its journals?') }}')">@csrf<button>{{ __('Cancel Payroll') }}</button></form>
        @endif
    </div>
</div>
<div class="kpi-grid">
    <div class="card kpi-card"><div class="muted">{{ __('Gross Salary') }}</div><div class="kpi">{{ number_format($payroll->total_gross, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Allowances') }}</div><div class="kpi">{{ number_format($payroll->total_allowances, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Deductions') }}</div><div class="kpi">{{ number_format($payroll->total_deductions, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Net Salary') }}</div><div class="kpi">{{ number_format($payroll->total_net, 3) }}</div></div>
</div>
@if ($payroll->status === 'posted')
<form class="filters" method="post" action="{{ route('accounting.payroll.pay', $payroll) }}">
    @csrf
    <div><label>{{ __('Payment Date') }}</label><input type="date" name="payment_date" value="{{ now()->toDateString() }}"></div>
    <div><label>{{ __('Payment Type') }}</label><select name="payment_type"><option value="cash">{{ __('Cash') }}</option><option value="bank_transfer">{{ __('Bank Transfer') }}</option></select></div>
    <div><label>{{ __('Cash Account') }}</label><select class="searchable-select" name="cash_account_id"><option value="">{{ __('Use Default') }}</option>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->localized_name }}</option>@endforeach</select></div>
    <div><label>{{ __('Bank Account') }}</label><select class="searchable-select" name="bank_account_id"><option value="">{{ __('Use Default') }}</option>@foreach ($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->localized_name }}</option>@endforeach</select></div>
    <div><label>{{ __('Reference') }}</label><input name="payment_reference"></div>
    <div><button>{{ __('Pay Payroll') }}</button></div>
</form>
@endif
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Gross Salary') }}</th><th>{{ __('Allowances') }}</th><th>{{ __('Deductions') }}</th><th>{{ __('Employer Social Security') }}</th><th>{{ __('Net Salary') }}</th></tr></thead>
    <tbody>@foreach ($payroll->lines as $line)<tr><td>{{ $line->employee->localized_name }}</td><td>{{ number_format($line->gross_salary, 3) }}</td><td>{{ number_format($line->allowances, 3) }}</td><td>{{ number_format($line->deductions, 3) }}</td><td>{{ number_format($line->employer_social_security, 3) }}</td><td>{{ number_format($line->net_salary, 3) }}</td></tr>@endforeach</tbody>
</table>
</div>
@if ($journalEntries->isNotEmpty())
<div class="section-title"><h2>{{ __('Generated Journals') }}</h2></div>
<div class="table-wrap"><table><thead><tr><th>{{ __('Entry No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th></tr></thead><tbody>@foreach ($journalEntries as $entry)<tr><td><a href="{{ route('accounting.journals.show', $entry) }}">{{ $entry->entry_no }}</a></td><td>{{ $entry->entry_date->toDateString() }}</td><td>{{ $entry->posting_type }}</td><td>{{ __(ucfirst($entry->status)) }}</td><td>{{ number_format($entry->total_debit, 3) }}</td><td>{{ number_format($entry->total_credit, 3) }}</td></tr>@endforeach</tbody></table></div>
@endif
@endsection

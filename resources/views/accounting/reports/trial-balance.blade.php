@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Trial Balance') }}</h1><div class="muted">{{ __('Opening balances, period movement, and closing balances for posted journals.') }}</div></div>
</div>

<form class="filters" method="get">
    <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $filters['from'] }}"></div>
    <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $filters['to'] }}"></div>
    <div><label>{{ __('Account Type') }}</label><select name="account_type"><option value="">{{ __('All') }}</option>@foreach (\App\Models\ChartOfAccount::TYPES as $type)<option value="{{ $type }}" @selected($filters['account_type'] === $type)>{{ __(ucfirst($type)) }}</option>@endforeach</select></div>
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Code or account name') }}"></div>
    <label class="check"><input type="checkbox" name="include_zero" value="1" @checked($filters['include_zero'])> {{ __('Include Zero Balances') }}</label>
    <div><button>{{ __('Apply') }}</button></div>
</form>

<div class="grid">
    <div class="card kpi-card"><div class="muted">{{ __('Period Debit') }}</div><div class="kpi">{{ number_format($totals['period_debit'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Period Credit') }}</div><div class="kpi">{{ number_format($totals['period_credit'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Closing Debit') }}</div><div class="kpi">{{ number_format($totals['closing_debit'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Closing Credit') }}</div><div class="kpi">{{ number_format($totals['closing_credit'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Balance Difference') }}</div><div @class(['kpi', 'amount-positive' => abs($difference) < 0.0005, 'amount-negative' => abs($difference) >= 0.0005])>{{ number_format($difference, 3) }}</div><div class="muted">{{ abs($difference) < 0.0005 ? __('Trial balance is balanced.') : __('Trial balance is not balanced.') }}</div></div>
</div>

<div class="section-title"><h2>{{ __('Export to Excel') }}</h2></div>
<form class="filters" method="get" action="{{ route('accounting.reports.trial-balance.export') }}">
    <input type="hidden" name="from" value="{{ $filters['from'] }}">
    <input type="hidden" name="to" value="{{ $filters['to'] }}">
    <input type="hidden" name="account_type" value="{{ $filters['account_type'] }}">
    <input type="hidden" name="q" value="{{ $filters['q'] }}">
    @if ($filters['include_zero'])<input type="hidden" name="include_zero" value="1">@endif
    @foreach ($exportColumns as $key => $label)
        <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th>{{ __('Account Type') }}</th><th>{{ __('Opening Debit') }}</th><th>{{ __('Opening Credit') }}</th><th>{{ __('Period Debit') }}</th><th>{{ __('Period Credit') }}</th><th>{{ __('Closing Debit') }}</th><th>{{ __('Closing Credit') }}</th></tr></thead>
    <tbody>
    @forelse ($rows as $row)
        <tr><td>{{ $row['code'] }}</td><td>{{ $row['account'] }}</td><td>{{ $row['type'] }}</td><td>{{ number_format($row['opening_debit'], 3) }}</td><td>{{ number_format($row['opening_credit'], 3) }}</td><td>{{ number_format($row['period_debit'], 3) }}</td><td>{{ number_format($row['period_credit'], 3) }}</td><td>{{ number_format($row['closing_debit'], 3) }}</td><td>{{ number_format($row['closing_credit'], 3) }}</td></tr>
    @empty
        <tr><td colspan="9">{{ __('No trial balance activity for this period.') }}</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><th>{{ __('Totals') }}</th><th colspan="2"></th><th>{{ number_format($totals['opening_debit'], 3) }}</th><th>{{ number_format($totals['opening_credit'], 3) }}</th><th>{{ number_format($totals['period_debit'], 3) }}</th><th>{{ number_format($totals['period_credit'], 3) }}</th><th>{{ number_format($totals['closing_debit'], 3) }}</th><th>{{ number_format($totals['closing_credit'], 3) }}</th></tr></tfoot>
</table>
</div>
@endsection

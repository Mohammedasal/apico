@extends('layouts.app')

@section('content')
<div class="section-title"><div><h1>{{ __('Expense Source Reconciliation') }}</h1><div class="muted">{{ __('Compare factory monthly expenses with posted accounting expense journals before changing the P&L source.') }}</div></div></div>
<form class="filters" method="get">
    <div><label>{{ __('Year') }}</label><input type="number" name="year" min="2000" max="2100" value="{{ $year }}"></div>
    <div><button>{{ __('Apply') }}</button></div>
</form>
@include('accounting.reports.partials.export-form', ['routeName' => 'accounting.reports.expense-reconciliation.export'])
<div class="grid" style="margin:16px 0">
    <div class="card kpi-card"><div class="muted">{{ __('Selected Source') }}</div><div class="kpi" style="font-size:18px">{{ __($source_label) }}</div>@if ($cutover)<div class="muted">{{ __('Cutover') }} {{ $cutover }}</div>@endif</div>
    <div class="card kpi-card"><div class="muted">{{ __('Legacy Monthly Expenses') }}</div><div class="kpi">{{ number_format($totals['legacy'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Posted Accounting Journals') }}</div><div class="kpi">{{ number_format($totals['accounting'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Total Difference') }}</div><div @class(['kpi', 'amount-positive' => abs($totals['difference']) < .0005, 'amount-negative' => abs($totals['difference']) >= .0005])>{{ number_format($totals['difference'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Selected P&L Expense') }}</div><div class="kpi">{{ number_format($totals['selected'], 3) }}</div></div>
</div>
<div class="table-wrap"><table><thead><tr><th>{{ __('Month') }}</th><th>{{ __('Legacy Monthly Expenses') }}</th><th>{{ __('Posted Accounting Journals') }}</th><th>{{ __('Difference') }}</th><th>{{ __('Selected P&L Expense') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
@forelse ($rows as $row)<tr><td>{{ $row['month'] }}</td><td>{{ number_format($row['legacy'], 3) }}</td><td>{{ number_format($row['accounting'], 3) }}</td><td @class(['amount-positive' => abs($row['difference']) < .0005, 'amount-negative' => abs($row['difference']) >= .0005])>{{ number_format($row['difference'], 3) }}</td><td>{{ number_format($row['selected'], 3) }}</td><td>{{ abs($row['difference']) < .0005 ? __('Matched') : __('Needs Reconciliation') }}</td></tr>
@empty<tr><td colspan="6">{{ __('No expense data for this year.') }}</td></tr>@endforelse
</tbody></table></div>
@endsection

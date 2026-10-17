@extends('layouts.app')

@section('content')
<div class="section-title"><div><h1>{{ __('Accounting P&L') }}</h1><div class="muted">{{ __('Posted journal revenue, cost of goods sold, and expenses.') }}</div></div></div>
<form class="filters" method="get"><div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $from }}"></div><div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $to }}"></div><div><button>{{ __('Apply') }}</button></div></form>
@include('accounting.reports.partials.export-form', ['routeName' => 'accounting.reports.profit-loss.export'])
<div class="grid" style="margin:16px 0"><div class="card kpi-card"><div class="muted">{{ __('Revenue') }}</div><div class="kpi">{{ number_format($revenue, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('COGS') }}</div><div class="kpi">{{ number_format($cogs, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('Gross Profit') }}</div><div class="kpi">{{ number_format($grossProfit, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('Expenses') }}</div><div class="kpi">{{ number_format($expenses, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('Net Profit') }}</div><div class="kpi">{{ number_format($netProfit, 3) }}</div></div></div>
<div class="table-wrap"><table><thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th>{{ __('Account Type') }}</th><th>{{ __('Amount') }}</th></tr></thead><tbody>@foreach ($rows as $row)<tr><td>{{ $row['code'] }}</td><td>{{ $row['account'] }}</td><td>{{ __(ucfirst($row['type'])) }}</td><td>{{ number_format($row['amount'], 3) }}</td></tr>@endforeach</tbody></table></div>
@endsection

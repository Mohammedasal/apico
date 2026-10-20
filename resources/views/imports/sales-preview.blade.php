@extends('layouts.app')

@section('content')
@if ($backupError ?? null)
    <div class="error" style="margin-bottom:16px">{{ $backupError }}</div>
@endif
<div class="section-title"><div><h1>{{ __('Sales Import Preview') }}</h1><div class="muted">{{ __('No database records have been changed. Review these totals before confirming the flush and import.') }}</div></div></div>
<div class="grid">
    <div class="card kpi-card"><div class="muted">{{ __('Customers') }}</div><div class="kpi">{{ $result['totals']['customers'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Purchases Rows') }}</div><div class="kpi">{{ $result['purchases']['rows'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Recycle In Rows') }}</div><div class="kpi">{{ $result['totals']['recycle_in_rows'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Recycle Out Rows') }}</div><div class="kpi">{{ $result['totals']['recycle_out_rows'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Payment Rows') }}</div><div class="kpi">{{ $result['totals']['payment_rows'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Stock Sale Rows') }}</div><div class="kpi">{{ $result['totals']['stock_sale_rows'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Skipped Invalid Rows') }}</div><div class="kpi">{{ $result['skipped_rows'] }}</div></div>
</div>

@if ($result['issues'])
<div class="section-title"><h2>{{ __('Rows Requiring Manual Correction') }}</h2></div>
<div class="table-wrap"><table><thead><tr><th>{{ __('Client') }}</th><th>{{ __('Type') }}</th><th>{{ __('Excel Row') }}</th><th>{{ __('Field') }}</th><th>{{ __('Wrong Value') }}</th><th>{{ __('Reason') }}</th></tr></thead><tbody>
@foreach ($result['issues'] as $issue)<tr><td>{{ $issue['customer'] }}</td><td>{{ __($issue['type']) }}</td><td>{{ $issue['row'] }}</td><td>{{ __($issue['field']) }}</td><td class="amount-negative">{{ $issue['value'] }}</td><td>{{ __($issue['reason']) }}</td></tr>@endforeach
</tbody></table></div>
@endif

@if ($dateIssues)
<div class="section-title"><h2>{{ __('2025 / Missing Date Transactions') }}</h2></div>
<div class="table-wrap"><table><thead><tr><th>{{ __('Client') }}</th><th>{{ __('Type') }}</th><th>{{ __('Date') }}</th><th>{{ __('Transaction') }}</th></tr></thead><tbody>
@foreach ($dateIssues as $issue)<tr><td>{{ $issue['customer'] }}</td><td>{{ __($issue['type']) }}</td><td>{{ $issue['date'] }}</td><td>{{ $issue['transaction'] }}</td></tr>@endforeach
</tbody></table></div>
@endif

<div class="card" style="margin-top:16px">
    <strong>{{ __('Confirmation Required') }}</strong>
    <p>{{ __('Confirming will first create and verify a database backup, then replace the imported operational transactions inside one database transaction.') }}</p>
    <form method="post" action="{{ route('imports.sales-sheet.commit') }}" onsubmit="return confirm(@json(__('Flush existing imported transactions and commit this preview?')))" style="display:inline-block">
        @csrf
        <input type="hidden" name="confirm" value="1">
        <button type="submit">{{ __('Backup, Flush & Import') }}</button>
    </form>
    <a class="button" href="{{ route('dashboard') }}">{{ __('Cancel') }}</a>
</div>
@endsection

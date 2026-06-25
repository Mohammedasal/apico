@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div>
        <h1>{{ $statementTitle ?? __('Supplier Statement') }}</h1>
        <div class="muted">{{ $supplier->name }} | {{ __('Generated Date') }}: {{ $statementGeneratedAt ?? now()->translatedFormat('F j, Y') }}</div>
        <div class="muted">{{ __('Supplier statement from :from to :to', ['from' => $statement['from'], 'to' => $statement['to']]) }}</div>
    </div>
    <div class="no-print">
        <button type="button" onclick="window.print()">{{ __('Print / Save PDF') }}</button>
        <button type="button" onclick="printLedgerOnly()">{{ __('Print Ledger Only') }}</button>
        @if (auth()->user()?->canWriteOperationalData())
            <a class="button" href="{{ route('suppliers.edit', $supplier) }}">{{ __('Edit') }}</a>
            <a class="button" href="{{ route('supplier-payments.create') }}">{{ __('Add Payment') }}</a>
        @endif
    </div>
</div>
<form class="filters" method="get">
    <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $statement['from'] }}"></div>
    <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $statement['to'] }}"></div>
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search ?? '' }}" placeholder="{{ __('Ledger search') }}"></div>
    <div><button>{{ __('Run Statement') }}</button></div>
</form>
<div class="section-title"><h2>{{ __('Export to Excel') }}</h2></div>
<form class="filters" method="get" action="{{ route('suppliers.export', $supplier) }}">
    <input type="hidden" name="from" value="{{ $statement['from'] }}">
    <input type="hidden" name="to" value="{{ $statement['to'] }}">
    <input type="hidden" name="q" value="{{ $search ?? '' }}">
    @foreach (($exportColumns ?? []) as $key => $label)
        <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>
<div class="grid" style="margin:16px 0">
    <div class="card"><div class="muted">{{ __('Opening Balance JOD') }}</div><div class="kpi">{{ number_format($statement['opening_balance'], 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Purchases JOD') }}</div><div class="kpi">{{ number_format($statement['period_purchases'], 3) }}</div><div class="muted">{{ number_format($statement['period_purchase_kg'], 3) }} {{ __('kg') }}</div></div>
    <div class="card"><div class="muted">{{ __('Payments JOD') }}</div><div class="kpi amount-negative">{{ number_format($statement['period_payments'], 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Remaining Payable JOD') }}</div><div class="kpi">{{ number_format($statement['closing_balance'], 3) }}</div></div>
</div>

<h2>{{ __('Purchases') }}</h2>
<table class="statement-table">
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Material') }}</th><th>{{ __('Weight Kg') }}</th><th>{{ __('Cost/Kg') }}</th><th>{{ __('Total JOD') }}</th><th>{{ __('Notes') }}</th></tr></thead>
    <tbody>
    @forelse ($statement['purchases'] as $row)
        <tr><td>{{ $row->date->toDateString() }}</td><td>{{ $row->material?->name ?? '-' }}</td><td>{{ number_format($row->weight_kg, 3) }}</td><td>{{ number_format($row->cost_per_kg, 3) }}</td><td>{{ number_format($row->total_cost, 3) }}</td><td>{{ $row->notes }}</td></tr>
    @empty
        <tr><td colspan="6">{{ __('No purchases.') }}</td></tr>
    @endforelse
    </tbody>
</table>

<h2>{{ __('Payments') }}</h2>
<table class="statement-table">
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Amount JOD') }}</th><th>{{ __('Type') }}</th><th>{{ __('Method') }}</th><th>{{ __('Cheque Due') }}</th><th>{{ __('Notes') }}</th></tr></thead>
    <tbody>
    @forelse ($statement['payments'] as $row)
        <tr><td>{{ $row->date->toDateString() }}</td><td>{{ number_format($row->amount, 3) }}</td><td>{{ __(ucwords(str_replace('_', ' ', $row->payment_type ?? 'cash'))) }}</td><td>{{ $row->payment_method }}</td><td>{{ $row->cheque_due_date?->toDateString() ?? '-' }}</td><td>{{ $row->notes }}</td></tr>
    @empty
        <tr><td colspan="6">{{ __('No payments.') }}</td></tr>
    @endforelse
    </tbody>
</table>

<div class="ledger-print-area">
    <h2>{{ __('Running Ledger') }}</h2>
    <table class="statement-table">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th>{{ __('Kg') }}</th><th>{{ __('JOD +/-') }}</th><th>{{ __('Running Balance') }}</th><th>{{ __('Notes') }}</th></tr></thead>
        <tbody>
        @forelse ($statement['transactions'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ __($row['type']) }}</td>
                <td>{{ $row['description'] }}</td>
                <td>{{ number_format($row['weight_kg'], 3) }}</td>
                <td @class(['amount-negative' => $row['amount'] < 0, 'amount-positive' => $row['amount'] > 0])>{{ number_format($row['amount'], 3) }}</td>
                <td>{{ number_format($row['running_balance'], 3) }}</td>
                <td>{{ $row['notes'] }}</td>
            </tr>
        @empty
            <tr><td colspan="7">{{ __('No transactions in this range.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<script>
    function printLedgerOnly() {
        document.body.classList.add('print-ledger-only');
        window.print();
        setTimeout(function () {
            document.body.classList.remove('print-ledger-only');
        }, 500);
    }
</script>
@endsection

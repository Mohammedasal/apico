@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div>
        <h1>{{ $statementTitle ?? __('Customer Statement') }}</h1>
        <div class="muted">{{ $customer->name }} | {{ __('Generated Date') }}: {{ $statementGeneratedAt ?? now()->translatedFormat('F j, Y') }}</div>
        <div class="muted">{{ __('Statement from :from to :to', ['from' => $statement['from'], 'to' => $statement['to']]) }}</div>
    </div>
    <div class="no-print">
        <button type="button" onclick="window.print()">{{ __('Print / Save PDF') }}</button>
        <button type="button" onclick="printLedgerOnly()">{{ __('Print Ledger Only') }}</button>
        @if (auth()->user()?->canWriteOperationalData())
            <a class="button" href="{{ route('customers.edit', $customer) }}">{{ __('Edit') }}</a>
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
<form class="filters" method="get" action="{{ route('customers.export', $customer) }}">
    <input type="hidden" name="from" value="{{ $statement['from'] }}">
    <input type="hidden" name="to" value="{{ $statement['to'] }}">
    <input type="hidden" name="q" value="{{ $search ?? '' }}">
    @foreach (($exportColumns ?? []) as $key => $label)
        <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>
<div class="grid" style="margin:16px 0">
    <div class="card"><div class="muted">{{ __('Total In Kg') }}</div><div class="kpi">{{ number_format($statement['total_in_kg'], 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Total Recycle Out Kg') }}</div><div class="kpi">{{ number_format($statement['total_recycle_out_kg'], 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Total Remaining Kg') }}</div><div class="kpi">{{ number_format($statement['closing_weight'], 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Total Sales Out JOD') }}</div><div class="kpi">{{ number_format($statement['period_stock_charges'], 3) }}</div><div class="muted">{{ number_format($statement['total_sales_out_kg'], 3) }} {{ __('kg') }}</div></div>
    <div class="card"><div class="muted">{{ __('Total Due JOD') }}</div><div class="kpi">{{ number_format($statement['period_total_charges'], 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Total Payment JOD') }}</div><div class="kpi amount-negative">{{ number_format($statement['period_payments'], 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Remaining Balance JOD') }}</div><div class="kpi">{{ number_format($statement['closing_balance'], 3) }}</div></div>
</div>
<div class="card" style="margin-bottom:16px">
    <strong>{{ __('Remaining receivable payment: :amount JOD', ['amount' => number_format($statement['closing_balance'], 3)]) }}</strong>
    <div class="muted">{{ __('Calculation: opening balance + recycle charges + stock sales - payments received.') }}</div>
</div>

<h2>{{ __('Recycle In Transactions') }}</h2>
<table class="statement-table">
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Material') }}</th><th>{{ __('Weight Kg') }}</th><th>{{ __('Notes') }}</th></tr></thead>
    <tbody>
    @forelse ($statement['tables']['recycle_ins'] as $row)
        <tr><td>{{ $row->date->toDateString() }}</td><td class="description-cell">{{ $row->material?->name ?? '-' }}</td><td>{{ number_format($row->weight_kg, 3) }}</td><td class="notes-cell">{{ $row->notes }}</td></tr>
    @empty
        <tr><td colspan="4">{{ __('No recycle in transactions.') }}</td></tr>
    @endforelse
    </tbody>
</table>

<h2>{{ __('Recycle Out Transactions') }}</h2>
<table class="statement-table recycle-out-table">
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Material') }}</th><th>{{ __('Recycled Kg') }}</th><th>{{ __('Waste Kg') }}</th><th>{{ __('Non-Recycled Kg') }}</th><th>{{ __('Total Out Kg') }}</th><th>{{ __('Rate') }}</th><th>{{ __('Total JOD') }}</th><th>{{ __('Notes') }}</th></tr></thead>
    <tbody>
    @forelse ($statement['tables']['recycle_outs'] as $row)
        <tr>
            <td>{{ $row->date->toDateString() }}</td>
            <td class="description-cell">{{ $row->material?->name ?? '-' }}</td>
            <td>{{ number_format($row->recycled_out_kg, 3) }}</td>
            <td>{{ number_format($row->waste_kg, 3) }}</td>
            <td>{{ number_format($row->non_recycled_kg, 3) }}</td>
            <td>{{ number_format($row->weight_kg, 3) }}</td>
            <td>{{ number_format($row->rate_per_kg, 3) }}</td>
            <td>{{ number_format($row->total_amount, 3) }}</td>
            <td class="notes-cell">{{ $row->notes }}</td>
        </tr>
    @empty
        <tr><td colspan="9">{{ __('No recycle out transactions.') }}</td></tr>
    @endforelse
    </tbody>
</table>

<h2>{{ __('Payments') }}</h2>
<table class="statement-table">
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Amount JOD') }}</th><th>{{ __('Remaining Balance') }}</th><th>{{ __('Type') }}</th><th>{{ __('Method / Receiver') }}</th><th>{{ __('Cheque Due') }}</th><th>{{ __('Notes') }}</th></tr></thead>
    <tbody>
    @forelse ($statement['tables']['payments'] as $row)
        <tr><td>{{ $row->date->toDateString() }}</td><td>{{ number_format($row->amount, 3) }}</td><td>{{ is_null($row->remaining_balance) ? '-' : number_format($row->remaining_balance, 3) }}</td><td>{{ __(ucwords(str_replace('_', ' ', $row->payment_type ?? 'cash'))) }}</td><td class="description-cell">{{ $row->payment_method }}</td><td>{{ $row->cheque_due_date?->toDateString() ?? '-' }}</td><td class="notes-cell">{{ $row->notes }}</td></tr>
    @empty
        <tr><td colspan="7">{{ __('No payments.') }}</td></tr>
    @endforelse
    </tbody>
</table>

<h2>{{ __('Sales Out') }}</h2>
<table class="statement-table">
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Material') }}</th><th>{{ __('Weight Kg') }}</th><th>{{ __('Rate') }}</th><th>{{ __('Total JOD') }}</th><th>{{ __('Notes') }}</th></tr></thead>
    <tbody>
    @forelse ($statement['tables']['stock_sales'] as $row)
        <tr><td>{{ $row->date->toDateString() }}</td><td class="description-cell">{{ $row->material?->name ?? '-' }}</td><td>{{ number_format($row->weight_kg, 3) }}</td><td>{{ number_format($row->selling_price_per_kg, 3) }}</td><td>{{ number_format($row->sales_value, 3) }}</td><td class="notes-cell">{{ $row->notes }}</td></tr>
    @empty
        <tr><td colspan="6">{{ __('No sales out transactions.') }}</td></tr>
    @endforelse
    </tbody>
</table>

<div class="ledger-print-area">
    <h2>{{ __('Running Ledger') }}</h2>
    <table class="statement-table ledger-table">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th>{{ __('Kg +/-') }}</th><th>{{ __('JOD +/-') }}</th><th>{{ __('Running JOD Balance') }}</th><th>{{ __('Running Kg Balance') }}</th><th>{{ __('Notes') }}</th></tr></thead>
        <tbody>
        @forelse ($statement['transactions'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ __($row['type']) }}</td>
                <td class="description-cell">{{ $row['description'] ?: '-' }}</td>
                <td @class(['amount-negative' => ($row['display_weight'] ?? $row['weight_delta']) < 0, 'amount-positive' => ($row['display_weight'] ?? $row['weight_delta']) > 0])>{{ number_format($row['display_weight'] ?? $row['weight_delta'], 3) }}</td>
                <td @class(['amount-negative' => $row['amount'] < 0, 'amount-positive' => $row['amount'] > 0])>{{ number_format($row['amount'], 3) }}</td>
                <td>{{ number_format($row['running_balance'], 3) }}</td>
                <td>{{ number_format($row['running_weight'], 3) }}</td>
                <td class="notes-cell">{{ $row['notes'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8">{{ __('No transactions in this range.') }}</td></tr>
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

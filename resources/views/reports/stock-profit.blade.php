@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div><h1>{{ __('Stock Profit') }}</h1><div class="muted">{{ __('Remaining stock: :kg kg', ['kg' => number_format($remainingStock, 3)]) }}</div></div>
    <form class="filters" method="get">
        <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $from }}"></div>
        <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $to }}"></div>
        <div><label>{{ __('Customer') }}</label><select class="searchable-select" name="customer_id"><option value="">{{ __('All') }}</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected($customerId == $customer->id)>{{ $customer->name }}</option>@endforeach</select></div>
        <div><label>{{ __('Material') }}</label><select class="searchable-select" name="material_id"><option value="">{{ __('All') }}</option>@foreach ($materials as $material)<option value="{{ $material->id }}" @selected($materialId == $material->id)>{{ $material->name }}</option>@endforeach</select></div>
        <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search }}" placeholder="{{ __('Customer, material, notes') }}"></div>
        <div><button>{{ __('Apply') }}</button></div>
    </form>
</div>
<div class="section-title"><h2>{{ __('Export to Excel') }}</h2></div>
<form class="filters" method="get" action="{{ route('reports.stock-profit.export') }}">
    <input type="hidden" name="from" value="{{ $from }}">
    <input type="hidden" name="to" value="{{ $to }}">
    <input type="hidden" name="customer_id" value="{{ $customerId }}">
    <input type="hidden" name="material_id" value="{{ $materialId }}">
    <input type="hidden" name="q" value="{{ $search }}">
    @foreach ($columns as $key => $label)
        <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>
<div class="grid" style="margin-bottom:16px">
    <div class="card kpi-card"><div class="muted">{{ __('Revenue') }}</div><div class="kpi">{{ number_format($summary['revenue'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Material COGS') }}</div><div class="kpi amount-negative">{{ number_format($summary['material_cogs'], 3) }}</div><div class="muted">{{ __('Weighted average sold stock cost') }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Conversion Cost') }}</div><div class="kpi amount-negative">{{ number_format($summary['recycle_cost'], 3) }}</div><div class="muted">{{ __('Sold tons x actual cost/ton') }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Profit') }}</div><div @class(['kpi', 'amount-positive' => $summary['profit'] >= 0, 'amount-negative' => $summary['profit'] < 0])>{{ number_format($summary['profit'], 3) }}</div></div>
</div>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Material') }}</th><th>{{ __('Kg') }}</th><th>{{ __('Sales Value') }}</th><th>{{ __('Net Profit') }}</th></tr></thead>
    <tbody>
    @foreach ($sales as $sale)
        <tr><td>{{ $sale->date->toDateString() }}</td><td>{{ $sale->customer->name }}</td><td>{{ $sale->material?->name ?? '-' }}</td><td>{{ number_format($sale->weight_kg, 3) }}</td><td>{{ number_format($sale->sales_value, 3) }}</td><td>{{ number_format($sale->net_profit, 3) }}</td></tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection

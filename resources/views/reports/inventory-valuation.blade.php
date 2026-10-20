@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div><h1>{{ __('Inventory Valuation') }}</h1><div class="muted">{{ __('Moving weighted-average stock value as of the selected date.') }}</div></div>
    <form class="filters" method="get">
        <div><label>{{ __('As of Date') }}</label><input type="date" name="on_date" value="{{ $onDate }}"></div>
        <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option><option value="OK" @selected($status === 'OK')>{{ __('OK') }}</option><option value="Negative stock" @selected($status === 'Negative stock')>{{ __('Negative stock') }}</option></select></div>
        <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search }}" placeholder="{{ __('Material') }}"></div>
        <div><button>{{ __('Apply') }}</button></div>
    </form>
</div>
<div class="grid" style="margin-bottom:16px">
    <div class="card kpi-card"><div class="muted">{{ __('Remaining Kg') }}</div><div class="kpi">{{ number_format($totalKg, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Inventory Value') }}</div><div class="kpi">{{ number_format($totalValue, 3) }}</div><div class="muted">JOD</div></div>
</div>
<div class="section-title"><h2>{{ __('Export to Excel') }}</h2></div>
<form class="filters" method="get" action="{{ route('reports.inventory-valuation.export') }}">
    <input type="hidden" name="on_date" value="{{ $onDate }}">
    <input type="hidden" name="status" value="{{ $status }}">
    <input type="hidden" name="q" value="{{ $search }}">
    @foreach ($columns as $key => $label)
        <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Material') }}</th><th>{{ __('Purchased Kg') }}</th><th>{{ __('Sold Kg') }}</th><th>{{ __('Remaining Kg') }}</th><th>{{ __('Average Cost/Kg') }}</th><th>{{ __('Inventory Value') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @forelse ($rows as $row)
        <tr><td>{{ $row['material'] }}</td><td>{{ number_format($row['purchased_kg'], 3) }}</td><td>{{ number_format($row['sold_kg'], 3) }}</td><td @class(['amount-negative' => $row['remaining_kg'] < 0])>{{ number_format($row['remaining_kg'], 3) }}</td><td>{{ number_format($row['average_cost_per_kg'], 6) }}</td><td>{{ number_format($row['inventory_value'], 3) }}</td><td>{{ __($row['status']) }}</td></tr>
    @empty
        <tr><td colspan="7">{{ __('No data for this period.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection

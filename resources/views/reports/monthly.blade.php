@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __('Monthly Report') }}</h1>
    <form class="filters" method="get">
        <div><label>{{ __('Year') }}</label><input type="number" name="year" value="{{ $year }}"></div>
        <div><label>{{ __('From Month') }}</label><input type="number" min="1" max="12" name="from_month" value="{{ $fromMonth }}"></div>
        <div><label>{{ __('To Month') }}</label><input type="number" min="1" max="12" name="to_month" value="{{ $toMonth }}"></div>
        <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search }}" placeholder="{{ __('Month name') }}"></div>
        <div><button>{{ __('Apply') }}</button></div>
    </form>
</div>
<div class="section-title"><h2>{{ __('Export to Excel') }}</h2></div>
<form class="filters" method="get" action="{{ route('reports.monthly.export') }}">
    <input type="hidden" name="year" value="{{ $year }}">
    <input type="hidden" name="from_month" value="{{ $fromMonth }}">
    <input type="hidden" name="to_month" value="{{ $toMonth }}">
    <input type="hidden" name="q" value="{{ $search }}">
    @foreach ($columns as $key => $label)
        <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Recycle In Kg') }}</th><th>{{ __('Total Out Kg') }}</th><th>{{ __('Recycled Out Kg') }}</th><th>{{ __('Waste Kg') }}</th><th>{{ __('Waste %') }}</th><th>{{ __('Non-Recycled Kg') }}</th><th>{{ __('Payments') }}</th><th>{{ __('Stock Sales') }}</th><th>{{ __('Actual Cost/Ton') }}</th><th>{{ __('Actual Stock Profit') }}</th><th>{{ __('Actual P&L') }}</th></tr></thead>
    <tbody>
    @foreach ($months as $row)
        <tr><td>{{ $row['month'] }}</td><td>{{ number_format($row['recycle_in_kg'], 3) }}</td><td>{{ number_format($row['recycle_out_kg'], 3) }}</td><td>{{ number_format($row['recycled_out_kg'], 3) }}</td><td>{{ number_format($row['waste_kg'], 3) }}</td><td>{{ number_format($row['waste_percentage'], 2) }}%</td><td>{{ number_format($row['non_recycled_kg'], 3) }}</td><td>{{ number_format($row['payments'], 3) }}</td><td>{{ number_format($row['stock_sales'], 3) }}</td><td>{{ number_format($row['actual_cost_per_ton'], 3) }}</td><td>{{ number_format($row['stock_profit'], 3) }}</td><td @class(['amount-positive' => $row['actual_profit_loss'] >= 0, 'amount-negative' => $row['actual_profit_loss'] < 0])>{{ number_format($row['actual_profit_loss'], 3) }}</td></tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection

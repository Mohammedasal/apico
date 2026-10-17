@extends('layouts.app')

@section('content')
<div class="section-title"><div><h1>{{ __('Cash & Bank') }}</h1><div class="muted">{{ __('Balances from posted journal entries.') }}</div></div></div>
<form class="filters" method="get"><div><label>{{ __('As Of') }}</label><input type="date" name="to" value="{{ $to }}"></div><div><button>{{ __('Apply') }}</button></div></form>
<div class="grid" style="margin:16px 0"><div class="card kpi-card"><div class="muted">{{ __('Cash') }}</div><div class="kpi">{{ number_format($cashTotal, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('Bank') }}</div><div class="kpi">{{ number_format($bankTotal, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('Total') }}</div><div class="kpi">{{ number_format($total, 3) }}</div></div></div>
<div class="table-wrap"><table><thead><tr><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th>{{ __('Balance') }}</th></tr></thead><tbody>@foreach ($rows as $row)<tr><td>{{ $row['name'] }}</td><td>{{ __(ucfirst($row['type'])) }}</td><td>{{ number_format($row['balance'], 3) }}</td></tr>@endforeach</tbody></table></div>
@endsection

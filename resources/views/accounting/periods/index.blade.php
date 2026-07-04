@extends('layouts.app')

@section('content')
<div class="section-title"><div><h1>{{ __('Accounting Periods') }}</h1><div class="muted">{{ __('Locked periods reject new or changed accounting postings.') }}</div></div></div>
<form method="post" action="{{ route('accounting.periods.store') }}" class="filters">
    @csrf
    <div><label>{{ __('Year') }}</label><input type="number" name="period_year" value="{{ now()->year }}"></div>
    <div><label>{{ __('Month') }}</label><input type="number" min="1" max="12" name="period_month" value="{{ now()->month }}"></div>
    <div><button>{{ __('Create Period') }}</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Status') }}</th><th>{{ __('Locked By') }}</th><th></th></tr></thead>
    <tbody>
    @forelse ($periods as $period)
        <tr>
            <td>{{ sprintf('%04d-%02d', $period->period_year, $period->period_month) }}</td>
            <td>{{ $period->start_date->toDateString() }}</td>
            <td>{{ $period->end_date->toDateString() }}</td>
            <td>{{ __(ucfirst($period->status)) }}</td>
            <td>{{ $period->locker?->name ?? '-' }}</td>
            <td>
                @if ($period->status === 'open')
                    <form method="post" action="{{ route('accounting.periods.lock', $period) }}">@csrf<button>{{ __('Lock') }}</button></form>
                @else
                    <form method="post" action="{{ route('accounting.periods.unlock', $period) }}">@csrf<button>{{ __('Unlock') }}</button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="6">{{ __('No accounting periods.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection

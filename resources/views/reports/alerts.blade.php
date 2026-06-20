@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __('Alerts') }}</h1>
    <form class="filters" method="get">
        <div><label>{{ __('Type') }}</label><select name="type"><option value="">{{ __('All') }}</option>@foreach ($types as $option)<option value="{{ $option }}" @selected($type === $option)>{{ __($option) }}</option>@endforeach</select></div>
        <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search }}" placeholder="{{ __('Message') }}"></div>
        <div><button>{{ __('Apply') }}</button></div>
    </form>
</div>
<div class="section-title"><h2>{{ __('Export to Excel') }}</h2></div>
<form class="filters" method="get" action="{{ route('reports.alerts.export') }}">
    <input type="hidden" name="type" value="{{ $type }}">
    <input type="hidden" name="q" value="{{ $search }}">
    @foreach ($columns as $key => $label)
        <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>
<div class="table-wrap" style="margin-top:16px">
<table>
    <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Message') }}</th></tr></thead>
    <tbody>
    @forelse ($alerts as $alert)
        <tr><td>{{ $alert['type'] }}</td><td>{{ $alert['message'] }}</td></tr>
    @empty
        <tr><td colspan="2">{{ __('No alerts.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection

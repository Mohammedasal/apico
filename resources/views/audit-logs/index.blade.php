@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div><h1>{{ __('Audit Log') }}</h1><div class="muted">{{ __('Recorded business and security changes with before and after values.') }}</div></div>
    <form class="filters" method="get">
        <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search }}" placeholder="{{ __('Action, record, or user') }}"></div>
        <div><label>{{ __('User') }}</label><select class="searchable-select" name="user_id"><option value="">{{ __('All') }}</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected($userId === $user->id)>{{ $user->name }}</option>@endforeach</select></div>
        <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $from }}"></div>
        <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $to }}"></div>
        <div><button>{{ __('Apply') }}</button></div>
    </form>
</div>
<form class="filters" method="get" action="{{ route('audit-logs.export') }}" style="margin-bottom:14px">
    @foreach (request()->except(['page', 'columns']) as $key => $value) @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
    @foreach (['time' => __('Time'), 'user' => __('User'), 'action' => __('Action'), 'record' => __('Record'), 'before' => __('Before'), 'after' => __('After'), 'ip_address' => __('IP Address')] as $column => $label)
        <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="columns[]" value="{{ $column }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Time') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th><th>{{ __('Changes') }}</th><th>{{ __('IP Address') }}</th></tr></thead>
    <tbody>
    @forelse ($logs as $log)
        <tr>
            <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
            <td>{{ $log->user?->name ?? __('System') }}</td>
            <td>{{ __(ucwords(str_replace('_', ' ', $log->action))) }}</td>
            <td>{{ class_basename($log->model_type) }}{{ $log->model_id ? ' #'.$log->model_id : '' }}</td>
            <td>
                <details><summary>{{ __('View Changes') }}</summary>
                    @if ($log->before !== null)<strong>{{ __('Before') }}</strong><pre style="white-space:pre-wrap;max-width:680px">{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                    @if ($log->after !== null)<strong>{{ __('After') }}</strong><pre style="white-space:pre-wrap;max-width:680px">{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                </details>
            </td>
            <td>{{ $log->ip_address ?: '-' }}</td>
        </tr>
    @empty
        <tr><td colspan="6">{{ __('No data for this period.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $logs->links() }}
@endsection

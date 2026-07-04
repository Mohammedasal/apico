@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Journal Entries') }}</h1><div class="muted">{{ __('Posted, reversed, and manual accounting journals.') }}</div></div>
    <a class="button" href="{{ route('accounting.journals.create') }}">{{ __('New Manual Journal') }}</a>
</div>
<form class="filters" method="get">
    <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option>@foreach (['draft','posted','reversed','void'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __(ucfirst($status)) }}</option>@endforeach</select></div>
    <div><label>{{ __('Source') }}</label><select name="source_module"><option value="">{{ __('All') }}</option><option value="operations" @selected(($filters['source_module'] ?? '') === 'operations')>{{ __('Operations') }}</option><option value="manual" @selected(($filters['source_module'] ?? '') === 'manual')>{{ __('Manual') }}</option></select></div>
    <div><button>{{ __('Filter') }}</button></div>
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Entry No.') }}</th><th>{{ __('Memo') }}</th><th>{{ __('Source') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @forelse ($entries as $entry)
        <tr>
            <td>{{ $entry->entry_date->toDateString() }}</td>
            <td><a href="{{ route('accounting.journals.show', $entry) }}">{{ $entry->entry_no }}</a></td>
            <td>{{ app()->getLocale() === 'ar' ? ($entry->memo_ar ?: $entry->memo_en) : ($entry->memo_en ?: $entry->memo_ar) }}</td>
            <td>{{ $entry->source_module ?? '-' }} @if ($entry->source_id)#{{ $entry->source_id }}@endif</td>
            <td>{{ number_format($entry->total_debit, 3) }}</td>
            <td>{{ number_format($entry->total_credit, 3) }}</td>
            <td>{{ __(ucfirst($entry->status)) }}</td>
        </tr>
    @empty
        <tr><td colspan="7">{{ __('No journal entries yet.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $entries->links() }}
@endsection

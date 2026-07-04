@extends('layouts.app')

@section('content')
<div class="section-title">
    <div>
        <h1>{{ __('Accounting Dashboard') }}</h1>
        <div class="muted">{{ __('Double-entry accounting generated from APICO operations.') }}</div>
    </div>
    <a class="button" href="{{ route('accounting.journals.create') }}">{{ __('New Manual Journal') }}</a>
</div>

<div class="status" style="border-color:{{ $accountingEnabled ? '#86b99c' : '#e5bf73' }};background:{{ $accountingEnabled ? '#edf9f1' : '#fff8e8' }}">
    <strong>{{ __('Automatic Posting') }}:</strong>
    {{ $accountingEnabled ? __('Enabled') : __('Disabled until mappings are approved') }}
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="muted">{{ __('Chart Accounts') }}</div><div class="kpi">{{ $accountCount }}</div><a href="{{ route('accounting.accounts.index') }}">{{ __('Open') }}</a></div>
    <div class="card kpi-card"><div class="muted">{{ __('Active Mappings') }}</div><div class="kpi">{{ $mappingCount }}</div><a href="{{ route('accounting.mappings.index') }}">{{ __('Review') }}</a></div>
    <div class="card kpi-card"><div class="muted">{{ __('Posted Journals') }}</div><div class="kpi">{{ $postedEntryCount }}</div><a href="{{ route('accounting.journals.index') }}">{{ __('Open') }}</a></div>
    <div class="card kpi-card"><div class="muted">{{ __('Reversed Journals') }}</div><div class="kpi">{{ $reversedEntryCount }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Cash Accounts') }}</div><div class="kpi">{{ $cashAccountCount }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Bank Accounts') }}</div><div class="kpi">{{ $bankAccountCount }}</div></div>
</div>

<div class="section-title"><h2>{{ __('Recent Journal Entries') }}</h2></div>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Entry No.') }}</th><th>{{ __('Memo') }}</th><th>{{ __('Source') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
    @forelse ($recentEntries as $entry)
        <tr>
            <td>{{ $entry->entry_date->toDateString() }}</td>
            <td><a href="{{ route('accounting.journals.show', $entry) }}">{{ $entry->entry_no }}</a></td>
            <td>{{ app()->getLocale() === 'ar' ? ($entry->memo_ar ?: $entry->memo_en) : ($entry->memo_en ?: $entry->memo_ar) }}</td>
            <td>{{ $entry->source_module ?? '-' }}</td>
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
@endsection

@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Expense Voucher') }} {{ $voucher->voucher_no }}</h1><div class="muted">{{ $voucher->expense_date->toDateString() }} | {{ __(ucfirst($voucher->status)) }}</div></div>
    <div style="display:flex;gap:8px">
        @if ($voucher->status === 'posted')
            <a class="button" href="{{ route('accounting.expenses.edit', $voucher) }}">{{ __('Edit') }}</a>
            <form method="post" action="{{ route('accounting.expenses.cancel', $voucher) }}" onsubmit="return confirm('{{ __('Cancel this expense voucher and reverse its journal?') }}')">@csrf<button>{{ __('Cancel Voucher') }}</button></form>
        @endif
    </div>
</div>
<div class="kpi-grid">
    <div class="card kpi-card"><div class="muted">{{ __('Category') }}</div><div class="kpi" style="font-size:20px">{{ $voucher->category->localized_name }}</div><div>{{ $voucher->category->defaultAccount->code }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Amount') }}</div><div class="kpi">{{ number_format($voucher->amount, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Paid Amount') }}</div><div class="kpi">{{ number_format($voucher->paid_amount, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Outstanding Amount') }}</div><div class="kpi">{{ number_format($voucher->amount - $voucher->paid_amount, 3) }}</div></div>
</div>
<div class="card">
    <div><strong>{{ __('Payment Status') }}:</strong> {{ __(ucwords(str_replace('_', ' ', $voucher->payment_status))) }}</div>
    <div><strong>{{ __('Payment Type') }}:</strong> {{ __(ucwords(str_replace('_', ' ', $voucher->payment_type))) }}</div>
    <div><strong>{{ __('Reference') }}:</strong> {{ $voucher->reference ?: '-' }}</div>
    <div><strong>{{ __('Notes') }}:</strong> {{ $voucher->notes ?: '-' }}</div>
</div>
@if ($journalEntries->isNotEmpty())
<div class="section-title"><h2>{{ __('Generated Journals') }}</h2></div>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Entry No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th></tr></thead>
    <tbody>@foreach ($journalEntries as $entry)<tr><td><a href="{{ route('accounting.journals.show', $entry) }}">{{ $entry->entry_no }}</a></td><td>{{ $entry->entry_date->toDateString() }}</td><td>{{ __(ucfirst($entry->status)) }}</td><td>{{ number_format($entry->total_debit, 3) }}</td><td>{{ number_format($entry->total_credit, 3) }}</td></tr>@endforeach</tbody>
</table>
</div>
@endif
@endsection

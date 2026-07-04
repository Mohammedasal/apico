@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Expense Vouchers') }}</h1><div class="muted">{{ __('Detailed expenses with automatic accounting journals.') }}</div></div>
    <a class="button" href="{{ route('accounting.expenses.create') }}">{{ __('New Expense') }}</a>
</div>

<div class="kpi-grid">
    <div class="card kpi-card"><div class="muted">{{ __('Total Expenses') }}</div><div class="kpi">{{ number_format($totalAmount, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Paid Amount') }}</div><div class="kpi">{{ number_format($totalPaid, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Outstanding Amount') }}</div><div class="kpi">{{ number_format($totalAmount - $totalPaid, 3) }}</div></div>
</div>

<form class="filters" method="get">
    <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    <div><label>{{ __('Category') }}</label><select class="searchable-select" name="expense_category_id"><option value="">{{ __('All') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['expense_category_id'] ?? '') == $category->id)>{{ $category->localized_name }}</option>@endforeach</select></div>
    <div><label>{{ __('Payment Type') }}</label><select name="payment_type"><option value="">{{ __('All') }}</option>@foreach (['cash','bank_transfer','cheque','credit'] as $type)<option value="{{ $type }}" @selected(($filters['payment_type'] ?? '') === $type)>{{ __(ucwords(str_replace('_', ' ', $type))) }}</option>@endforeach</select></div>
    <div><label>{{ __('Payment Status') }}</label><select name="payment_status"><option value="">{{ __('All') }}</option>@foreach (['paid','unpaid','partially_paid'] as $status)<option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>@endforeach</select></div>
    <div><label>{{ __('Voucher Status') }}</label><select name="status"><option value="">{{ __('All') }}</option><option value="posted" @selected(($filters['status'] ?? '') === 'posted')>{{ __('Posted') }}</option><option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
    <div><button>{{ __('Filter') }}</button></div>
</form>

<form class="filters" method="get" action="{{ route('accounting.expenses.export') }}" style="margin-top:10px">
    @foreach ($filters as $key => $value) @if (filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
    <div style="grid-column:1/-1">
        <label>{{ __('Export Columns') }}</label>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
            @foreach ($exportColumns as $key => $label)
                <label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>
            @endforeach
        </div>
    </div>
    <div><button>{{ __('Export Excel') }}</button></div>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Voucher No.') }}</th><th>{{ __('Category') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Paid') }}</th><th>{{ __('Payment Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reference') }}</th></tr></thead>
    <tbody>
    @forelse ($vouchers as $voucher)
        <tr>
            <td>{{ $voucher->expense_date->toDateString() }}</td>
            <td><a href="{{ route('accounting.expenses.show', $voucher) }}">{{ $voucher->voucher_no }}</a></td>
            <td>{{ $voucher->category->localized_name }}</td>
            <td>{{ number_format($voucher->amount, 3) }}</td>
            <td>{{ number_format($voucher->paid_amount, 3) }} | {{ __(ucwords(str_replace('_', ' ', $voucher->payment_status))) }}</td>
            <td>{{ __(ucwords(str_replace('_', ' ', $voucher->payment_type))) }}</td>
            <td>{{ __(ucfirst($voucher->status)) }}</td>
            <td>{{ $voucher->reference }}</td>
        </tr>
    @empty
        <tr><td colspan="8">{{ __('No expense vouchers.') }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $vouchers->links() }}
@endsection

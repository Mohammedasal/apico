@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __('Supplier Payments') }}</h1>
    @if (auth()->user()?->canWriteOperationalData())
        <a class="button" href="{{ route('supplier-payments.create') }}">{{ __('New Payment') }}</a>
    @endif
</div>
@php
    $sortUrl = function (string $column) use ($filters) {
        $direction = $filters['sort'] === $column && $filters['direction'] === 'asc' ? 'desc' : 'asc';
        return route('supplier-payments.index', array_merge(request()->query(), ['sort' => $column, 'direction' => $direction, 'page' => null]));
    };
@endphp
<form class="filters" method="get">
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Supplier, reference, bank, notes') }}"></div>
    <div><label>{{ __('Supplier') }}</label><select class="searchable-select" name="supplier_id"><option value="">{{ __('All') }}</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($filters['supplier_id'] == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
    <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $filters['from'] }}"></div>
    <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $filters['to'] }}"></div>
    <div><label>{{ __('Type') }}</label><select name="payment_type"><option value="">{{ __('All') }}</option>@foreach (['cash' => 'Cash', 'cheque' => 'Cheque', 'bank_transfer' => 'Bank Transfer', 'exchange_of_goods' => 'Exchange of Goods'] as $value => $label)<option value="{{ $value }}" @selected($filters['payment_type'] === $value)>{{ __($label) }}</option>@endforeach</select></div>
    <div><label>{{ __('Min Amount') }}</label><input type="number" step="0.001" name="min_amount" value="{{ $filters['min_amount'] }}"></div>
    <div><label>{{ __('Max Amount') }}</label><input type="number" step="0.001" name="max_amount" value="{{ $filters['max_amount'] }}"></div>
    <div><button>{{ __('Apply') }}</button></div>
    <div><a class="button" href="{{ route('supplier-payments.index') }}">{{ __('Clear') }}</a></div>
</form>
@if (in_array(auth()->user()?->role, ['admin', 'accountant', 'viewer'], true))
    <div class="section-title"><h2>{{ __('Export to Excel') }}</h2></div>
    <form class="filters" method="get" action="{{ route('supplier-payments.export') }}">
        @foreach (request()->except(['columns', 'page']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        @foreach ($columns as $key => $label)<label class="check"><input type="checkbox" name="columns[]" value="{{ $key }}" checked> {{ $label }}</label>@endforeach
        <div><button>{{ __('Export Excel') }}</button></div>
    </form>
@endif
<div class="table-wrap">
<table>
    <thead><tr><th><a href="{{ $sortUrl('date') }}">{{ __('Date') }}</a></th><th><a href="{{ $sortUrl('supplier') }}">{{ __('Supplier') }}</a></th><th><a href="{{ $sortUrl('amount') }}">{{ __('Amount') }}</a></th><th><a href="{{ $sortUrl('payment_type') }}">{{ __('Type') }}</a></th><th><a href="{{ $sortUrl('cheque_due_date') }}">{{ __('Cheque') }}</a></th><th>{{ __('Notes') }}</th><th>{{ __('Audit') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($payments as $payment)
        <tr>
            <td>{{ $payment->date?->toDateString() }}</td>
            <td>{{ $payment->supplier?->name }}</td>
            <td>{{ number_format($payment->amount, 3) }}</td>
            <td>{{ __(ucwords(str_replace('_', ' ', $payment->payment_type ?? 'cash'))) }}</td>
            <td>{{ $payment->payment_type === 'cheque' ? (($payment->cheque_due_date?->toDateString() ?? __('No due date')).' | '.__(ucfirst($payment->cheque_status ?? 'pending'))) : '-' }}</td>
            <td>{{ $payment->notes }}</td>
            <td>
                <div>{{ __('Created') }} {{ $payment->created_at?->format('Y-m-d H:i') }}</div>
                <div class="muted">{{ __('By') }} {{ $payment->creator?->name ?? __('Import/System') }}</div>
                @if ($payment->updated_by)
                    <div>{{ __('Edited') }} {{ $payment->updated_at?->format('Y-m-d H:i') }}</div>
                    <div class="muted">{{ __('By') }} {{ $payment->editor?->name ?? __('System') }}</div>
                @endif
            </td>
            <td>
                @if (auth()->user()?->canWriteOperationalData())<a href="{{ route('supplier-payments.edit', $payment) }}">{{ __('Edit') }}</a>@endif
                @if (auth()->user()?->role === 'admin')
                    <form method="post" action="{{ route('supplier-payments.destroy', $payment) }}" style="display:inline" onsubmit="return confirm('{{ __('Delete this transaction?') }}')">@csrf @method('delete') <button class="link-button" type="submit">{{ __('Delete') }}</button></form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $payments->links() }}
@endsection

@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div>
        <h1>{{ __('Cheques In') }}</h1>
        <div class="muted">{{ __('Incoming cheques generated from payments with type Cheque.') }}</div>
    </div>
    <form class="filters" method="get">
        <div><label>{{ __('Search') }}</label><input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Customer, cheque, bank, or notes') }}"></div>
        <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option><option value="pending" @selected($filters['status'] === 'pending')>{{ __('Pending') }}</option><option value="collected" @selected($filters['status'] === 'collected')>{{ __('Collected') }}</option><option value="bounced" @selected($filters['status'] === 'bounced')>{{ __('Bounced') }}</option><option value="cancelled" @selected($filters['status'] === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
        <div><label>{{ __('Due From') }}</label><input type="date" name="from" value="{{ $filters['from'] }}"></div>
        <div><label>{{ __('Due To') }}</label><input type="date" name="to" value="{{ $filters['to'] }}"></div>
        <div><label>{{ __('Min Amount') }}</label><input type="number" step="0.001" name="min_amount" value="{{ $filters['min_amount'] }}"></div>
        <div><label>{{ __('Max Amount') }}</label><input type="number" step="0.001" name="max_amount" value="{{ $filters['max_amount'] }}"></div>
        <div><label>{{ __('Sort By') }}</label><select name="sort">@foreach (['due_date' => __('Due Date'), 'customer' => __('Customer'), 'payment_date' => __('Payment Date'), 'amount' => __('Amount'), 'status' => __('Status')] as $value => $label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label>{{ __('Direction') }}</label><select name="direction"><option value="asc" @selected($filters['direction'] === 'asc')>{{ __('Ascending') }}</option><option value="desc" @selected($filters['direction'] === 'desc')>{{ __('Descending') }}</option></select></div>
        <div><button>{{ __('Filter') }}</button></div>
    </form>
</div>
@if (auth()->user()?->canManageAccounting())
<form class="filters" method="get" action="{{ route('cheques-in.export') }}" style="margin-bottom:14px">
    @foreach (request()->except(['page', 'columns']) as $key => $value) @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
    @foreach (['due_date' => __('Due Date'), 'customer' => __('Customer'), 'payment_date' => __('Payment Date'), 'amount' => __('Amount'), 'bank' => __('Bank'), 'cheque_number' => __('Cheque No.'), 'status' => __('Status'), 'due_state' => __('Due State'), 'settlement_date' => __('Settlement Date'), 'settlement_bank' => __('Settlement Bank'), 'notes' => __('Notes')] as $column => $label)
        <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="columns[]" value="{{ $column }}" checked> {{ $label }}</label>
    @endforeach
    <div><button>{{ __('Export Excel') }}</button></div>
</form>
@endif
<div class="grid" style="margin-bottom:16px">
    <div class="card kpi-card"><div class="muted">{{ __('Overdue') }}</div><div class="kpi amount-negative">{{ $dueSummary['overdue'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Due Today') }}</div><div class="kpi">{{ $dueSummary['due_today'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Upcoming 7 Days') }}</div><div class="kpi">{{ $dueSummary['upcoming'] }}</div></div>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Due Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Payment Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Cheque No.') }}</th><th>{{ __('Status') }}</th><th>{{ __('Settlement') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Audit') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($cheques as $payment)
            <tr>
                <td>{{ $payment->cheque_due_date?->toDateString() ?? '-' }}</td>
                <td>{{ $payment->customer->name }}</td>
                <td>{{ $payment->date->toDateString() }}</td>
                <td>{{ number_format($payment->amount, 3) }}</td>
                <td>{{ $payment->bank_name }}</td>
                <td>{{ $payment->reference_no }}</td>
                <td>{{ __(ucfirst($payment->cheque_status ?? 'pending')) }}<div class="muted">{{ __(str($payment->due_state)->replace('_', ' ')->title()->toString()) }}</div></td>
                <td><div>{{ $payment->cheque_settlement_date?->toDateString() ?? '-' }}</div><div class="muted">{{ $payment->chequeBankAccount?->localized_name ?? '-' }}</div></td>
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
                    @if (auth()->user()?->canManageAccounting())
                        <form method="post" action="{{ route('cheques-in.update', $payment) }}" class="filters" style="padding:0;border:0;background:transparent;grid-template-columns:minmax(110px,1fr) minmax(135px,1fr) minmax(150px,1.3fr) auto;min-width:560px">
                            @csrf
                            @method('put')
                            <input type="hidden" name="_record_version" value="{{ app(\App\Services\FormSubmissionGuard::class)->version($payment) }}">
                            <select name="cheque_status"><option value="pending" @selected($payment->cheque_status === 'pending')>{{ __('Pending') }}</option><option value="collected" @selected($payment->cheque_status === 'collected')>{{ __('Collected') }}</option><option value="bounced" @selected($payment->cheque_status === 'bounced')>{{ __('Bounced') }}</option><option value="cancelled" @selected($payment->cheque_status === 'cancelled')>{{ __('Cancelled') }}</option></select>
                            <input type="date" name="cheque_settlement_date" value="{{ $payment->cheque_settlement_date?->toDateString() }}" title="{{ __('Settlement Date') }}">
                            <select class="searchable-select" name="cheque_bank_account_id" title="{{ __('Settlement Bank') }}"><option value="">{{ __('Select Bank') }}</option>@foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected($payment->cheque_bank_account_id === $account->id)>{{ $account->localized_name }}</option>@endforeach</select>
                            <button>{{ __('Save') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="11">{{ __('No incoming cheques.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $cheques->links() }}
@endsection

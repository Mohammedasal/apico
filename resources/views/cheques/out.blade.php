@extends('layouts.app')

@section('content')
@php $isEdit = filled($record); @endphp
<div class="toolbar">
    <div>
        <h1>{{ __('Cheques Out') }}</h1>
        <div class="muted">{{ __('Outgoing cheques to monitor due dates and bank balance needs.') }}</div>
    </div>
</div>
<form class="filters" method="get" style="margin-bottom:14px">
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Payee, cheque, bank, or notes') }}"></div>
    <div><label>{{ __('Source') }}</label><select name="source"><option value="">{{ __('All') }}</option><option value="manual" @selected($filters['source'] === 'manual')>{{ __('Manual Monitor') }}</option><option value="supplier" @selected($filters['source'] === 'supplier')>{{ __('Supplier Payment') }}</option><option value="expense" @selected($filters['source'] === 'expense')>{{ __('Expense') }}</option></select></div>
    <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option><option value="pending" @selected($filters['status'] === 'pending')>{{ __('Pending') }}</option><option value="cleared" @selected($filters['status'] === 'cleared')>{{ __('Cleared') }}</option><option value="cancelled" @selected($filters['status'] === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
    <div><label>{{ __('Due From') }}</label><input type="date" name="from" value="{{ $filters['from'] }}"></div>
    <div><label>{{ __('Due To') }}</label><input type="date" name="to" value="{{ $filters['to'] }}"></div>
    <div><label>{{ __('Min Amount') }}</label><input type="number" step="0.001" name="min_amount" value="{{ $filters['min_amount'] }}"></div>
    <div><label>{{ __('Max Amount') }}</label><input type="number" step="0.001" name="max_amount" value="{{ $filters['max_amount'] }}"></div>
    <div><label>{{ __('Direction') }}</label><select name="direction"><option value="asc" @selected($filters['direction'] === 'asc')>{{ __('Ascending') }}</option><option value="desc" @selected($filters['direction'] === 'desc')>{{ __('Descending') }}</option></select></div>
    <div><button>{{ __('Filter') }}</button></div>
</form>
@if (auth()->user()?->canManageAccounting())
<form class="filters" method="get" action="{{ route('cheques-out.export') }}" style="margin-bottom:14px">
    @foreach (request()->except(['manual_page', 'supplier_page', 'expense_page', 'columns']) as $key => $value) @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
    @foreach (['source' => __('Source'), 'due_date' => __('Due Date'), 'payee' => __('Payee'), 'transaction_date' => __('Transaction Date'), 'amount' => __('Amount'), 'bank' => __('Bank'), 'cheque_number' => __('Cheque No.'), 'status' => __('Status'), 'due_state' => __('Due State'), 'settlement_date' => __('Settlement Date'), 'notes' => __('Notes')] as $column => $label)
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

@if (auth()->user()?->canWriteOperationalData())
<form method="post" action="{{ $isEdit ? route('cheques-out.update', $record) : route('cheques-out.store') }}">
    @csrf
    @if ($isEdit)
        @method('put')
        <input type="hidden" name="_record_version" value="{{ app(\App\Services\FormSubmissionGuard::class)->version($record) }}">
    @else
        <input type="hidden" name="_submission_token" value="{{ app(\App\Services\FormSubmissionGuard::class)->issue('cheque-out') }}">
    @endif
    <div class="form-grid">
        <div><label>{{ __('Payee') }}</label><input name="payee" value="{{ old('payee', $record?->payee) }}">@error('payee')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Bank') }}</label><input name="bank_name" value="{{ old('bank_name', $record?->bank_name) }}"></div>
        <div><label>{{ __('Cheque No.') }}</label><input name="cheque_number" value="{{ old('cheque_number', $record?->cheque_number) }}"></div>
        <div><label>{{ __('Issue Date') }}</label><input type="date" name="issue_date" value="{{ old('issue_date', $record?->issue_date?->toDateString()) }}"></div>
        <div><label>{{ __('Due Date') }}</label><input type="date" name="due_date" value="{{ old('due_date', $record?->due_date?->toDateString()) }}">@error('due_date')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Amount JOD') }}</label><input type="number" step="0.001" name="amount" value="{{ old('amount', $record?->amount) }}">@error('amount')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Status') }}</label><select name="status"><option value="pending" @selected(old('status', $record?->status ?? 'pending') === 'pending')>{{ __('Pending') }}</option><option value="cleared" @selected(old('status', $record?->status) === 'cleared')>{{ __('Cleared') }}</option><option value="cancelled" @selected(old('status', $record?->status) === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $record?->notes) }}</textarea></div>
    </div>
    <p><button>{{ $isEdit ? __('Update Cheque') : __('Save Cheque') }}</button> @if ($isEdit)<a class="button" href="{{ route('cheques-out.index') }}">{{ __('Cancel') }}</a>@endif</p>
</form>
@endif

<div class="section-title"><div><h2>{{ __('Outgoing Cheques') }}</h2><div class="muted">{{ __('Monitoring-only cheques do not create accounting journals.') }}</div></div></div>
<div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Due Date') }}</th><th>{{ __('Payee') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Cheque No.') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Audit') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($cheques as $cheque)
            <tr>
                <td>{{ $cheque->due_date->toDateString() }}</td>
                <td>{{ $cheque->payee }}</td>
                <td>{{ $cheque->bank_name }}</td>
                <td>{{ $cheque->cheque_number }}</td>
                <td>{{ number_format($cheque->amount, 3) }}</td>
                <td>{{ __(ucfirst($cheque->status)) }}<div class="muted">{{ __(str($cheque->due_state)->replace('_', ' ')->title()->toString()) }}</div></td>
                <td>{{ $cheque->notes }}</td>
                <td>
                    <div>{{ __('Created') }} {{ $cheque->created_at?->format('Y-m-d H:i') }}</div>
                    <div class="muted">{{ __('By') }} {{ $cheque->creator?->name ?? __('Import/System') }}</div>
                    @if ($cheque->updated_by)
                        <div>{{ __('Edited') }} {{ $cheque->updated_at?->format('Y-m-d H:i') }}</div>
                        <div class="muted">{{ __('By') }} {{ $cheque->editor?->name ?? __('System') }}</div>
                    @endif
                </td>
                <td>@if (auth()->user()?->canWriteOperationalData())<a href="{{ route('cheques-out.edit', $cheque) }}">{{ __('Edit') }}</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="9">{{ __('No outgoing cheques.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $cheques->links() }}

<div class="section-title"><h2>{{ __('Supplier Payment Cheques') }}</h2></div>
<div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Due Date') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Payment Date') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Cheque No.') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Settlement') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Audit') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($supplierCheques as $payment)
            <tr>
                <td>{{ $payment->cheque_due_date?->toDateString() ?? '-' }}</td>
                <td>{{ $payment->supplier?->name }}</td>
                <td>{{ $payment->date?->toDateString() }}</td>
                <td>{{ $payment->bank_name }}</td>
                <td>{{ $payment->reference_no }}</td>
                <td>{{ number_format($payment->amount, 3) }}</td>
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
                    <form method="post" action="{{ route('cheques-out.supplier.update', $payment) }}" class="filters" style="padding:0;border:0;background:transparent;grid-template-columns:minmax(105px,1fr) minmax(135px,1fr) minmax(150px,1.3fr) auto;min-width:550px">
                        @csrf @method('put')
                        <input type="hidden" name="_record_version" value="{{ app(\App\Services\FormSubmissionGuard::class)->version($payment) }}">
                        <select name="cheque_status"><option value="pending" @selected($payment->cheque_status === 'pending')>{{ __('Pending') }}</option><option value="cleared" @selected($payment->cheque_status === 'cleared')>{{ __('Cleared') }}</option><option value="cancelled" @selected($payment->cheque_status === 'cancelled')>{{ __('Cancelled') }}</option></select>
                        <input type="date" name="cheque_settlement_date" value="{{ $payment->cheque_settlement_date?->toDateString() }}" title="{{ __('Settlement Date') }}">
                        <select class="searchable-select" name="cheque_bank_account_id" title="{{ __('Settlement Bank') }}"><option value="">{{ __('Select Bank') }}</option>@foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected($payment->cheque_bank_account_id === $account->id)>{{ $account->localized_name }}</option>@endforeach</select>
                        <button>{{ __('Save') }}</button>
                    </form>
                    @endif
                    @if ($payment->cheque_status === 'pending' && auth()->user()?->canWriteOperationalData())<a href="{{ route('supplier-payments.edit', $payment) }}">{{ __('Edit Payment') }}</a>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="11">{{ __('No supplier payment cheques.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $supplierCheques->links() }}

<div class="section-title"><h2>{{ __('Expense Cheques') }}</h2></div>
<div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Due Date') }}</th><th>{{ __('Voucher No.') }}</th><th>{{ __('Category') }}</th><th>{{ __('Expense Date') }}</th><th>{{ __('Cheque No.') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Settlement') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($expenseCheques as $expense)
            <tr>
                <td>{{ $expense->cheque_due_date?->toDateString() ?? '-' }}</td>
                <td><a href="{{ route('accounting.expenses.show', $expense) }}">{{ $expense->voucher_no }}</a></td>
                <td>{{ $expense->category?->localized_name }}</td>
                <td>{{ $expense->expense_date->toDateString() }}</td>
                <td>{{ $expense->reference }}</td>
                <td>{{ number_format($expense->paid_amount, 3) }}</td>
                <td>{{ __(ucfirst($expense->cheque_status ?? 'pending')) }}<div class="muted">{{ __(str($expense->due_state)->replace('_', ' ')->title()->toString()) }}</div></td>
                <td><div>{{ $expense->cheque_settlement_date?->toDateString() ?? '-' }}</div><div class="muted">{{ $expense->chequeBankAccount?->localized_name ?? '-' }}</div></td>
                <td>
                    @if (auth()->user()?->canManageAccounting())
                    <form method="post" action="{{ route('cheques-out.expense.update', $expense) }}" class="filters" style="padding:0;border:0;background:transparent;grid-template-columns:minmax(105px,1fr) minmax(135px,1fr) minmax(150px,1.3fr) auto;min-width:550px">
                        @csrf @method('put')
                        <input type="hidden" name="_record_version" value="{{ app(\App\Services\FormSubmissionGuard::class)->version($expense) }}">
                        <select name="cheque_status"><option value="pending" @selected($expense->cheque_status === 'pending')>{{ __('Pending') }}</option><option value="cleared" @selected($expense->cheque_status === 'cleared')>{{ __('Cleared') }}</option><option value="cancelled" @selected($expense->cheque_status === 'cancelled')>{{ __('Cancelled') }}</option></select>
                        <input type="date" name="cheque_settlement_date" value="{{ $expense->cheque_settlement_date?->toDateString() }}" title="{{ __('Settlement Date') }}">
                        <select class="searchable-select" name="cheque_bank_account_id" title="{{ __('Settlement Bank') }}"><option value="">{{ __('Select Bank') }}</option>@foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected($expense->cheque_bank_account_id === $account->id)>{{ $account->localized_name }}</option>@endforeach</select>
                        <button>{{ __('Save') }}</button>
                    </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9">{{ __('No expense cheques.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $expenseCheques->links() }}
@endsection

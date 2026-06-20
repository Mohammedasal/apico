@extends('layouts.app')

@section('content')
@php $isEdit = filled($record); @endphp
<div class="toolbar">
    <div>
        <h1>{{ __('Cheques Out') }}</h1>
        <div class="muted">{{ __('Outgoing cheques to monitor due dates and bank balance needs.') }}</div>
    </div>
</div>

@if (auth()->user()?->canWriteOperationalData())
<form method="post" action="{{ $isEdit ? route('cheques-out.update', $record) : route('cheques-out.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
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

<div class="section-title"><h2>{{ __('Outgoing Cheques') }}</h2></div>
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
                <td>{{ __(ucfirst($cheque->status)) }}</td>
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
        <thead><tr><th>{{ __('Due Date') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Payment Date') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Cheque No.') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Audit') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($supplierCheques as $payment)
            <tr>
                <td>{{ $payment->cheque_due_date?->toDateString() ?? '-' }}</td>
                <td>{{ $payment->supplier?->name }}</td>
                <td>{{ $payment->date?->toDateString() }}</td>
                <td>{{ $payment->bank_name }}</td>
                <td>{{ $payment->reference_no }}</td>
                <td>{{ number_format($payment->amount, 3) }}</td>
                <td>{{ __(ucfirst($payment->cheque_status ?? 'pending')) }}</td>
                <td>{{ $payment->notes }}</td>
                <td>
                    <div>{{ __('Created') }} {{ $payment->created_at?->format('Y-m-d H:i') }}</div>
                    <div class="muted">{{ __('By') }} {{ $payment->creator?->name ?? __('Import/System') }}</div>
                    @if ($payment->updated_by)
                        <div>{{ __('Edited') }} {{ $payment->updated_at?->format('Y-m-d H:i') }}</div>
                        <div class="muted">{{ __('By') }} {{ $payment->editor?->name ?? __('System') }}</div>
                    @endif
                </td>
                <td>@if (auth()->user()?->canWriteOperationalData())<a href="{{ route('supplier-payments.edit', $payment) }}">{{ __('Edit Payment') }}</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="10">{{ __('No supplier payment cheques.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

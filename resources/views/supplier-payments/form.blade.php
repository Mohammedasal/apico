@extends('layouts.app')

@section('content')
@php $isEdit = $payment->exists; @endphp
<h1>{{ $isEdit ? __('Edit Supplier Payment') : __('New Supplier Payment') }}</h1>
<form method="post" action="{{ $isEdit ? route('supplier-payments.update', $payment) : route('supplier-payments.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Date') }}</label><input type="date" name="date" value="{{ old('date', $payment->date?->toDateString() ?? now()->toDateString()) }}">@error('date')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Supplier') }}</label><select class="searchable-select" name="supplier_id"><option value="">{{ __('Select') }}</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', $payment->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select>@error('supplier_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Amount') }}</label><input type="number" step="0.001" name="amount" value="{{ old('amount', $payment->amount) }}">@error('amount')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Type') }}</label><select name="payment_type"><option value="cash" @selected(old('payment_type', $payment->payment_type ?? 'cash') === 'cash')>{{ __('Cash') }}</option><option value="cheque" @selected(old('payment_type', $payment->payment_type) === 'cheque')>{{ __('Cheque') }}</option><option value="bank_transfer" @selected(old('payment_type', $payment->payment_type) === 'bank_transfer')>{{ __('Bank Transfer') }}</option><option value="exchange_of_goods" @selected(old('payment_type', $payment->payment_type) === 'exchange_of_goods')>{{ __('Exchange of Goods') }}</option></select></div>
        <div><label>{{ __('Method') }}</label><input name="payment_method" value="{{ old('payment_method', $payment->payment_method) }}"></div>
        <div><label>{{ __('Reference') }}</label><input name="reference_no" value="{{ old('reference_no', $payment->reference_no) }}"></div>
        <div><label>{{ __('Bank') }}</label><input name="bank_name" value="{{ old('bank_name', $payment->bank_name) }}"></div>
        <div><label>{{ __('Cheque Due Date') }}</label><input type="date" name="cheque_due_date" value="{{ old('cheque_due_date', $payment->cheque_due_date?->toDateString()) }}"></div>
        <div><label>{{ __('Cheque Status') }}</label><select name="cheque_status"><option value="pending" @selected(old('cheque_status', $payment->cheque_status ?? 'pending') === 'pending')>{{ __('Pending') }}</option><option value="collected" @selected(old('cheque_status', $payment->cheque_status) === 'collected')>{{ __('Collected') }}</option><option value="bounced" @selected(old('cheque_status', $payment->cheque_status) === 'bounced')>{{ __('Bounced') }}</option><option value="cancelled" @selected(old('cheque_status', $payment->cheque_status) === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $payment->notes) }}</textarea></div>
    </div>
    <p><button>{{ __('Save Payment') }}</button></p>
</form>
@endsection

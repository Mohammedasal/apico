@extends('layouts.app')

@section('content')
@php $isEdit = $voucher->exists; @endphp
<h1>{{ $isEdit ? __('Edit Expense Voucher') : __('New Expense Voucher') }}</h1>
<form method="post" action="{{ $isEdit ? route('accounting.expenses.update', $voucher) : route('accounting.expenses.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Date') }}</label><input type="date" name="expense_date" value="{{ old('expense_date', $voucher->expense_date?->toDateString() ?? now()->toDateString()) }}">@error('expense_date')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Category') }}</label><select class="searchable-select" name="expense_category_id"><option value="">{{ __('Select') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(old('expense_category_id', $voucher->expense_category_id) == $category->id)>{{ $category->localized_name }}</option>@endforeach</select>@error('expense_category_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Amount') }}</label><input id="expense-amount" type="number" step="0.001" min="0.001" name="amount" value="{{ old('amount', $voucher->amount) }}">@error('amount')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Payment Status') }}</label><select id="payment-status" name="payment_status"><option value="paid" @selected(old('payment_status', $voucher->payment_status) === 'paid')>{{ __('Paid') }}</option><option value="unpaid" @selected(old('payment_status', $voucher->payment_status) === 'unpaid')>{{ __('Unpaid') }}</option><option value="partially_paid" @selected(old('payment_status', $voucher->payment_status) === 'partially_paid')>{{ __('Partially Paid') }}</option></select></div>
        <div><label>{{ __('Paid Amount') }}</label><input id="paid-amount" type="number" step="0.001" min="0" name="paid_amount" value="{{ old('paid_amount', $voucher->paid_amount) }}">@error('paid_amount')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Payment Type') }}</label><select id="payment-type" name="payment_type"><option value="cash" @selected(old('payment_type', $voucher->payment_type) === 'cash')>{{ __('Cash') }}</option><option value="bank_transfer" @selected(old('payment_type', $voucher->payment_type) === 'bank_transfer')>{{ __('Bank Transfer') }}</option><option value="cheque" @selected(old('payment_type', $voucher->payment_type) === 'cheque')>{{ __('Cheque') }}</option><option value="credit" @selected(old('payment_type', $voucher->payment_type) === 'credit')>{{ __('Credit') }}</option></select></div>
        <div><label>{{ __('Cash Account') }}</label><select class="searchable-select" name="cash_account_id"><option value="">{{ __('Use Default') }}</option>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}" @selected(old('cash_account_id', $voucher->cash_account_id) == $account->id)>{{ $account->localized_name }}</option>@endforeach</select></div>
        <div><label>{{ __('Bank Account') }}</label><select class="searchable-select" name="bank_account_id"><option value="">{{ __('Use Default') }}</option>@foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected(old('bank_account_id', $voucher->bank_account_id) == $account->id)>{{ $account->localized_name }}</option>@endforeach</select></div>
        <div><label>{{ __('Payable Account') }}</label><select class="searchable-select" name="payable_account_id"><option value="">{{ __('Use Accrued Expenses') }}</option>@foreach ($payableAccounts as $account)<option value="{{ $account->id }}" @selected(old('payable_account_id', $voucher->payable_account_id) == $account->id)>{{ $account->code }} - {{ $account->localized_name }}</option>@endforeach</select></div>
        <div><label>{{ __('Cheque Due Date') }}</label><input type="date" name="cheque_due_date" value="{{ old('cheque_due_date', $voucher->cheque_due_date?->toDateString()) }}">@error('cheque_due_date')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Cheque Bank') }}</label><input name="cheque_bank" value="{{ old('cheque_bank', $voucher->cheque_bank) }}"></div>
        <div><label>{{ __('Reference') }}</label><input name="reference" value="{{ old('reference', $voucher->reference) }}"></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $voucher->notes) }}</textarea></div>
    </div>
    <p><button>{{ __('Post Expense') }}</button></p>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const status = document.getElementById('payment-status');
    const amount = document.getElementById('expense-amount');
    const paid = document.getElementById('paid-amount');
    const type = document.getElementById('payment-type');

    function syncPayment() {
        if (status.value === 'paid') {
            paid.value = amount.value;
            if (type.value === 'credit') type.value = 'cash';
        } else if (status.value === 'unpaid') {
            paid.value = '0.000';
            type.value = 'credit';
        } else if (type.value === 'credit') {
            type.value = 'cash';
        }
    }

    status.addEventListener('change', syncPayment);
    amount.addEventListener('input', function () {
        if (status.value === 'paid') paid.value = amount.value;
    });
    syncPayment();
});
</script>
@endsection

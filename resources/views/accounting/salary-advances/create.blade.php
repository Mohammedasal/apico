@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Add Salary Advance') }}</h1><div class="muted">{{ __('Record the payment now; it will be deducted automatically from month-end payroll.') }}</div></div>
    <a class="button" href="{{ route('accounting.salary-advances.index') }}">{{ __('Salary Advance History') }}</a>
</div>

<form method="post" action="{{ route('accounting.salary-advances.store') }}">
    @csrf
    <div class="form-grid">
        <div><label>{{ __('Employee') }}</label><select class="searchable-select" name="employee_id" required><option value="">{{ __('Select Employee') }}</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected(old('employee_id', $selectedEmployee) == $employee->id)>{{ $employee->localized_name }} - {{ number_format($employee->base_salary, 3) }} {{ __('JOD') }}</option>@endforeach</select></div>
        <div><label>{{ __('Payment Date') }}</label><input type="date" name="payment_date" value="{{ old('payment_date', $paymentDate) }}" required></div>
        <div><label>{{ __('Amount') }}</label><input type="number" name="amount" min="0.001" step="0.001" value="{{ old('amount') }}" required></div>
        <div><label>{{ __('Payment Type') }}</label><select name="payment_type" required><option value="cash" @selected(old('payment_type') === 'cash')>{{ __('Cash') }}</option><option value="bank_transfer" @selected(old('payment_type') === 'bank_transfer')>{{ __('Bank Transfer') }}</option></select></div>
        <div><label>{{ __('Cash Account') }}</label><select class="searchable-select" name="cash_account_id"><option value="">{{ __('Use Default') }}</option>@foreach ($cashAccounts as $account)<option value="{{ $account->id }}" @selected(old('cash_account_id') == $account->id)>{{ $account->localized_name }}</option>@endforeach</select></div>
        <div><label>{{ __('Bank Account') }}</label><select class="searchable-select" name="bank_account_id"><option value="">{{ __('Use Default') }}</option>@foreach ($bankAccounts as $account)<option value="{{ $account->id }}" @selected(old('bank_account_id') == $account->id)>{{ $account->localized_name }}</option>@endforeach</select></div>
        <div><label>{{ __('Reference') }}</label><input name="reference" value="{{ old('reference') }}"></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes') }}</textarea></div>
    </div>
    <p><button>{{ __('Post Advance') }}</button></p>
</form>
@endsection

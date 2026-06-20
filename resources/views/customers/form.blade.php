@extends('layouts.app')

@section('content')
<h1>{{ $customer->exists ? __('Edit Customer') : __('New Customer') }}</h1>
<form method="post" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}">
    @csrf
    @if ($customer->exists) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Name') }}</label><input name="name" value="{{ old('name', $customer->name) }}">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Phone') }}</label><input name="phone" value="{{ old('phone', $customer->phone) }}"></div>
        <div><label>{{ __('Location') }}</label><input name="location" value="{{ old('location', $customer->location) }}"></div>
        <div><label>{{ __('Opening Balance') }}</label><input type="number" step="0.001" name="opening_balance" value="{{ old('opening_balance', $customer->opening_balance ?? 0) }}"></div>
        <div><label>{{ __('Opening Weight Kg') }}</label><input type="number" step="0.001" name="opening_weight_balance_kg" value="{{ old('opening_weight_balance_kg', $customer->opening_weight_balance_kg ?? 0) }}"></div>
        <div><label>{{ __('Status') }}</label><select name="status"><option value="active" @selected(old('status', $customer->status) === 'active')>{{ __('Active') }}</option><option value="inactive" @selected(old('status', $customer->status) === 'inactive')>{{ __('Inactive') }}</option></select></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $customer->notes) }}</textarea></div>
    </div>
    <p><button>{{ __('Save') }}</button></p>
</form>
@endsection

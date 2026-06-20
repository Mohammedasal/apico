@extends('layouts.app')

@section('content')
@php $isEdit = $supplier->exists; @endphp
<h1>{{ $isEdit ? __('Edit Supplier') : __('New Supplier') }}</h1>
<form method="post" action="{{ $isEdit ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Name') }}</label><input name="name" value="{{ old('name', $supplier->name) }}">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Phone') }}</label><input name="phone" value="{{ old('phone', $supplier->phone) }}"></div>
        <div><label>{{ __('Location') }}</label><input name="location" value="{{ old('location', $supplier->location) }}"></div>
        <div><label>{{ __('Opening Balance JOD') }}</label><input type="number" step="0.001" name="opening_balance" value="{{ old('opening_balance', $supplier->opening_balance ?? 0) }}"></div>
        <div><label>{{ __('Status') }}</label><select name="status"><option value="active" @selected(old('status', $supplier->status ?? 'active') === 'active')>{{ __('Active') }}</option><option value="inactive" @selected(old('status', $supplier->status) === 'inactive')>{{ __('Inactive') }}</option></select></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $supplier->notes) }}</textarea></div>
    </div>
    <p><button>{{ __('Save Supplier') }}</button></p>
</form>
@endsection

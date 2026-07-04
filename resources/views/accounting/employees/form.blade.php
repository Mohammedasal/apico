@extends('layouts.app')

@section('content')
@php $isEdit = $employee->exists; @endphp
<h1>{{ $isEdit ? __('Edit Employee') : __('New Employee') }}</h1>
<form method="post" action="{{ $isEdit ? route('accounting.employees.update', $employee) : route('accounting.employees.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('English Name') }}</label><input name="name_en" value="{{ old('name_en', $employee->name_en) }}">@error('name_en')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Arabic Name') }}</label><input dir="rtl" name="name_ar" value="{{ old('name_ar', $employee->name_ar) }}"></div>
        <div><label>{{ __('Phone') }}</label><input name="phone" value="{{ old('phone', $employee->phone) }}"></div>
        <div><label>{{ __('Position') }}</label><input name="position" value="{{ old('position', $employee->position) }}"></div>
        <div><label>{{ __('Base Salary') }}</label><input type="number" step="0.001" min="0" name="base_salary" value="{{ old('base_salary', $employee->base_salary ?? 0) }}">@error('base_salary')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Status') }}</label><select name="is_active"><option value="1" @selected((string) old('is_active', (int) $employee->is_active) === '1')>{{ __('Active') }}</option><option value="0" @selected((string) old('is_active', (int) $employee->is_active) === '0')>{{ __('Inactive') }}</option></select></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $employee->notes) }}</textarea></div>
    </div>
    <p><button>{{ __('Save Employee') }}</button></p>
</form>
@endsection

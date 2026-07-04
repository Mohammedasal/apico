@extends('layouts.app')

@section('content')
@php $isEdit = $category->exists; @endphp
<h1>{{ $isEdit ? __('Edit Expense Category') : __('New Expense Category') }}</h1>
<form method="post" action="{{ $isEdit ? route('accounting.expense-categories.update', $category) : route('accounting.expense-categories.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('English Name') }}</label><input name="name_en" value="{{ old('name_en', $category->name_en) }}">@error('name_en')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Arabic Name') }}</label><input dir="rtl" name="name_ar" value="{{ old('name_ar', $category->name_ar) }}">@error('name_ar')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Default Expense Account') }}</label><select class="searchable-select" name="default_account_id">@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected(old('default_account_id', $category->default_account_id) == $account->id)>{{ $account->code }} - {{ $account->localized_name }}</option>@endforeach</select>@error('default_account_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Status') }}</label><select name="is_active"><option value="1" @selected((string) old('is_active', (int) $category->is_active) === '1')>{{ __('Active') }}</option><option value="0" @selected((string) old('is_active', (int) $category->is_active) === '0')>{{ __('Inactive') }}</option></select></div>
    </div>
    <p><button>{{ __('Save Category') }}</button></p>
</form>
@endsection

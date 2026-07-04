@extends('layouts.app')

@section('content')
@php $isEdit = $account->exists; @endphp
<h1>{{ $isEdit ? __('Edit Account') : __('New Account') }}</h1>
<form method="post" action="{{ $isEdit ? route('accounting.accounts.update', $account) : route('accounting.accounts.store') }}">
    @csrf
    @if ($isEdit) @method('put') @endif
    <div class="form-grid">
        <div><label>{{ __('Code') }}</label><input name="code" value="{{ old('code', $account->code) }}">@error('code')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Parent Account') }}</label><select class="searchable-select" name="parent_id"><option value="">{{ __('None') }}</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id', $account->parent_id) == $parent->id)>{{ $parent->code }} - {{ $parent->localized_name }}</option>@endforeach</select>@error('parent_id')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('English Name') }}</label><input name="name_en" value="{{ old('name_en', $account->name_en) }}">@error('name_en')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Arabic Name') }}</label><input dir="rtl" name="name_ar" value="{{ old('name_ar', $account->name_ar) }}">@error('name_ar')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('Type') }}</label><select name="type">@foreach (\App\Models\ChartOfAccount::TYPES as $type)<option value="{{ $type }}" @selected(old('type', $account->type) === $type)>{{ __(ucwords(str_replace('_', ' ', $type))) }}</option>@endforeach</select></div>
        <div><label>{{ __('Normal Balance') }}</label><select name="normal_balance"><option value="debit" @selected(old('normal_balance', $account->normal_balance) === 'debit')>{{ __('Debit') }}</option><option value="credit" @selected(old('normal_balance', $account->normal_balance) === 'credit')>{{ __('Credit') }}</option></select></div>
        <div><label>{{ __('Posting') }}</label><select name="is_posting"><option value="1" @selected((string) old('is_posting', (int) $account->is_posting) === '1')>{{ __('Posting Account') }}</option><option value="0" @selected((string) old('is_posting', (int) $account->is_posting) === '0')>{{ __('Grouping Account') }}</option></select></div>
        <div><label>{{ __('Status') }}</label><select name="is_active"><option value="1" @selected((string) old('is_active', (int) $account->is_active) === '1')>{{ __('Active') }}</option><option value="0" @selected((string) old('is_active', (int) $account->is_active) === '0')>{{ __('Inactive') }}</option></select></div>
        <div><label>{{ __('Sort Order') }}</label><input type="number" name="sort_order" value="{{ old('sort_order', $account->sort_order) }}"></div>
    </div>
    <p><button>{{ __('Save Account') }}</button></p>
</form>
@endsection

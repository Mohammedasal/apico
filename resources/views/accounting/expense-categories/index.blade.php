@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Expense Categories') }}</h1><div class="muted">{{ __('Each category is linked to its accounting expense account.') }}</div></div>
    <a class="button" href="{{ route('accounting.expense-categories.create') }}">{{ __('New Category') }}</a>
</div>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('English Name') }}</th><th>{{ __('Arabic Name') }}</th><th>{{ __('Default Expense Account') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($categories as $category)
        <tr>
            <td>{{ $category->name_en }}</td>
            <td dir="rtl">{{ $category->name_ar }}</td>
            <td>{{ $category->defaultAccount->code }} - {{ $category->defaultAccount->localized_name }}</td>
            <td>{{ $category->is_active ? __('Active') : __('Inactive') }}</td>
            <td><a href="{{ route('accounting.expense-categories.edit', $category) }}">{{ __('Edit') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection

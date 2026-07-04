@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Chart of Accounts') }}</h1><div class="muted">{{ __('Hierarchical accounting accounts in English and Arabic.') }}</div></div>
    <a class="button" href="{{ route('accounting.accounts.create') }}">{{ __('New Account') }}</a>
</div>

<form class="filters" method="get">
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search }}" placeholder="{{ __('Code or account name') }}"></div>
    <div><label>{{ __('Type') }}</label><select name="type"><option value="">{{ __('All') }}</option>@foreach (\App\Models\ChartOfAccount::TYPES as $option)<option value="{{ $option }}" @selected($type === $option)>{{ __(ucwords(str_replace('_', ' ', $option))) }}</option>@endforeach</select></div>
    <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option><option value="active" @selected($status === 'active')>{{ __('Active') }}</option><option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option></select></div>
    <div><button>{{ __('Filter') }}</button></div>
</form>

<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Code') }}</th><th>{{ __('English Name') }}</th><th>{{ __('Arabic Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Normal Balance') }}</th><th>{{ __('Posting') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($accounts as $account)
        <tr>
            <td><strong>{{ $account->code }}</strong></td>
            <td>{{ $account->name_en }}</td>
            <td dir="rtl">{{ $account->name_ar }}</td>
            <td>{{ __(ucwords(str_replace('_', ' ', $account->type))) }}</td>
            <td>{{ __(ucfirst($account->normal_balance)) }}</td>
            <td>{{ $account->is_posting ? __('Yes') : __('Group') }}</td>
            <td>{{ $account->is_active ? __('Active') : __('Inactive') }}</td>
            <td><a href="{{ route('accounting.accounts.edit', $account) }}">{{ __('Edit') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection

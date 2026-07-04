@extends('layouts.app')

@section('content')
<div class="section-title"><div><h1>{{ __('Account Mappings') }}</h1><div class="muted">{{ __('Accounts used for automatic operational postings.') }}</div></div></div>
<form method="post" action="{{ route('accounting.mappings.update') }}">
    @csrf
    @method('put')
    <div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Mapping Key') }}</th><th>{{ __('Posting Account') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @foreach ($mappings as $mapping)
            <tr>
                <td><strong>{{ $mapping->mapping_key }}</strong></td>
                <td><select class="searchable-select" name="mappings[{{ $mapping->id }}]">@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected($mapping->account_id === $account->id)>{{ $account->code }} - {{ $account->localized_name }}</option>@endforeach</select></td>
                <td>{{ $mapping->is_active ? __('Active') : __('Inactive') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <p><button>{{ __('Save Mappings') }}</button></p>
</form>
@endsection

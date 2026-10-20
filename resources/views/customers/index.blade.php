@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __('Customers') }}</h1>
    @if (auth()->user()?->canWriteOperationalData())
        <a class="button" href="{{ route('customers.create') }}">{{ __('New Customer') }}</a>
    @endif
</div>
<form class="filters" method="get">
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search ?? '' }}" placeholder="{{ __('Name, phone, location, notes') }}"></div>
    <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option><option value="active" @selected($status === 'active')>{{ __('Active') }}</option><option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option></select></div>
    <div><label>{{ __('Rows') }}</label><select name="per_page">@foreach ([25, 50, 100] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>@endforeach</select></div>
    <div><button>{{ __('Search') }}</button></div>
    @if (($search ?? '') !== '' || $status !== '')
        <div><a class="button" href="{{ route('customers.index') }}">{{ __('Clear') }}</a></div>
    @endif
</form>
@php
    $sortUrl = function (string $column) use ($sort, $direction) {
        $query = request()->except('page');
        $query['sort'] = $column;
        $query['direction'] = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
        return route('customers.index', $query);
    };
    $sortIndicator = fn (string $column) => $sort === $column ? ($direction === 'asc' ? '&uarr;' : '&darr;') : '';
@endphp
<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th><a class="sort-link" href="{{ $sortUrl('name') }}">{{ __('Name') }} <span>{!! $sortIndicator('name') !!}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('balance_kg') }}">{{ __('Remaining Balance Kg') }} <span>{!! $sortIndicator('balance_kg') !!}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('balance_jod') }}">{{ __('Remaining Balance JOD') }} <span>{!! $sortIndicator('balance_jod') !!}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('status') }}">{{ __('Status') }} <span>{!! $sortIndicator('status') !!}</span></a></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    @foreach ($customers as $customer)
        <tr>
            <td data-sort-value="{{ $customer->name }}">{{ $customer->name }}</td>
            <td data-sort-value="{{ $customer->remaining_balance_kg }}" @class(['amount-positive' => $customer->remaining_balance_kg >= 0, 'amount-negative' => $customer->remaining_balance_kg < 0])>{{ number_format($customer->remaining_balance_kg, 3) }}</td>
            <td data-sort-value="{{ $customer->remaining_balance_jod }}" @class(['amount-positive' => $customer->remaining_balance_jod >= 0, 'amount-negative' => $customer->remaining_balance_jod < 0])>{{ number_format($customer->remaining_balance_jod, 3) }}</td>
            <td data-sort-value="{{ $customer->status }}">{{ __(ucfirst($customer->status)) }}</td>
            <td>
                <a href="{{ route('customers.show', $customer) }}">{{ __('Statement') }}</a>
                @if (auth()->user()?->canWriteOperationalData())
                    | <a href="{{ route('customers.edit', $customer) }}">{{ __('Edit') }}</a>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $customers->links() }}
@endsection

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
    <div><button>{{ __('Search') }}</button></div>
    @if (($search ?? '') !== '')
        <div><a class="button" href="{{ route('customers.index') }}">{{ __('Clear') }}</a></div>
    @endif
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Remaining Balance Kg') }}</th><th>{{ __('Remaining Balance JOD') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($customers as $customer)
        <tr>
            <td>{{ $customer->name }}</td>
            <td @class(['amount-positive' => $customer->remaining_balance_kg >= 0, 'amount-negative' => $customer->remaining_balance_kg < 0])>{{ number_format($customer->remaining_balance_kg, 3) }}</td>
            <td @class(['amount-positive' => $customer->remaining_balance_jod >= 0, 'amount-negative' => $customer->remaining_balance_jod < 0])>{{ number_format($customer->remaining_balance_jod, 3) }}</td>
            <td>{{ __(ucfirst($customer->status)) }}</td>
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

@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __('Suppliers') }}</h1>
    @if (auth()->user()?->canWriteOperationalData())
        <a class="button" href="{{ route('suppliers.create') }}">{{ __('New Supplier') }}</a>
    @endif
</div>
<form class="filters" method="get">
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $search ?? '' }}" placeholder="{{ __('Name, phone, location, notes') }}"></div>
    <div><button>{{ __('Search') }}</button></div>
    @if (($search ?? '') !== '')
        <div><a class="button" href="{{ route('suppliers.index') }}">{{ __('Clear') }}</a></div>
    @endif
</form>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Location') }}</th><th>{{ __('Opening Balance') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($suppliers as $supplier)
        <tr>
            <td>{{ $supplier->name }}</td>
            <td>{{ $supplier->phone }}</td>
            <td>{{ $supplier->location }}</td>
            <td>{{ number_format($supplier->opening_balance, 3) }}</td>
            <td>{{ __(ucfirst($supplier->status)) }}</td>
            <td>
                <a href="{{ route('suppliers.show', $supplier) }}">{{ __('Statement') }}</a>
                @if (auth()->user()?->canWriteOperationalData())
                    | <a href="{{ route('suppliers.edit', $supplier) }}">{{ __('Edit') }}</a>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $suppliers->links() }}
@endsection

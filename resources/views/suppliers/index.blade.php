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
<table id="suppliersTable">
    <thead><tr><th><button class="table-sort" type="button" data-sort-column="0" data-sort-type="text">{{ __('Name') }} <span aria-hidden="true">--</span></button></th><th><button class="table-sort" type="button" data-sort-column="1" data-sort-type="number">{{ __('Remaining Balance JOD') }} <span aria-hidden="true">--</span></button></th><th><button class="table-sort" type="button" data-sort-column="2" data-sort-type="text">{{ __('Status') }} <span aria-hidden="true">--</span></button></th><th></th></tr></thead>
    <tbody>
    @foreach ($suppliers as $supplier)
        <tr>
            <td data-sort-value="{{ $supplier->name }}">{{ $supplier->name }}</td>
            <td data-sort-value="{{ $supplier->remaining_balance_jod }}" @class(['amount-positive' => $supplier->remaining_balance_jod >= 0, 'amount-negative' => $supplier->remaining_balance_jod < 0])>{{ number_format($supplier->remaining_balance_jod, 3) }}</td>
            <td data-sort-value="{{ $supplier->status }}">{{ __(ucfirst($supplier->status)) }}</td>
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
<script>
document.querySelectorAll('#suppliersTable .table-sort').forEach((button) => {
    button.addEventListener('click', () => {
        const table = button.closest('table');
        const tbody = table.querySelector('tbody');
        const column = Number(button.dataset.sortColumn);
        const type = button.dataset.sortType;
        const currentDirection = button.dataset.direction === 'asc' ? 'desc' : 'asc';

        table.querySelectorAll('.table-sort').forEach((sortButton) => {
            sortButton.dataset.direction = '';
            sortButton.querySelector('span').textContent = '--';
        });

        button.dataset.direction = currentDirection;
        button.querySelector('span').textContent = currentDirection === 'asc' ? 'ASC' : 'DESC';

        [...tbody.querySelectorAll('tr')]
            .sort((leftRow, rightRow) => {
                const leftCell = leftRow.children[column];
                const rightCell = rightRow.children[column];
                const leftValue = leftCell.dataset.sortValue || leftCell.textContent.trim();
                const rightValue = rightCell.dataset.sortValue || rightCell.textContent.trim();
                const result = type === 'number'
                    ? Number(leftValue) - Number(rightValue)
                    : leftValue.localeCompare(rightValue, undefined, { sensitivity: 'base' });

                return currentDirection === 'asc' ? result : -result;
            })
            .forEach((row) => tbody.appendChild(row));
    });
});
</script>
@endsection

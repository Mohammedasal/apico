@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __($config['title']) }}</h1>
    @if (auth()->user()?->canWriteOperationalData())
        <a class="button" href="{{ route('operations.create', $module) }}">{{ __('New :item', ['item' => __($config['title'])]) }}</a>
    @endif
</div>
<form class="filters" method="get" style="margin-bottom:14px">
    <input type="hidden" name="sort" value="{{ $filters['sort'] }}">
    <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
    @if ($customers->isNotEmpty())
        <div>
            <label>{{ __('Customer') }}</label>
            <select name="customer_id" class="searchable-select" data-placeholder="{{ __('All customers') }}">
                <option value="">{{ __('All customers') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected(($filters['customer_id'] ?? null) === $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if ($suppliers->isNotEmpty())
        <div>
            <label>{{ __('Supplier') }}</label>
            <select name="supplier_id" class="searchable-select" data-placeholder="{{ __('All suppliers') }}">
                <option value="">{{ __('All suppliers') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? null) === $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if ($materials->isNotEmpty())
        <div>
            <label>{{ __('Material') }}</label>
            <select name="material_id" class="searchable-select" data-placeholder="{{ __('All materials') }}">
                <option value="">{{ __('All materials') }}</option>
                @foreach ($materials as $material)
                    <option value="{{ $material->id }}" @selected(($filters['material_id'] ?? null) === $material->id)>{{ $material->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
    <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
    @if ($module !== 'payments')
        <div><label>{{ __('Min Weight Kg') }}</label><input type="number" step="0.001" name="min_weight" value="{{ $filters['min_weight'] ?? '' }}"></div>
        <div><label>{{ __('Max Weight Kg') }}</label><input type="number" step="0.001" name="max_weight" value="{{ $filters['max_weight'] ?? '' }}"></div>
    @endif
    @if ($module !== 'recycle-in')
        <div><label>{{ __('Min Amount JOD') }}</label><input type="number" step="0.001" name="min_amount" value="{{ $filters['min_amount'] ?? '' }}"></div>
        <div><label>{{ __('Max Amount JOD') }}</label><input type="number" step="0.001" name="max_amount" value="{{ $filters['max_amount'] ?? '' }}"></div>
    @endif
    @if ($module === 'payments')
        <div>
            <label>{{ __('Payment Type') }}</label>
            <select name="payment_type">
                <option value="">{{ __('All payment types') }}</option>
                @foreach (['cash' => 'Cash', 'cheque' => 'Cheque', 'bank_transfer' => 'Bank Transfer', 'exchange_of_goods' => 'Exchange of Goods'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['payment_type'] ?? null) === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>{{ __('Cheque Status') }}</label>
            <select name="cheque_status">
                <option value="">{{ __('All cheque statuses') }}</option>
                @foreach (['pending' => 'Pending', 'collected' => 'Collected', 'bounced' => 'Bounced', 'cancelled' => 'Cancelled'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['cheque_status'] ?? null) === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label>{{ __('Search') }}</label>
        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ $module === 'payments' ? __('Notes, reference, bank') : __('Search notes') }}">
    </div>
    <div><button>{{ __('Search') }}</button></div>
    <div><a class="button" href="{{ route('operations.index', $module) }}">{{ __('Clear') }}</a></div>
</form>
@php
    $sortUrl = function (string $column) use ($module, $filters) {
        $query = request()->except('page');
        $query['sort'] = $column;
        $query['direction'] = $filters['sort'] === $column && $filters['direction'] === 'asc' ? 'desc' : 'asc';

        return route('operations.index', array_merge(['module' => $module], $query));
    };
    $sortIndicator = fn (string $column) => $filters['sort'] === $column
        ? ($filters['direction'] === 'asc' ? '&uarr;' : '&darr;')
        : '';
@endphp
<div class="table-wrap">
<table>
    <thead>
    <tr>
        <th><a class="sort-link" href="{{ $sortUrl('date') }}">{{ __('Date') }} <span>{!! $sortIndicator('date') !!}</span></a></th>
        @if ($module !== 'stock-purchases')<th><a class="sort-link" href="{{ $sortUrl('customer') }}">{{ __('Customer') }} <span>{!! $sortIndicator('customer') !!}</span></a></th>@endif
        @if ($module === 'stock-purchases')<th><a class="sort-link" href="{{ $sortUrl('supplier') }}">{{ __('Supplier') }} <span>{!! $sortIndicator('supplier') !!}</span></a></th>@endif
        @if ($module !== 'payments')
            <th><a class="sort-link" href="{{ $sortUrl('material') }}">{{ __('Material') }} <span>{!! $sortIndicator('material') !!}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('weight') }}">{{ __('Weight Kg') }} <span>{!! $sortIndicator('weight') !!}</span></a></th>
        @endif
        <th><a class="sort-link" href="{{ $sortUrl('amount') }}">{{ $module === 'recycle-out' ? __('Calculation / Amount JOD') : __('Amount') }} <span>{!! $sortIndicator('amount') !!}</span></a></th>
        @if ($module === 'payments')
            <th><a class="sort-link" href="{{ $sortUrl('type') }}">{{ __('Type') }} <span>{!! $sortIndicator('type') !!}</span></a></th>
            <th><a class="sort-link" href="{{ $sortUrl('cheque') }}">{{ __('Cheque') }} <span>{!! $sortIndicator('cheque') !!}</span></a></th>
        @endif
        <th><a class="sort-link" href="{{ $sortUrl('notes') }}">{{ __('Notes') }} <span>{!! $sortIndicator('notes') !!}</span></a></th>
        <th><a class="sort-link" href="{{ $sortUrl('audit') }}">{{ __('Audit') }} <span>{!! $sortIndicator('audit') !!}</span></a></th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    @foreach ($records as $record)
        <tr>
            <td>{{ $record->date?->toDateString() }}</td>
            @if ($module !== 'stock-purchases')<td>{{ $record->customer->name ?? '' }}</td>@endif
            @if ($module === 'stock-purchases')<td>{{ $record->supplier?->name ?? $record->supplier_name }}</td>@endif
            @if ($module !== 'payments')
                <td>{{ $record->material->name ?? '-' }}</td>
                <td>
                    {{ number_format($record->weight_kg, 3) }}
                    @if ($module === 'recycle-out')
                        <div class="muted">R {{ number_format($record->recycled_out_kg, 3) }} | W {{ number_format($record->waste_kg, 3) }} | NR {{ number_format($record->non_recycled_kg, 3) }}</div>
                    @endif
                </td>
            @endif
            <td>
                @if ($module === 'recycle-out')
                    {{ number_format($record->recycled_out_kg, 3) }} x {{ number_format($record->rate_per_kg, 3) }} = {{ number_format($record->total_amount, 3) }}
                @elseif (isset($record->total_amount)) {{ number_format($record->total_amount, 3) }}
                @elseif (isset($record->amount)) {{ number_format($record->amount, 3) }}
                @elseif (isset($record->total_cost)) {{ number_format($record->total_cost, 3) }}
                    @else
                        {{ number_format($record->sales_value, 3) }}
                        @if (auth()->user()?->canViewProfitAndLoss())
                            / {{ __('profit') }} {{ number_format($record->net_profit, 3) }}
                        @endif
                @endif
            </td>
            @if ($module === 'payments')
                <td>{{ __(ucwords(str_replace('_', ' ', $record->payment_type ?? 'cash'))) }}</td>
                <td>{{ $record->payment_type === 'cheque' ? (($record->cheque_due_date?->toDateString() ?? __('No due date')).' | '.__(ucfirst($record->cheque_status ?? 'pending'))) : '-' }}</td>
            @endif
            <td>{{ $record->notes }}</td>
            <td>
                <div>{{ __('Created') }} {{ $record->created_at?->format('Y-m-d H:i') }}</div>
                <div class="muted">{{ __('By') }} {{ $record->creator?->name ?? __('Import/System') }}</div>
                @if ($record->updated_by)
                    <div>{{ __('Edited') }} {{ $record->updated_at?->format('Y-m-d H:i') }}</div>
                    <div class="muted">{{ __('By') }} {{ $record->editor?->name ?? __('System') }}</div>
                @endif
            </td>
            <td>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    @if (auth()->user()?->canWriteOperationalData())
                        <a href="{{ route('operations.edit', [$module, $record->id]) }}">{{ __('Edit') }}</a>
                    @endif
                    @if (auth()->user()?->role === 'admin')
                        <form method="post" action="{{ route('operations.destroy', [$module, $record->id]) }}" style="margin:0;padding:0;border:0;background:transparent;box-shadow:none" onsubmit="return confirm(@json(__('Delete this transaction? This action cannot be undone.')))">
                            @csrf
                            @method('delete')
                            <button type="submit" style="background:var(--red);padding:6px 9px">{{ __('Delete') }}</button>
                        </form>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $records->links() }}
@endsection

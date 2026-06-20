@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __($config['title']) }}</h1>
    @if (auth()->user()?->canWriteOperationalData())
        <a class="button" href="{{ route('operations.create', $module) }}">{{ __('New :item', ['item' => __($config['title'])]) }}</a>
    @endif
</div>
<div class="table-wrap">
<table>
    <thead>
    <tr>
        <th>{{ __('Date') }}</th>
        @if ($module !== 'stock-purchases')<th>{{ __('Customer') }}</th>@endif
        @if ($module === 'stock-purchases')<th>{{ __('Supplier') }}</th>@endif
        @if ($module !== 'payments')<th>{{ __('Material') }}</th><th>{{ __('Weight Kg') }}</th>@endif
        <th>{{ $module === 'recycle-out' ? __('Calculation / Amount JOD') : __('Amount') }}</th>
        @if ($module === 'payments')<th>{{ __('Type') }}</th><th>{{ __('Cheque') }}</th>@endif
        <th>{{ __('Notes') }}</th>
        <th>{{ __('Audit') }}</th>
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
                    @else {{ number_format($record->sales_value, 3) }} / {{ __('profit') }} {{ number_format($record->net_profit, 3) }}
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
                @if (auth()->user()?->canWriteOperationalData())
                    <a href="{{ route('operations.edit', [$module, $record->id]) }}">{{ __('Edit') }}</a>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $records->links() }}
@endsection

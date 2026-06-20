@extends('layouts.app')

@section('content')
<div class="toolbar">
    <h1>{{ __('Supplier Payments') }}</h1>
    @if (auth()->user()?->canWriteOperationalData())
        <a class="button" href="{{ route('supplier-payments.create') }}">{{ __('New Payment') }}</a>
    @endif
</div>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Type') }}</th><th>{{ __('Cheque') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Audit') }}</th><th></th></tr></thead>
    <tbody>
    @foreach ($payments as $payment)
        <tr>
            <td>{{ $payment->date?->toDateString() }}</td>
            <td>{{ $payment->supplier?->name }}</td>
            <td>{{ number_format($payment->amount, 3) }}</td>
            <td>{{ __(ucwords(str_replace('_', ' ', $payment->payment_type ?? 'cash'))) }}</td>
            <td>{{ $payment->payment_type === 'cheque' ? (($payment->cheque_due_date?->toDateString() ?? __('No due date')).' | '.__(ucfirst($payment->cheque_status ?? 'pending'))) : '-' }}</td>
            <td>{{ $payment->notes }}</td>
            <td>
                <div>{{ __('Created') }} {{ $payment->created_at?->format('Y-m-d H:i') }}</div>
                <div class="muted">{{ __('By') }} {{ $payment->creator?->name ?? __('Import/System') }}</div>
                @if ($payment->updated_by)
                    <div>{{ __('Edited') }} {{ $payment->updated_at?->format('Y-m-d H:i') }}</div>
                    <div class="muted">{{ __('By') }} {{ $payment->editor?->name ?? __('System') }}</div>
                @endif
            </td>
            <td>@if (auth()->user()?->canWriteOperationalData())<a href="{{ route('supplier-payments.edit', $payment) }}">{{ __('Edit') }}</a>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $payments->links() }}
@endsection

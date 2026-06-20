@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div>
        <h1>{{ __('Cheques In') }}</h1>
        <div class="muted">{{ __('Incoming cheques generated from payments with type Cheque.') }}</div>
    </div>
    <form class="filters" method="get">
        <div><label>{{ __('Status') }}</label><select name="status"><option value="">{{ __('All') }}</option><option value="pending" @selected($status === 'pending')>{{ __('Pending') }}</option><option value="collected" @selected($status === 'collected')>{{ __('Collected') }}</option><option value="bounced" @selected($status === 'bounced')>{{ __('Bounced') }}</option><option value="cancelled" @selected($status === 'cancelled')>{{ __('Cancelled') }}</option></select></div>
        <div><button>{{ __('Filter') }}</button></div>
    </form>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Due Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Payment Date') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Cheque No.') }}</th><th>{{ __('Status') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Audit') }}</th><th></th></tr></thead>
        <tbody>
        @forelse ($cheques as $payment)
            <tr>
                <td>{{ $payment->cheque_due_date?->toDateString() ?? '-' }}</td>
                <td>{{ $payment->customer->name }}</td>
                <td>{{ $payment->date->toDateString() }}</td>
                <td>{{ number_format($payment->amount, 3) }}</td>
                <td>{{ $payment->bank_name }}</td>
                <td>{{ $payment->reference_no }}</td>
                <td>{{ __(ucfirst($payment->cheque_status ?? 'pending')) }}</td>
                <td>{{ $payment->notes }}</td>
                <td>
                    <div>{{ __('Created') }} {{ $payment->created_at?->format('Y-m-d H:i') }}</div>
                    <div class="muted">{{ __('By') }} {{ $payment->creator?->name ?? __('Import/System') }}</div>
                    @if ($payment->updated_by)
                        <div>{{ __('Edited') }} {{ $payment->updated_at?->format('Y-m-d H:i') }}</div>
                        <div class="muted">{{ __('By') }} {{ $payment->editor?->name ?? __('System') }}</div>
                    @endif
                </td>
                <td>
                    @if (auth()->user()?->canWriteOperationalData())
                        <form method="post" action="{{ route('cheques-in.update', $payment) }}" class="filters" style="padding:0;border:0;background:transparent;grid-template-columns:1fr auto;min-width:220px">
                            @csrf
                            @method('put')
                            <select name="cheque_status"><option value="pending" @selected($payment->cheque_status === 'pending')>{{ __('Pending') }}</option><option value="collected" @selected($payment->cheque_status === 'collected')>{{ __('Collected') }}</option><option value="bounced" @selected($payment->cheque_status === 'bounced')>{{ __('Bounced') }}</option><option value="cancelled" @selected($payment->cheque_status === 'cancelled')>{{ __('Cancelled') }}</option></select>
                            <button>{{ __('Save') }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10">{{ __('No incoming cheques.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $cheques->links() }}
@endsection

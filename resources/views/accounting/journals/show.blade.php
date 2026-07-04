@extends('layouts.app')

@section('content')
<div class="section-title">
    <div><h1>{{ __('Journal Entry') }} {{ $journal->entry_no }}</h1><div class="muted">{{ $journal->entry_date->toDateString() }} | {{ __(ucfirst($journal->status)) }} | {{ $journal->source_module ?? __('Manual') }}</div></div>
    @if ($journal->status === 'posted')
        <form method="post" action="{{ route('accounting.journals.reverse', $journal) }}" onsubmit="return confirm('{{ __('Reverse this journal entry?') }}')">@csrf<button>{{ __('Reverse') }}</button></form>
    @endif
</div>
<div class="card" style="margin-bottom:14px">
    <strong>{{ __('Memo') }}:</strong> {{ app()->getLocale() === 'ar' ? ($journal->memo_ar ?: $journal->memo_en) : ($journal->memo_en ?: $journal->memo_ar) }}
    @if ($journal->source_type)<span class="muted"> | {{ $journal->source_type }} #{{ $journal->source_id }} | {{ $journal->posting_type }}</span>@endif
</div>
<div class="table-wrap">
<table>
    <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Description') }}</th><th>{{ __('Customer / Supplier') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th></tr></thead>
    <tbody>
    @foreach ($journal->lines as $line)
        <tr>
            <td>{{ $line->account->code }} - {{ $line->account->localized_name }}</td>
            <td>{{ app()->getLocale() === 'ar' ? ($line->description_ar ?: $line->description_en) : ($line->description_en ?: $line->description_ar) }}</td>
            <td>{{ $line->customer?->name ?? $line->supplier?->name ?? '-' }}</td>
            <td>{{ $line->debit > 0 ? number_format($line->debit, 3) : '-' }}</td>
            <td>{{ $line->credit > 0 ? number_format($line->credit, 3) : '-' }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot><tr><th colspan="3">{{ __('Total') }}</th><th>{{ number_format($journal->total_debit, 3) }}</th><th>{{ number_format($journal->total_credit, 3) }}</th></tr></tfoot>
</table>
</div>
@endsection

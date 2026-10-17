@extends('layouts.app')

@section('content')
<div class="section-title"><div><h1>{{ __('General Ledger') }}</h1><div class="muted">{{ __('Posted journal lines with opening and running balances.') }}</div></div></div>
<form class="filters" method="get">
    <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $filters['from'] }}"></div>
    <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $filters['to'] }}"></div>
    <div><label>{{ __('Account') }}</label><select class="searchable-select" name="account_id"><option value="">{{ __('All') }}</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected($filters['account_id'] == $account->id)>{{ $account->code }} - {{ $account->localized_name }}</option>@endforeach</select></div>
    <div><label>{{ __('Search') }}</label><input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Entry number or memo') }}"></div>
    <div><button>{{ __('Apply') }}</button></div>
</form>
@include('accounting.reports.partials.export-form', ['routeName' => 'accounting.reports.general-ledger.export'])
<div class="grid" style="margin:16px 0"><div class="card kpi-card"><div class="muted">{{ __('Opening Balance') }}</div><div class="kpi">{{ number_format($openingBalance, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('Period Debit') }}</div><div class="kpi">{{ number_format($periodDebit, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('Period Credit') }}</div><div class="kpi">{{ number_format($periodCredit, 3) }}</div></div><div class="card kpi-card"><div class="muted">{{ __('Closing Balance') }}</div><div class="kpi">{{ number_format($closingBalance, 3) }}</div></div></div>
<div class="table-wrap"><table><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Entry No.') }}</th><th>{{ __('Account') }}</th><th>{{ __('Memo') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th><th>{{ __('Running Balance') }}</th></tr></thead><tbody>@forelse ($rows as $row) @php $line=$row['line']; @endphp<tr><td>{{ $line->journalEntry->entry_date->toDateString() }}</td><td><a href="{{ route('accounting.journals.show', $line->journalEntry) }}">{{ $line->journalEntry->entry_no }}</a></td><td>{{ $line->account->code }} - {{ $line->account->localized_name }}</td><td>{{ app()->getLocale()==='ar' ? $line->journalEntry->memo_ar : $line->journalEntry->memo_en }}</td><td>{{ number_format($line->debit, 3) }}</td><td>{{ number_format($line->credit, 3) }}</td><td>{{ number_format($row['running_balance'], 3) }}</td></tr>@empty<tr><td colspan="7">{{ __('No ledger activity for this period.') }}</td></tr>@endforelse</tbody></table></div>
@endsection

@extends('layouts.app')

@section('content')
<h1>{{ __('New Manual Journal') }}</h1>
<div class="status">{{ __('Manual journals are only for accounting-only transactions and approved corrections.') }}</div>
<form method="post" action="{{ route('accounting.journals.store') }}">
    @csrf
    <div class="form-grid">
        <div><label>{{ __('Date') }}</label><input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}">@error('entry_date')<div class="error">{{ $message }}</div>@enderror</div>
        <div><label>{{ __('English Memo') }}</label><input name="memo_en" value="{{ old('memo_en') }}"></div>
        <div><label>{{ __('Arabic Memo') }}</label><input dir="rtl" name="memo_ar" value="{{ old('memo_ar') }}"></div>
    </div>
    @error('lines')<div class="error">{{ $message }}</div>@enderror
    <div class="table-wrap" style="margin-top:14px">
    <table>
        <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Description') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th></tr></thead>
        <tbody id="journal-lines">
        @for ($i = 0; $i < 6; $i++)
            <tr>
                <td><select class="searchable-select" name="lines[{{ $i }}][account_id]"><option value="">{{ __('Select') }}</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected(old("lines.$i.account_id") == $account->id)>{{ $account->code }} - {{ $account->localized_name }}</option>@endforeach</select></td>
                <td><input name="lines[{{ $i }}][description_en]" value="{{ old("lines.$i.description_en") }}"></td>
                <td><input type="number" step="0.001" min="0" name="lines[{{ $i }}][debit]" value="{{ old("lines.$i.debit") }}"></td>
                <td><input type="number" step="0.001" min="0" name="lines[{{ $i }}][credit]" value="{{ old("lines.$i.credit") }}"></td>
            </tr>
        @endfor
        </tbody>
    </table>
    </div>
    <p><button>{{ __('Post Journal') }}</button></p>
</form>
@endsection

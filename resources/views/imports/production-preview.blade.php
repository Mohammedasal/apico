@extends('layouts.app')

@section('content')
@if ($backupError ?? null)
    <div class="error" style="margin-bottom:16px">{{ $backupError }}</div>
@endif
<div class="section-title"><div><h1>{{ __('Production Import Preview') }}</h1><div class="muted">{{ __('No production or expense records have been changed yet.') }}</div></div></div>
<div class="grid">
    <div class="card kpi-card"><div class="muted">{{ __('Year') }}</div><div class="kpi">{{ $result['year'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Production Days') }}</div><div class="kpi">{{ $result['production_days'] }}</div><div class="muted">{{ number_format($result['production_kg'], 3) }} kg</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Monthly Expense Rows') }}</div><div class="kpi">{{ $result['monthly_expenses'] }}</div><div class="muted">{{ number_format($result['total_expenses'], 3) }} JOD</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Existing Production Days Replaced') }}</div><div class="kpi">{{ $result['existing_production_days'] }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Existing Expense Months Replaced') }}</div><div class="kpi">{{ $result['existing_monthly_expenses'] }}</div></div>
</div>
<div class="card" style="margin-top:16px">
    <strong>{{ __('Confirmation Required') }}</strong>
    <p>{{ __('Confirming will create and verify a database backup before replacing production and monthly expense rows for this year.') }}</p>
    <form method="post" action="{{ route('imports.production-sheet.commit') }}" onsubmit="return confirm(@json(__('Replace this year production and expense rows with the previewed workbook?')))" style="display:inline-block">
        @csrf
        <input type="hidden" name="confirm" value="1">
        <button type="submit">{{ __('Backup & Import Production') }}</button>
    </form>
    <a class="button" href="{{ route('production.index', ['year' => $result['year']]) }}">{{ __('Cancel') }}</a>
</div>
@endsection

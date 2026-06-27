@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div>
        <h1>{{ __('Production / P&L') }}</h1>
        <div class="muted">{{ $start->format('F Y') }} {{ __('monthly production, workbook income rate, actual transaction income, costs, and profit/loss.') }}</div>
    </div>
    <form class="filters" method="get">
        <div><label>{{ __('Year') }}</label><input type="number" name="year" value="{{ $year }}"></div>
        <div><label>{{ __('Month') }}</label><input type="number" min="1" max="12" name="month" value="{{ $month }}"></div>
        <div><button>{{ __('Open') }}</button></div>
    </form>
</div>

@if (auth()->user()?->canManageSystem())
<div class="section-title"><h2>{{ __('Production Sheet Upload') }}</h2></div>
<form class="filters" method="post" action="{{ route('imports.production-sheet.store') }}" enctype="multipart/form-data">
    @csrf
    <div><label>{{ __('Excel File') }}</label><input type="file" name="production_sheet" accept=".xlsx" required>@error('production_sheet')<div class="error">{{ $message }}</div>@enderror</div>
    <div><button>{{ __('Import Production & Expenses') }}</button></div>
</form>
@endif

@if (session('production_import_result'))
    @php $productionImport = session('production_import_result'); @endphp
    <div class="section-title">
        <h2>{{ __('Last Production Import') }}</h2>
    </div>
    <div class="grid">
        <div class="card kpi-card"><div class="muted">{{ __('Year') }}</div><div class="kpi">{{ $productionImport['year'] }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Production Days') }}</div><div class="kpi">{{ $productionImport['production_days'] }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Monthly Expense Rows') }}</div><div class="kpi">{{ $productionImport['monthly_expenses'] }}</div></div>
    </div>
@endif

<div class="section-title"><h2>{{ __('Completed Months Actual P&L') }}</h2></div>
<div class="grid">
    <div class="card kpi-card">
        <div class="muted">{{ $completedMonthsSummary['from']->toDateString() }} {{ __('to') }} {{ $completedMonthsSummary['to']->toDateString() }}</div>
        <div @class(['kpi', 'amount-positive' => $completedMonthsSummary['actual_profit_loss'] >= 0, 'amount-negative' => $completedMonthsSummary['actual_profit_loss'] < 0])>{{ number_format($completedMonthsSummary['actual_profit_loss'], 3) }}</div>
        <div class="muted">{{ __('Actual income') }} {{ number_format($completedMonthsSummary['actual_income'], 3) }} | {{ __('Expenses') }} {{ number_format($completedMonthsSummary['expenses'], 3) }}</div>
        <div class="muted">{{ __('Stock COGS') }} {{ number_format($completedMonthsSummary['stock_material_cogs'] ?? 0, 3) }} | {{ number_format($completedMonthsSummary['production_tons'], 3) }} {{ __('tons') }}</div>
    </div>
</div>

<div class="grid">
    <div class="card kpi-card"><div class="muted">{{ __('Production Tons') }}</div><div class="kpi">{{ number_format($productionTons, 3) }}</div><div class="muted">{{ number_format($productionKg, 3) }} {{ __('kg') }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Avg Daily Production') }}</div><div class="kpi">{{ number_format($averageDailyProduction, 3) }}</div><div class="muted">{{ $productionDays }} {{ __('active days') }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Production Income JOD') }}</div><div class="kpi">{{ number_format($productionIncome, 3) }}</div><div class="muted">{{ number_format($productionTons, 3) }} {{ __('tons') }} x {{ number_format($averageIncomePerTon, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Actual Transaction Income JOD') }}</div><div class="kpi">{{ number_format($income, 3) }}</div><div class="muted">{{ __('Recycle') }} {{ number_format($recycleIncome, 3) }} + {{ __('Sales') }} {{ number_format($stockIncome, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Total Expenses JOD') }}</div><div class="kpi">{{ number_format($totalExpenses, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Cost / Ton JOD') }}</div><div class="kpi">{{ number_format($costPerTon, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Daily Cost JOD') }}</div><div class="kpi">{{ number_format($dailyCost, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Production P&L JOD') }}</div><div @class(['kpi', 'amount-positive' => $profitLoss >= 0, 'amount-negative' => $profitLoss < 0])>{{ number_format($profitLoss, 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Actual P&L JOD') }}</div><div @class(['kpi', 'amount-positive' => $actualProfitLoss >= 0, 'amount-negative' => $actualProfitLoss < 0])>{{ number_format($actualProfitLoss, 3) }}</div><div class="muted">{{ __('After stock material COGS') }} {{ number_format($actualStockMaterialCogs, 3) }}</div></div>
</div>

<div class="section-title"><h2>{{ __('Monthly Expenses') }}</h2></div>
@if (auth()->user()?->canManageSystem())
<form method="post" action="{{ route('production.expenses.store') }}">
    @csrf
    <input type="hidden" name="year" value="{{ $year }}">
    <input type="hidden" name="month" value="{{ $month }}">
    <div class="form-grid">
        <div><label>{{ __('Average Income / Ton') }}</label><input type="number" step="0.001" name="average_income_per_ton" value="{{ old('average_income_per_ton', $expense->average_income_per_ton ?? 140) }}"></div>
        <div><label>{{ __('Electricity Bill') }}</label><input type="number" step="0.001" name="electricity_bill" value="{{ old('electricity_bill', $expense->electricity_bill ?? 0) }}"></div>
        <div><label>{{ __('Total Salaries') }}</label><input type="number" step="0.001" name="total_salaries" value="{{ old('total_salaries', $expense->total_salaries ?? 0) }}"></div>
        <div><label>{{ __('Rent') }}</label><input type="number" step="0.001" name="rent" value="{{ old('rent', $expense->rent ?? 0) }}"></div>
        <div><label>{{ __('Misc') }}</label><input type="number" step="0.001" name="misc" value="{{ old('misc', $expense->misc ?? 0) }}"></div>
        <div><label>{{ __('Social Security') }}</label><input type="number" step="0.001" name="social_security" value="{{ old('social_security', $expense->social_security ?? 0) }}"></div>
        <div><label>{{ __('Other Expenses') }}</label><input type="number" step="0.001" name="other_expenses" value="{{ old('other_expenses', $expense->other_expenses ?? 0) }}"></div>
        <div style="grid-column:1/-1"><label>{{ __('Notes') }}</label><textarea name="notes">{{ old('notes', $expense->notes) }}</textarea></div>
    </div>
    <p><button>{{ __('Save Expenses') }}</button></p>
</form>
@else
<div class="grid">
    <div class="card"><div class="muted">{{ __('Average Income / Ton') }}</div><div class="kpi">{{ number_format($averageIncomePerTon, 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Electricity') }}</div><div class="kpi">{{ number_format($expense->electricity_bill ?? 0, 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Salaries') }}</div><div class="kpi">{{ number_format($expense->total_salaries ?? 0, 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Rent') }}</div><div class="kpi">{{ number_format($expense->rent ?? 0, 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Misc') }}</div><div class="kpi">{{ number_format($expense->misc ?? 0, 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Social Security') }}</div><div class="kpi">{{ number_format($expense->social_security ?? 0, 3) }}</div></div>
    <div class="card"><div class="muted">{{ __('Other') }}</div><div class="kpi">{{ number_format($expense->other_expenses ?? 0, 3) }}</div></div>
</div>
@endif

<div class="section-title"><h2>{{ __('Daily Production') }}</h2></div>
@if (auth()->user()?->canManageSystem())
<form method="post" action="{{ route('production.days.store') }}">
    @csrf
    <input type="hidden" name="year" value="{{ $year }}">
    <input type="hidden" name="month" value="{{ $month }}">
    <div class="table-wrap">
        <table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Shift 1 Kg') }}</th><th>{{ __('Shift 2 Kg') }}</th><th>{{ __('Total Kg') }}</th><th>{{ __('Notes') }}</th></tr></thead>
            <tbody>
            @foreach ($days as $index => $day)
                @php $entry = $entries[$day->toDateString()] ?? null; @endphp
                <tr>
                    <td>{{ $day->toDateString() }}<input type="hidden" name="days[{{ $index }}][date]" value="{{ $day->toDateString() }}"></td>
                    <td><input type="number" step="0.001" name="days[{{ $index }}][shift_one_kg]" value="{{ old("days.$index.shift_one_kg", $entry?->shift_one_kg ?? 0) }}"></td>
                    <td><input type="number" step="0.001" name="days[{{ $index }}][shift_two_kg]" value="{{ old("days.$index.shift_two_kg", $entry?->shift_two_kg ?? 0) }}"></td>
                    <td>{{ number_format($entry?->total_kg ?? 0, 3) }}</td>
                    <td><input name="days[{{ $index }}][notes]" value="{{ old("days.$index.notes", $entry?->notes) }}"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p><button>{{ __('Save Daily Production') }}</button></p>
</form>
@else
<div class="table-wrap">
    <table>
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Shift 1 Kg') }}</th><th>{{ __('Shift 2 Kg') }}</th><th>{{ __('Total Kg') }}</th><th>{{ __('Notes') }}</th></tr></thead>
        <tbody>
        @foreach ($days as $day)
            @php $entry = $entries[$day->toDateString()] ?? null; @endphp
            <tr>
                <td>{{ $day->toDateString() }}</td>
                <td>{{ number_format($entry?->shift_one_kg ?? 0, 3) }}</td>
                <td>{{ number_format($entry?->shift_two_kg ?? 0, 3) }}</td>
                <td>{{ number_format($entry?->total_kg ?? 0, 3) }}</td>
                <td>{{ $entry?->notes }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection

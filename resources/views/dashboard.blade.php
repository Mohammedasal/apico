@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div>
        <h1>{{ __('Dashboard') }}</h1>
        <div class="muted">{{ $from || $to ? __('Factory totals for the selected date range.') : __('Factory totals for all imported dates.') }}</div>
    </div>
    <form class="filters" method="get">
        <div><label>{{ __('From') }}</label><input type="date" name="from" value="{{ $from }}"></div>
        <div><label>{{ __('To') }}</label><input type="date" name="to" value="{{ $to }}"></div>
        <div><button>{{ __('Apply') }}</button></div>
    </form>
</div>

<div class="section-title">
    <h2>{{ __('Quick Add') }}</h2>
</div>
@if (auth()->user()?->canWriteOperationalData())
<div class="quick-actions">
    <a class="quick-action" href="{{ route('operations.create', 'recycle-in') }}">
        <span class="action-mark mark-in">IN</span>
        <span><strong>{{ __('Recycle In') }}</strong><span>{{ __('Customer material received') }}</span></span>
    </a>
    <a class="quick-action" href="{{ route('operations.create', 'recycle-out') }}">
        <span class="action-mark mark-out">OUT</span>
        <span><strong>{{ __('Recycle Out') }}</strong><span>{{ __('Recycled, waste, non-recycled') }}</span></span>
    </a>
    <a class="quick-action" href="{{ route('operations.create', 'payments') }}">
        <span class="action-mark mark-pay">PAY</span>
        <span><strong>{{ __('Payment') }}</strong><span>{{ __('Customer receivable payment') }}</span></span>
    </a>
    <a class="quick-action" href="{{ route('operations.create', 'stock-sales') }}">
        <span class="action-mark mark-sale">SALE</span>
        <span><strong>{{ __('Stock Sale') }}</strong><span>{{ __('Sell stock material') }}</span></span>
    </a>
    <a class="quick-action" href="{{ route('operations.create', 'stock-purchases') }}">
        <span class="action-mark mark-buy">BUY</span>
        <span><strong>{{ __('Purchase') }}</strong><span>{{ __('Buy stock material') }}</span></span>
    </a>
    <a class="quick-action" href="{{ route('supplier-payments.create') }}">
        <span class="action-mark mark-pay">SUP</span>
        <span><strong>{{ __('Supplier Payment') }}</strong><span>{{ __('Pay stock suppliers') }}</span></span>
    </a>
    @if (auth()->user()?->canViewFinancialReports())
        <a class="quick-action" href="{{ route('production.index') }}">
            <span class="action-mark mark-out">P&L</span>
            <span><strong>{{ __('Production') }}</strong><span>{{ __('Daily production and costs') }}</span></span>
        </a>
    @endif
</div>
@endif

@if (auth()->user()?->canManageSystem())
<div class="section-title">
    <h2>{{ __('Sales Sheet Upload') }}</h2>
</div>
<form class="filters" method="post" action="{{ route('imports.sales-sheet.store') }}" enctype="multipart/form-data" onsubmit="return confirm(@json(__('This will flush existing imported transactions and upload this workbook. Continue?')))">
    @csrf
    <div><label>{{ __('Excel File') }}</label><input type="file" name="sales_sheet" accept=".xlsx" required>@error('sales_sheet')<div class="error">{{ $message }}</div>@enderror</div>
    <div><button>{{ __('Flush & Import') }}</button></div>
</form>
@endif

@if (session('import_result'))
    @php $import = session('import_result'); @endphp
    <div class="section-title">
        <h2>{{ __('Last Import') }}</h2>
    </div>
    <div class="grid">
        <div class="card kpi-card"><div class="muted">{{ __('Customers') }}</div><div class="kpi">{{ $import['totals']['customers'] }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Purchases Rows') }}</div><div class="kpi">{{ $import['purchases']['rows'] }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Recycle In Rows') }}</div><div class="kpi">{{ $import['totals']['recycle_in_rows'] }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Recycle Out Rows') }}</div><div class="kpi">{{ $import['totals']['recycle_out_rows'] }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Payment Rows') }}</div><div class="kpi">{{ $import['totals']['payment_rows'] }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Stock Sale Rows') }}</div><div class="kpi">{{ $import['totals']['stock_sale_rows'] }}</div></div>
    </div>
@endif

@if (session('date_issues'))
    <div class="section-title">
        <h2>{{ __('2025 / Missing Date Transactions') }}</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Type') }}</th><th>{{ __('Date') }}</th><th>{{ __('Transaction') }}</th></tr></thead>
            <tbody>
            @forelse (session('date_issues') as $issue)
                <tr><td>{{ $issue['customer'] }}</td><td>{{ $issue['type'] }}</td><td>{{ $issue['date'] }}</td><td>{{ $issue['transaction'] }}</td></tr>
            @empty
                <tr><td colspan="4">{{ __('No 2025 or missing-date transactions found.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endif

<div class="section-title">
    <h2>{{ __('Factory Snapshot') }}</h2>
</div>
<div class="grid">
    <div class="card kpi-card"><div class="muted">{{ __('Customers') }}</div><div class="kpi">{{ $customerCount }}</div><a href="{{ route('customers.index') }}">{{ __('Open list') }}</a></div>
    <div class="card kpi-card"><div class="muted">{{ __('Recycle In Kg') }}</div><div class="kpi">{{ number_format($recycleInKg, 3) }}</div><a href="{{ route('operations.index', 'recycle-in') }}">{{ __('View in') }}</a></div>
    <div class="card kpi-card"><div class="muted">{{ __('Recycle Out Kg') }}</div><div class="kpi">{{ number_format($recycleOutKg, 3) }}</div><a href="{{ route('operations.index', 'recycle-out') }}">{{ __('View out') }}</a></div>
    <div class="card kpi-card"><div class="muted">{{ __('Daily Production Avg') }}</div><div class="kpi">{{ number_format($dailyProductionAverage, 3) }}</div><div class="muted">{{ $productionDays }} {{ __('production days') }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Waste Kg') }}</div><div class="kpi">{{ number_format($wasteKg, 3) }}</div>@if (auth()->user()?->canViewFinancialReports())<a href="{{ route('reports.monthly') }}">{{ __('Monthly report') }}</a>@endif</div>
    <div class="card kpi-card"><div class="muted">{{ __('Waste % of Out') }}</div><div class="kpi">{{ number_format($wastePercentage, 2) }}%</div><div class="muted">{{ __('Waste / total out') }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Payments JOD') }}</div><div class="kpi">{{ number_format($payments, 3) }}</div><a href="{{ route('operations.index', 'payments') }}">{{ __('View payments') }}</a></div>
    <div class="card kpi-card"><div class="muted">{{ __('Debts / Receivables JOD') }}</div><div class="kpi">{{ number_format($receivables, 3) }}</div><div class="muted">{{ __('Remaining customer balances') }}</div></div>
    @if (auth()->user()?->canViewFinancialReports())
        <div class="card kpi-card">
            <div class="muted">{{ __('Actual Cost / Ton JOD') }}</div>
            <div class="kpi">{{ number_format($actualCostPerTon, 3) }}</div>
            <div class="muted">{{ $actualCostPerTonLabel }} {{ __('completed month') }}</div>
        </div>
        <div class="card kpi-card">
            <div class="muted">{{ __('Actual Stock Profit JOD') }}</div>
            <div class="kpi">{{ number_format($stockProfit, 3) }}</div>
            <div class="muted">{{ __('Revenue') }} {{ number_format($stockRevenue, 3) }} - {{ __('Material COGS') }} {{ number_format($stockMaterialCogs, 3) }} - {{ __('Conversion') }} {{ number_format($stockRecycleCost, 3) }}</div>
            <a href="{{ route('reports.stock-profit') }}">{{ __('Profit report') }}</a>
        </div>
        <div class="card kpi-card">
            <div class="muted">{{ __('Actual Factory P&L JOD') }}</div>
            <div @class(['kpi', 'amount-positive' => $actualFactoryProfitLoss >= 0, 'amount-negative' => $actualFactoryProfitLoss < 0])>{{ number_format($actualFactoryProfitLoss, 3) }}</div>
            <div class="muted">{{ __('Income - material COGS - expenses') }} {{ number_format($actualOperatingExpenses, 3) }}</div>
        </div>
    @endif
    <div class="card kpi-card"><div class="muted">{{ __('Remaining Stock Kg') }}</div><div class="kpi">{{ number_format($remainingStock, 3) }}</div><a href="{{ route('operations.index', 'stock-sales') }}">{{ __('Stock sales') }}</a></div>
</div>

<div class="section-title">
    <h2>{{ __('Totals Chart') }}</h2>
</div>
<div class="card">
    <canvas id="summaryChart" height="90"></canvas>
</div>
@php
    $chartLabels = auth()->user()?->canViewFinancialReports()
        ? [__('Recycle In Kg'), __('Recycle Out Kg'), __('Production Kg'), __('Waste Kg'), __('Payments'), __('Stock Profit'), __('Actual P&L')]
        : [__('Recycle In Kg'), __('Recycle Out Kg'), __('Production Kg'), __('Waste Kg'), __('Payments')];
    $chartData = auth()->user()?->canViewFinancialReports()
        ? [$recycleInKg, $recycleOutKg, $productionKg, $wasteKg, $payments, $stockProfit, $actualFactoryProfitLoss]
        : [$recycleInKg, $recycleOutKg, $productionKg, $wasteKg, $payments];
@endphp
<script>
new Chart(document.getElementById('summaryChart'), {
    type: 'bar',
    data: {
        labels: @json($chartLabels),
        datasets: [{ data: @json($chartData), backgroundColor: ['#166534', '#176b55', '#0f766e', '#b91c1c', '#b45309', '#2563eb', '#7c3aed'], borderRadius: 4 }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { x: { grid: { display: false } }, y: { beginAtZero: true } }
    }
});
</script>
@endsection

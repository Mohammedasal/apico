@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div>
        <h1>{{ __('Stock Sales Dashboard') }}</h1>
        <div class="muted">{{ $period['label'] }}</div>
    </div>
    <a class="button" href="{{ route('operations.index', 'stock-sales') }}">{{ __('View Transactions') }}</a>
</div>

@include('reports.partials.period-filter', ['routeName' => 'dashboards.stock-sales'])

<div class="grid dashboard-kpis">
    <div class="card kpi-card"><div class="muted">{{ __('Sold Kg') }}</div><div class="kpi">{{ number_format($summary['sold_kg'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Revenue JOD') }}</div><div class="kpi">{{ number_format($summary['revenue'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Average Rate/Kg') }}</div><div class="kpi">{{ number_format($summary['average_rate'], 6) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Transaction Count') }}</div><div class="kpi">{{ number_format($summary['transactions']) }}</div></div>
    @if (auth()->user()?->canViewProfitAndLoss())
        <div class="card kpi-card"><div class="muted">{{ __('Material COGS JOD') }}</div><div class="kpi amount-negative">{{ number_format($summary['material_cogs'], 3) }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Conversion Cost JOD') }}</div><div class="kpi amount-negative">{{ number_format($summary['recycle_cost'], 3) }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Actual Stock Profit JOD') }}</div><div @class(['kpi', 'amount-positive' => $summary['profit'] >= 0, 'amount-negative' => $summary['profit'] < 0])>{{ number_format($summary['profit'], 3) }}</div></div>
        <div class="card kpi-card"><div class="muted">{{ __('Margin') }}</div><div @class(['kpi', 'amount-positive' => $summary['margin_percentage'] >= 0, 'amount-negative' => $summary['margin_percentage'] < 0])>{{ number_format($summary['margin_percentage'], 2) }}%</div></div>
    @endif
</div>

<div class="section-title"><h2>{{ __('Performance Trend') }}</h2></div>
@if ($trend->isEmpty())
    <div class="status muted">{{ __('No data for this period.') }}</div>
@else
    <div class="card chart-panel"><canvas id="stockSalesTrend"></canvas></div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Sold Kg') }}</th><th>{{ __('Revenue JOD') }}</th>@if (auth()->user()?->canViewProfitAndLoss())<th>{{ __('Profit JOD') }}</th>@endif<th>{{ __('Transactions') }}</th></tr></thead>
            <tbody>
            @foreach ($trend as $row)
                <tr><td>{{ $row['label'] }}</td><td>{{ number_format($row['weight_kg'], 3) }}</td><td>{{ number_format($row['revenue'], 3) }}</td>@if (auth()->user()?->canViewProfitAndLoss())<td @class(['amount-positive' => $row['profit'] >= 0, 'amount-negative' => $row['profit'] < 0])>{{ number_format($row['profit'], 3) }}</td>@endif<td>{{ $row['transactions'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="dashboard-columns">
    <section>
        <div class="section-title"><h2>{{ __('Top Customers') }}</h2></div>
        <div class="table-wrap"><table><thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Sold Kg') }}</th><th>{{ __('Revenue JOD') }}</th></tr></thead><tbody>
        @forelse ($topCustomers as $row)<tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['weight_kg'], 3) }}</td><td>{{ number_format($row['amount'], 3) }}</td></tr>@empty<tr><td colspan="3">{{ __('No data for this period.') }}</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section>
        <div class="section-title"><h2>{{ __('Top Materials') }}</h2></div>
        <div class="table-wrap"><table><thead><tr><th>{{ __('Material') }}</th><th>{{ __('Sold Kg') }}</th><th>{{ __('Revenue JOD') }}</th></tr></thead><tbody>
        @forelse ($topMaterials as $row)<tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['weight_kg'], 3) }}</td><td>{{ number_format($row['amount'], 3) }}</td></tr>@empty<tr><td colspan="3">{{ __('No data for this period.') }}</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>

@if ($trend->isNotEmpty())
<script>
const stockTrend = @json($trend);
new Chart(document.getElementById('stockSalesTrend'), {
    data: {
        labels: stockTrend.map((row) => row.label),
        datasets: [
            { type: 'bar', label: @json(__('Sold Kg')), data: stockTrend.map((row) => row.weight_kg), backgroundColor: '#176b55', borderRadius: 4, yAxisID: 'weight' },
            { type: 'line', label: @json(__('Revenue JOD')), data: stockTrend.map((row) => row.revenue), borderColor: '#2563eb', backgroundColor: '#2563eb', borderWidth: 3, tension: .25, yAxisID: 'amount' },
            @if (auth()->user()?->canViewProfitAndLoss())
            { type: 'line', label: @json(__('Profit JOD')), data: stockTrend.map((row) => row.profit), borderColor: '#166534', backgroundColor: '#166534', borderWidth: 2, tension: .25, yAxisID: 'amount' },
            @endif
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { weight: { beginAtZero: true, position: 'left' }, amount: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } } } }
});
</script>
@endif
@endsection

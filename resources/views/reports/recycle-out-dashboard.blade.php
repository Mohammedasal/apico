@extends('layouts.app')

@section('content')
<div class="toolbar">
    <div>
        <h1>{{ __('Recycle Out Dashboard') }}</h1>
        <div class="muted">{{ $period['label'] }}</div>
    </div>
    <a class="button" href="{{ route('operations.index', 'recycle-out') }}">{{ __('View Transactions') }}</a>
</div>

@include('reports.partials.period-filter', ['routeName' => 'dashboards.recycle-out'])

<div class="grid dashboard-kpis">
    <div class="card kpi-card"><div class="muted">{{ __('Total Out Kg') }}</div><div class="kpi">{{ number_format($summary['total_kg'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Recycled Kg') }}</div><div class="kpi amount-positive">{{ number_format($summary['recycled_kg'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Waste Kg') }}</div><div class="kpi amount-negative">{{ number_format($summary['waste_kg'], 3) }}</div><div class="muted">{{ number_format($summary['waste_percentage'], 2) }}%</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Non-Recycled Kg') }}</div><div class="kpi">{{ number_format($summary['non_recycled_kg'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Revenue JOD') }}</div><div class="kpi">{{ number_format($summary['revenue'], 3) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Average Rate/Kg') }}</div><div class="kpi">{{ number_format($summary['average_rate'], 6) }}</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Recycling Yield') }}</div><div class="kpi">{{ number_format($summary['yield_percentage'], 2) }}%</div></div>
    <div class="card kpi-card"><div class="muted">{{ __('Transaction Count') }}</div><div class="kpi">{{ number_format($summary['transactions']) }}</div></div>
</div>

<div class="section-title"><h2>{{ __('Performance Trend') }}</h2></div>
@if ($trend->isEmpty())
    <div class="status muted">{{ __('No data for this period.') }}</div>
@else
    <div class="card chart-panel"><canvas id="recycleOutTrend"></canvas></div>
    <div class="table-wrap" style="margin-top:12px">
        <table>
            <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Total Out Kg') }}</th><th>{{ __('Recycled Kg') }}</th><th>{{ __('Waste Kg') }}</th><th>{{ __('Non-Recycled Kg') }}</th><th>{{ __('Waste %') }}</th><th>{{ __('Revenue JOD') }}</th></tr></thead>
            <tbody>
            @foreach ($trend as $row)
                <tr><td>{{ $row['label'] }}</td><td>{{ number_format($row['total_kg'], 3) }}</td><td>{{ number_format($row['recycled_kg'], 3) }}</td><td>{{ number_format($row['waste_kg'], 3) }}</td><td>{{ number_format($row['non_recycled_kg'], 3) }}</td><td>{{ number_format($row['waste_percentage'], 2) }}%</td><td>{{ number_format($row['revenue'], 3) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="dashboard-columns">
    <section>
        <div class="section-title"><h2>{{ __('Top Customers') }}</h2></div>
        <div class="table-wrap"><table><thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Total Out Kg') }}</th><th>{{ __('Recycled Kg') }}</th><th>{{ __('Waste Kg') }}</th></tr></thead><tbody>
        @forelse ($topCustomers as $row)<tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['total_kg'], 3) }}</td><td>{{ number_format($row['recycled_kg'], 3) }}</td><td>{{ number_format($row['waste_kg'], 3) }}</td></tr>@empty<tr><td colspan="4">{{ __('No data for this period.') }}</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section>
        <div class="section-title"><h2>{{ __('Top Materials') }}</h2></div>
        <div class="table-wrap"><table><thead><tr><th>{{ __('Material') }}</th><th>{{ __('Total Out Kg') }}</th><th>{{ __('Recycled Kg') }}</th><th>{{ __('Waste Kg') }}</th></tr></thead><tbody>
        @forelse ($topMaterials as $row)<tr><td>{{ $row['name'] }}</td><td>{{ number_format($row['total_kg'], 3) }}</td><td>{{ number_format($row['recycled_kg'], 3) }}</td><td>{{ number_format($row['waste_kg'], 3) }}</td></tr>@empty<tr><td colspan="4">{{ __('No data for this period.') }}</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>

@if ($trend->isNotEmpty())
<script>
const recycleTrend = @json($trend);
new Chart(document.getElementById('recycleOutTrend'), {
    data: {
        labels: recycleTrend.map((row) => row.label),
        datasets: [
            { type: 'bar', label: @json(__('Recycled Kg')), data: recycleTrend.map((row) => row.recycled_kg), backgroundColor: '#166534', stack: 'output', yAxisID: 'weight' },
            { type: 'bar', label: @json(__('Waste Kg')), data: recycleTrend.map((row) => row.waste_kg), backgroundColor: '#b91c1c', stack: 'output', yAxisID: 'weight' },
            { type: 'bar', label: @json(__('Non-Recycled Kg')), data: recycleTrend.map((row) => row.non_recycled_kg), backgroundColor: '#b45309', stack: 'output', yAxisID: 'weight' },
            { type: 'line', label: @json(__('Revenue JOD')), data: recycleTrend.map((row) => row.revenue), borderColor: '#2563eb', backgroundColor: '#2563eb', borderWidth: 3, tension: .25, yAxisID: 'amount' }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { weight: { beginAtZero: true, stacked: true, position: 'left' }, amount: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }, x: { stacked: true } } }
});
</script>
@endif
@endsection

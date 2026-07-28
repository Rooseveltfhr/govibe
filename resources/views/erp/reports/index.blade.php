@extends('erp.layouts.app')
@section('title','Rapports')
@section('page-title','Rapports & Analytics')
@section('page-subtitle','Tableau de bord analytique {{ $currentYear }}')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([['Revenus '.$currentYear,'HTG '.number_format($stats['totalRevenue'],0,'.',','),'bi-cash-coin','#059669','#d1fae5'],['Clients',$stats['totalClients'],'bi-people-fill','#1e3a5f','#dbeafe'],['Projets',$stats['totalProjects'],'bi-kanban-fill','#7c3aed','#ede9fe'],['Factures en attente',$stats['pendingInvoices'],'bi-clock-fill','#d97706','#fef3c7']] as [$l,$v,$i,$c,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div><p class="text-gray-400 text-xs mb-1">{{ $l }}</p><p class="text-xl font-extrabold text-gray-800 dark:text-white">{{ $v }}</p></div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}"><i class="bi {{ $i }}" style="color:{{ $c }}"></i></div>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Revenue chart --}}
    <div class="content-card p-5">
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="bi bi-bar-chart-line text-blue-500"></i> Revenus mensuels {{ $currentYear }}
        </h3>
        <canvas id="revenueChart" height="200"></canvas>
    </div>

    {{-- Summary --}}
    <div class="content-card p-5">
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="bi bi-graph-up-arrow text-green-500"></i> Résumé annuel
        </h3>
        <div class="space-y-3">
            @foreach($monthlyRevenue as $m)
            @if($m['amount'] > 0)
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500 w-8">{{ $m['month'] }}</span>
                <div class="flex-1 bg-gray-100 dark:bg-slate-700 rounded-full h-2">
                    @php $max = max(array_column($monthlyRevenue,'amount')) ?: 1; @endphp
                    <div class="h-2 rounded-full" style="width:{{ min(100,($m['amount']/$max)*100) }}%;background:#d4a017"></div>
                </div>
                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 w-28 text-right">HTG {{ number_format($m['amount'],0,'.',',') }}</span>
            </div>
            @endif
            @endforeach
            @if(collect($monthlyRevenue)->sum('amount') == 0)
            <div class="py-8 text-center text-gray-400">
                <i class="bi bi-graph-up text-3xl block mb-2 opacity-30"></i>
                <p class="text-sm">Aucune donnée de revenus pour {{ $currentYear }}.</p>
            </div>
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('revenueChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode(array_column($monthlyRevenue, 'month')) !!},
            datasets: [{
                label: 'Revenus HTG',
                data: {!! json_encode(array_column($monthlyRevenue, 'amount')) !!},
                backgroundColor: '#d4a017',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { color: '#9ca3af' }, grid: { color: '#f3f4f6' } },
                x: { ticks: { color: '#9ca3af' }, grid: { display: false } }
            }
        }
    });
}
</script>
@endsection

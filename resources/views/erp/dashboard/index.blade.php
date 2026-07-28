@extends('erp.layouts.app')

@section('title','Dashboard')
@section('page-title','Tableau de bord')
@section('page-subtitle','Vue globale de GOVIBE Innovation Hub')

@section('content')
{{-- KPI Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @php
    $kpis = [
        ['Clients actifs',     $stats['total_clients']     ?? 0, 'bi-people-fill',     '#1e3a5f', '#dbeafe', '+12% ce mois'],
        ['Projets en cours',   $stats['active_projects']   ?? 0, 'bi-kanban-fill',     '#7c3aed', '#ede9fe', '3 en retard'],
        ['Revenus du mois',    'HTG '.number_format($stats['monthly_revenue'] ?? 0), 'bi-cash-stack', '#059669', '#d1fae5', '+8% vs mois dernier'],
        ['Employés actifs',    $stats['total_employees']   ?? 0, 'bi-person-badge-fill','#d97706', '#fef3c7', '2 en congé'],
    ];
    @endphp

    @foreach($kpis as [$label, $value, $icon, $color, $bg, $sub])
    <div class="stat-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs font-medium mb-1">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $value }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $sub }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" style="background:{{ $bg }}">
                <i class="bi {{ $icon }} text-lg" style="color:{{ $color }}"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
    {{-- Revenue chart placeholder --}}
    <div class="lg:col-span-2 content-card">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-semibold text-gray-800 dark:text-white text-sm">Revenus — 6 derniers mois</h3>
            <select class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 text-gray-500 focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-gray-300">
                <option>2026</option><option>2025</option>
            </select>
        </div>
        <div class="p-5" x-data="revenueChart()" x-init="init()">
            <canvas id="revenueChart" height="200"></canvas>
        </div>
    </div>

    {{-- Business Units --}}
    <div class="content-card">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-semibold text-gray-800 dark:text-white text-sm">Unités Business</h3>
        </div>
        <div class="p-4 space-y-2">
            @php
            $units = [
                ['GOVIBE Coworking','#1e3a5f', 65, 'bi-building'],
                ['GOVIBE Academy','#7c3aed', 45, 'bi-mortarboard-fill'],
                ['Digital Services','#059669', 80, 'bi-globe2'],
                ['AI Lab','#d97706', 30, 'bi-cpu-fill'],
                ['Media','#dc2626', 55, 'bi-camera-video-fill'],
                ['Events','#0891b2', 20, 'bi-calendar-event-fill'],
            ];
            @endphp
            @foreach($units as [$name, $color, $pct, $icon])
            <div class="flex items-center gap-3 py-1.5">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0" style="background:{{ $color }}1a">
                    <i class="bi {{ $icon }} text-xs" style="color:{{ $color }}"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-center mb-0.5">
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">{{ $name }}</p>
                        <span class="text-xs text-gray-400 ml-2">{{ $pct }}%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:{{ $pct }}%; background:{{ $color }}"></div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Recent projects --}}
    <div class="lg:col-span-2 content-card">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-semibold text-gray-800 dark:text-white text-sm">Projets récents</h3>
            <a href="{{ route('erp.projects.index') }}" class="text-blue-600 text-xs hover:underline">Voir tout</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-800/50">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Projet</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Progrès</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    @forelse($recentProjects ?? [] as $project)
                    <tr class="table-row">
                        <td class="px-5 py-3">
                            <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $project->name }}</p>
                            <p class="text-xs text-gray-400">{{ $project->reference }}</p>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $project->client->name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            @php $sColors=['planning'=>'bg-gray-100 text-gray-600','active'=>'bg-blue-100 text-blue-700','on_hold'=>'bg-yellow-100 text-yellow-700','completed'=>'bg-green-100 text-green-700','cancelled'=>'bg-red-100 text-red-700']; @endphp
                            <span class="badge {{ $sColors[$project->status] ?? 'bg-gray-100 text-gray-600' }} text-xs">{{ ucfirst(str_replace('_',' ',$project->status)) }}</span>
                        </td>
                        <td class="px-5 py-3 w-28">
                            <div class="flex items-center gap-2">
                                <div class="progress-bar flex-1"><div class="progress-fill" style="width:{{ $project->progress }}%"></div></div>
                                <span class="text-xs text-gray-400">{{ $project->progress }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400 text-sm">
                        <i class="bi bi-kanban text-2xl block mb-2 opacity-30"></i>Aucun projet
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Quick actions + recent activity --}}
    <div class="space-y-5">
        {{-- Quick actions --}}
        <div class="content-card p-5">
            <h3 class="font-semibold text-gray-800 dark:text-white text-sm mb-4">Actions rapides</h3>
            <div class="grid grid-cols-2 gap-2">
                @foreach([
                    ['Nouveau client','bi-person-plus-fill','#1e3a5f', route('erp.crm.clients.create')],
                    ['Nouveau projet','bi-plus-square-fill','#7c3aed', route('erp.projects.create')],
                    ['Nouvelle facture','bi-receipt','#059669', route('erp.invoices.create')],
                    ['Réservation','bi-calendar-plus-fill','#d97706', route('erp.booking.create')],
                    ['Rapport','bi-bar-chart-fill','#0891b2', route('erp.reports.index')],
                    ['Ouvrir POS','bi-shop-window','#dc2626', route('erp.pos.index')],
                ] as [$label, $icon, $color, $href])
                <a href="{{ $href }}" class="flex flex-col items-center gap-1.5 p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors border border-transparent hover:border-gray-200 dark:hover:border-slate-600 text-center">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:{{ $color }}1a">
                        <i class="bi {{ $icon }} text-sm" style="color:{{ $color }}"></i>
                    </div>
                    <span class="text-xs text-gray-600 dark:text-gray-400 font-medium leading-tight">{{ $label }}</span>
                </a>
                @endforeach
            </div>
        </div>

        {{-- Recent clients --}}
        <div class="content-card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-white text-sm">Derniers clients</h3>
                <a href="{{ route('erp.crm.clients.index') }}" class="text-blue-600 text-xs hover:underline">Voir tout</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-slate-700">
                @forelse($recentClients ?? [] as $client)
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="avatar avatar-navy w-8 h-8 text-xs flex-shrink-0">{{ strtoupper(substr($client->name,0,1)) }}</div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ $client->name }}</p>
                        <p class="text-xs text-gray-400">{{ $client->type }}</p>
                    </div>
                    <span class="badge {{ $client->status==='active' ? 'status-active' : 'status-inactive' }} text-xs">{{ $client->status }}</span>
                </div>
                @empty
                <div class="px-4 py-6 text-center text-gray-400 text-sm">
                    <i class="bi bi-people text-xl block mb-1 opacity-30"></i>Aucun client
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
function revenueChart() {
    return {
        init() {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Jan','Fév','Mar','Avr','Mai','Jun'],
                    datasets: [
                        {
                            label: 'Revenus',
                            data: [85000, 120000, 95000, 140000, 110000, 165000],
                            backgroundColor: 'rgba(30,58,95,0.85)',
                            borderRadius: 6,
                            borderSkipped: false,
                        },
                        {
                            label: 'Dépenses',
                            data: [45000, 60000, 55000, 70000, 62000, 80000],
                            backgroundColor: 'rgba(212,160,23,0.75)',
                            borderRadius: 6,
                            borderSkipped: false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
                    scales: {
                        y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, callback: v => 'HTG ' + (v/1000) + 'k' } },
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }
    }
}
</script>
@endpush

@extends('erp.layouts.app')
@section('title','Rapports')
@section('page-title','Rapports & Analytics')
@section('page-subtitle','Vue d\'ensemble des activités GOVIBE {{ $year }}')

@section('content')

{{-- Year filter + PDF global --}}
<div class="flex flex-wrap items-center gap-3 mb-6">
    <form method="GET" class="flex gap-2">
        <select name="year" onchange="this.form.submit()"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500">
            @foreach($availableYears as $y)
            <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('erp.reports.pdf.global', ['year'=>$year]) }}"
       class="btn-gold flex items-center gap-2 text-sm px-4 py-2">
        <i class="fas fa-file-pdf"></i> Rapport global PDF
    </a>
</div>

{{-- KPI cards --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    @foreach([
        ['Revenus facturés','HTG '.number_format($stats['totalRevenue'],0,'.',','),'fa-coins','#059669','#d1fae5'],
        ['Clients',$stats['totalClients'],'fa-users','#1e3a5f','#dbeafe'],
        ['Projets',$stats['totalProjects'],'fa-diagram-project','#7c3aed','#ede9fe'],
        ['Formations '.$year,$stats['totalFormations'],'fa-chalkboard-teacher','#d97706','#fef3c7'],
        ['Bootcamp inscrits',$stats['bootcampTotal'],'fa-robot','#DC2626','#fee2e2'],
    ] as [$l,$v,$i,$c,$bg])
    <div class="content-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ $l }}</p>
                <p class="text-xl font-extrabold text-gray-800">{{ $v }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}20">
                <i class="fas {{ $i }}" style="color:{{ $c }}"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Activity sections --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    {{-- Revenue chart --}}
    <div class="content-card p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-chart-bar text-blue-500"></i> Revenus mensuels {{ $year }}
            </h3>
            <a href="{{ route('erp.reports.pdf.global', ['year'=>$year]) }}" class="text-red-600 text-xs hover:underline">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
        </div>
        <canvas id="revenueChart" height="220"></canvas>
    </div>

    {{-- Activity summary --}}
    <div class="content-card p-5">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-list-check text-green-500"></i> Résumé des activités
        </h3>
        <div class="space-y-3">
            @foreach([
                ['Inscriptions formations',route('erp.reports.formations',['year'=>$year]),$stats['totalInscriptions'],'fa-graduation-cap','#d97706'],
                ['Bootcamp approuvés',route('erp.reports.bootcamp'),$stats['bootcampApproved'],'fa-robot','#DC2626'],
                ['Réservations',route('erp.reports.reservations',['year'=>$year]),$stats['totalBookings'],'fa-calendar-check','#1e3a5f'],
                ['Ventes POS',route('erp.reports.pos',['year'=>$year]),$stats['posRevenue'].' HTG','fa-cash-register','#059669'],
                ['Total clients',route('erp.reports.clients'),$stats['totalClients'],'fa-users','#7c3aed'],
            ] as [$label,$url,$val,$icon,$color])
            <a href="{{ $url }}" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition group">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:{{ $color }}15">
                    <i class="fas {{ $icon }} text-sm" style="color:{{ $color }}"></i>
                </div>
                <span class="flex-1 text-sm text-gray-700 group-hover:text-gray-900">{{ $label }}</span>
                <span class="font-bold text-sm text-gray-800">{{ is_numeric($val) ? number_format((float)$val,0,'.',',') : $val }}</span>
                <i class="fas fa-chevron-right text-gray-300 text-xs group-hover:text-gray-500"></i>
            </a>
            @endforeach
        </div>
    </div>
</div>

{{-- Report cards --}}
<h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Rapports détaillés</h2>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach([
        ['Formations & Inscriptions','Suivi des formations GOVIBE et participants','fa-chalkboard-teacher','#d97706',route('erp.reports.formations',['year'=>$year]),route('erp.reports.pdf.formations',['year'=>$year])],
        ['Bootcamp AI 2026','Registrations, statuts, modules choisis','fa-robot','#DC2626',route('erp.reports.bootcamp'),route('erp.reports.pdf.bootcamp')],
        ['Réservations d\'espaces','Bookings coworking et salles de réunion','fa-calendar-check','#1e3a5f',route('erp.reports.reservations',['year'=>$year]),route('erp.reports.pdf.reservations',['year'=>$year])],
        ['Ventes POS','Transactions point de vente','fa-cash-register','#059669',route('erp.reports.pos',['year'=>$year]),route('erp.reports.pdf.pos',['year'=>$year])],
        ['Rapport Clients CRM','Portefeuille clients et activité','fa-users','#7c3aed',route('erp.reports.clients'),route('erp.reports.pdf.clients',['year'=>$year])],
        ['Rapport global','Synthèse complète de toutes les activités','fa-file-chart-line','#374151',route('erp.reports.index',['year'=>$year]),route('erp.reports.pdf.global',['year'=>$year])],
    ] as [$title,$desc,$icon,$color,$link,$pdf])
    <div class="content-card p-5 flex flex-col gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $color }}15">
                <i class="fas {{ $icon }}" style="color:{{ $color }}"></i>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 text-sm">{{ $title }}</h4>
                <p class="text-xs text-gray-400">{{ $desc }}</p>
            </div>
        </div>
        <div class="flex gap-2 mt-auto pt-2 border-t border-gray-100">
            <a href="{{ $link }}" class="flex-1 text-center text-sm font-semibold text-blue-600 hover:text-blue-800 py-1.5 rounded-lg hover:bg-blue-50 transition">
                <i class="fas fa-eye mr-1"></i> Voir
            </a>
            <a href="{{ $pdf }}" class="flex-1 text-center text-sm font-semibold text-red-600 hover:text-red-800 py-1.5 rounded-lg hover:bg-red-50 transition">
                <i class="fas fa-file-pdf mr-1"></i> PDF
            </a>
        </div>
    </div>
    @endforeach
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
                backgroundColor: '#DC2626',
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

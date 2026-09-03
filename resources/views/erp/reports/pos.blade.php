@extends('erp.layouts.app')
@section('title','Rapport POS')
@section('page-title','Rapport — Point de Vente (POS)')
@section('page-subtitle','Transactions et revenus de la boutique')

@section('content')
{{-- Filters --}}
<div class="content-card p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Année</label>
            <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach($availableYears as $y)
                <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Mois</label>
            <select name="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="0">Tous les mois</option>
                @foreach(array_slice($months,1,null,true) as $i => $m)
                <option value="{{ $i }}" @selected($month==$i)>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-gold px-4 py-2 text-sm">Filtrer</button>
        <a href="{{ route('erp.reports.pdf.pos', ['year'=>$year, 'month'=>$month]) }}"
           class="flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-red-700 transition">
            <i class="fas fa-file-pdf"></i> Télécharger PDF
        </a>
        <a href="{{ route('erp.reports.index') }}" class="text-gray-500 text-sm hover:underline self-end pb-2">
            ← Retour aux rapports
        </a>
    </form>
</div>

{{-- KPIs --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['Transactions','fa-receipt',$stats['total'],'#374151'],
        ['Complétées','fa-check-circle',$stats['completed'],'#059669'],
        ['Revenus POS','fa-cash-register','HTG '.number_format($stats['revenue'],0,'.',','),'#DC2626'],
        ['Ticket moyen','fa-tag','HTG '.number_format($stats['avg_ticket'],0,'.',','),'#7c3aed'],
    ] as [$label,$icon,$val,$color])
    <div class="content-card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $color }}15">
            <i class="fas {{ $icon }}" style="color:{{ $color }}"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400">{{ $label }}</p>
            <p class="text-xl font-extrabold text-gray-800">{{ $val }}</p>
        </div>
    </div>
    @endforeach
</div>

{{-- Monthly chart --}}
<div class="content-card p-5 mb-6">
    <h3 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <i class="fas fa-chart-bar text-green-500"></i> Revenus POS mensuels {{ $year }}
    </h3>
    <canvas id="posChart" height="120"></canvas>
</div>

{{-- Transactions table --}}
<div class="content-card overflow-hidden">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Transactions ({{ $transactions->count() }})</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="text-left px-4 py-3">Référence</th>
                    <th class="text-left px-4 py-3">Client</th>
                    <th class="text-right px-4 py-3">Sous-total</th>
                    <th class="text-right px-4 py-3">Remise</th>
                    <th class="text-right px-4 py-3">Total</th>
                    <th class="text-center px-4 py-3">Statut</th>
                    <th class="text-left px-4 py-3">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($transactions as $t)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $t->reference }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $t->client?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ number_format($t->subtotal,0,'.',',') }}</td>
                    <td class="px-4 py-3 text-right text-gray-500">{{ number_format($t->discount,0,'.',',') }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900">HTG {{ number_format($t->total,0,'.',',') }}</td>
                    <td class="px-4 py-3 text-center">
                        @if($t->status==='completed')
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">Complétée</span>
                        @elseif($t->status==='cancelled')
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Annulée</span>
                        @else
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">{{ ucfirst($t->status) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $t->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">Aucune transaction trouvée.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const posCtx = document.getElementById('posChart');
if (posCtx) {
    new Chart(posCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode(array_column($monthly,'month')) !!},
            datasets: [{
                label: 'POS HTG',
                data: {!! json_encode(array_column($monthly,'amount')) !!},
                borderColor: '#059669',
                backgroundColor: 'rgba(5,150,105,0.08)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
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

@extends('erp.layouts.app')
@section('title','Rapport Réservations')
@section('page-title','Rapport — Réservations d\'espaces')
@section('page-subtitle','Bookings coworking et salles de réunion')

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
            <label class="block text-xs font-semibold text-gray-500 mb-1">Statut</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <option value="pending" @selected($status==='pending')>En attente</option>
                <option value="confirmed" @selected($status==='confirmed')>Confirmées</option>
                <option value="cancelled" @selected($status==='cancelled')>Annulées</option>
            </select>
        </div>
        <button type="submit" class="btn-gold px-4 py-2 text-sm">Filtrer</button>
        <a href="{{ route('erp.reports.pdf.reservations', ['year'=>$year, 'status'=>$status]) }}"
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
        ['Total réservations','fa-calendar',$stats['total'],'#1e3a5f'],
        ['Confirmées','fa-calendar-check',$stats['confirmed'],'#059669'],
        ['En attente','fa-clock',$stats['pending'],'#d97706'],
        ['Revenus payés','fa-coins','HTG '.number_format($stats['revenue'],0,'.',','),'#DC2626'],
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

{{-- Bookings table --}}
<div class="content-card overflow-hidden">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Réservations {{ $year }} ({{ $bookings->count() }})</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="text-left px-4 py-3">Référence</th>
                    <th class="text-left px-4 py-3">Client</th>
                    <th class="text-left px-4 py-3">Espace</th>
                    <th class="text-left px-4 py-3">Début</th>
                    <th class="text-left px-4 py-3">Fin</th>
                    <th class="text-right px-4 py-3">Montant</th>
                    <th class="text-center px-4 py-3">Paiement</th>
                    <th class="text-center px-4 py-3">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($bookings as $b)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $b->reference }}</td>
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $b->client?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $b->space?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $b->start_datetime?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $b->end_datetime?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">HTG {{ number_format($b->total_price,0,'.',',') }}</td>
                    <td class="px-4 py-3 text-center">
                        @if($b->payment_status==='paid')
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">Payé</span>
                        @else
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">{{ ucfirst($b->payment_status ?? 'pending') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($b->status==='confirmed')
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">Confirmée</span>
                        @elseif($b->status==='cancelled')
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Annulée</span>
                        @else
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">En attente</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-gray-400">Aucune réservation trouvée.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

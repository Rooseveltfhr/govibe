@extends('erp.layouts.app')
@section('title','Réservations')
@section('page-title','Coworking & Réservations')
@section('page-subtitle','Gestion des espaces et réservations')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([['Total',$stats['total'],'bi-calendar-check-fill','#1e3a5f','#dbeafe'],["Aujourd'hui",$stats['today'],'bi-calendar2-day-fill','#059669','#d1fae5'],['Confirmées',$stats['confirmed'],'bi-check-circle-fill','#0891b2','#e0f2fe'],['En attente',$stats['pending'],'bi-clock-fill','#d97706','#fef3c7']] as [$l,$v,$i,$c,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div><p class="text-gray-400 text-xs mb-1">{{ $l }}</p><p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $v }}</p></div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}"><i class="bi {{ $i }}" style="color:{{ $c }}"></i></div>
        </div>
    </div>
    @endforeach
</div>

<div class="content-card">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <h3 class="font-semibold text-gray-800 dark:text-white">Réservations</h3>
        <a href="{{ route('erp.booking.create') }}" class="btn-gold text-sm"><i class="bi bi-plus-lg mr-1"></i> Nouvelle réservation</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-slate-800/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Titre</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Client</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Début</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Fin</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Espace</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                @forelse($bookings as $b)
                @php $sc=['pending'=>'bg-yellow-100 text-yellow-700','confirmed'=>'bg-green-100 text-green-700','cancelled'=>'bg-red-100 text-red-700']; @endphp
                <tr class="table-row">
                    <td class="px-5 py-3.5 text-sm font-medium text-gray-800 dark:text-white">{{ $b->title }}</td>
                    <td class="px-5 py-3.5 text-sm text-gray-500">{{ $b->client->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-xs text-gray-400">{{ \Carbon\Carbon::parse($b->start_at)->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-3.5 text-xs text-gray-400">{{ \Carbon\Carbon::parse($b->end_at)->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-3.5 text-xs text-gray-500">{{ $b->space ?? '—' }}</td>
                    <td class="px-5 py-3.5"><span class="badge text-xs {{ $sc[$b->status] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($b->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">
                    <i class="bi bi-calendar-check text-4xl block mb-3 opacity-30"></i>
                    <p>Aucune réservation. <a href="{{ route('erp.booking.create') }}" class="text-blue-600 hover:underline">Créer la première</a></p>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($bookings,'hasPages') && $bookings->hasPages())
    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $bookings->links() }}</div>
    @endif
</div>
@endsection

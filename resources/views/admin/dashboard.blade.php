@extends('layouts.admin')

@section('title', 'Tableau de bord')
@section('page-title', 'Tableau de bord')

@section('content')

<!-- Stats cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    @php
        $stats = [
            ['Inscriptions totales', $totalInscriptions, 'fas fa-users', '#1e3a5f', '#e8f0fe'],
            ['Hommes', $totalHommes, 'fas fa-male', '#0369a1', '#e0f2fe'],
            ['Femmes', $totalFemmes, 'fas fa-female', '#7c3aed', '#f5f3ff'],
            ['Formations actives', $totalFormations, 'fas fa-graduation-cap', '#d4a017', '#fef9e7'],
        ];
    @endphp
    @foreach($stats as [$label, $value, $icon, $color, $bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm font-medium">{{ $label }}</p>
                <p class="text-3xl font-extrabold text-gray-800 mt-1">{{ $value }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:{{ $bg }}">
                <i class="{{ $icon }} text-lg" style="color:{{ $color }}"></i>
            </div>
        </div>
        @if($label === 'Inscriptions totales' && $totalInscriptions > 0)
        <div class="mt-3 pt-3 border-t border-gray-100">
            <p class="text-xs text-gray-400">
                <span class="text-blue-600 font-medium">{{ $totalHommes }}</span> hommes ·
                <span class="text-purple-600 font-medium">{{ $totalFemmes }}</span> femmes
            </p>
        </div>
        @endif
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- Par formation -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-chart-bar mr-2" style="color:#1e3a5f"></i>
            Inscriptions par formation
        </h3>
        @if($parFormation->isEmpty())
            <p class="text-gray-400 text-sm text-center py-8">Aucune donnée disponible.</p>
        @else
        <div class="space-y-3">
            @foreach($parFormation as $f)
            <div>
                <div class="flex justify-between items-center mb-1">
                    <span class="text-sm text-gray-700 font-medium truncate max-w-[70%]">{{ $f->nom }}</span>
                    <span class="text-sm font-bold text-gray-800">{{ $f->inscriptions_count }}/{{ $f->max_participants }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    @php $pct = $f->max_participants > 0 ? min(100, ($f->inscriptions_count / $f->max_participants) * 100) : 0; @endphp
                    <div class="h-2 rounded-full transition-all" style="width:{{ $pct }}%; background: linear-gradient(135deg, #d4a017, #f5c518)"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Par département -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-map-marker-alt mr-2" style="color:#d4a017"></i>
            Statistiques par département
        </h3>
        @if($parDepartement->isEmpty())
            <p class="text-gray-400 text-sm text-center py-8">Aucune donnée disponible.</p>
        @else
        <div class="space-y-2">
            @foreach($parDepartement->take(8) as $dept)
            <div class="flex items-center justify-between py-2 border-b border-gray-50">
                <div class="flex items-center space-x-3">
                    <div class="w-2 h-2 rounded-full" style="background:#1e3a5f"></div>
                    <span class="text-sm text-gray-700">{{ $dept->departement }}</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="bg-blue-50 text-blue-800 text-xs font-bold px-2 py-0.5 rounded-full">{{ $dept->total }}</div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

<!-- Recent registrations -->
<div class="bg-white rounded-2xl border border-gray-200">
    <div class="flex items-center justify-between p-6 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">
            <i class="fas fa-clock mr-2 text-gray-400"></i>
            Inscriptions récentes
        </h3>
        <a href="{{ route('admin.inscriptions.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            Voir tout <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Participant</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Formation</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sexe</th>
                    <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recentes as $r)
                <tr class="table-row">
                    <td class="px-6 py-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background:#1e3a5f">
                                {{ strtoupper(substr($r->nom_complet, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 text-sm">{{ $r->nom_complet }}</p>
                                <p class="text-gray-400 text-xs">{{ $r->numero_inscription }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ Str::limit($r->formation->nom ?? '—', 30) }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $r->sexe === 'Masculin' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                            {{ $r->sexe }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $r->created_at->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-gray-400 text-sm">Aucune inscription pour le moment.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

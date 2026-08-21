@extends('erp.layouts.app')

@section('title', 'Partenaires')
@section('page-title', 'Partenaires')
@section('page-subtitle', 'Demandes de partenariat et collaborateurs')

@section('content')

{{-- Stats --}}
<div class="grid grid-cols-4 gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Total', $stats['total'], 'bi-handshake', '#1e3a5f', '#dbeafe'],
        ['Nouveaux', $stats['nouveau'], 'bi-bell-fill', '#d97706', '#fef3c7'],
        ['En cours', $stats['en_cours'], 'bi-hourglass-split', '#7c3aed', '#ede9fe'],
        ['Acceptés', $stats['accepte'], 'bi-check-circle-fill', '#059669', '#d1fae5'],
    ] as [$label, $val, $icon, $color, $bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $val }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}">
                <i class="bi {{ $icon }}" style="color:{{ $color }}"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="content-card">
    {{-- Toolbar --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, email, organisation..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-56 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                <option value="nouveau" {{ request('statut') == 'nouveau' ? 'selected' : '' }}>Nouveau</option>
                <option value="en_cours" {{ request('statut') == 'en_cours' ? 'selected' : '' }}>En cours</option>
                <option value="accepte" {{ request('statut') == 'accepte' ? 'selected' : '' }}>Accepté</option>
                <option value="refuse" {{ request('statut') == 'refuse' ? 'selected' : '' }}>Refusé</option>
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if(request('q') || request('statut'))
                <a href="{{ route('erp.partenaires.index') }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>
        <span class="text-xs text-gray-400">{{ $partenaires->total() }} demande(s)</span>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Contact</th>
                    <th class="px-4 py-3 text-left font-semibold">Organisation</th>
                    <th class="px-4 py-3 text-left font-semibold">Type</th>
                    <th class="px-4 py-3 text-left font-semibold">Localisation</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold">Date</th>
                    <th class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse($partenaires as $p)
                <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-5 py-3">
                        <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $p->nom_complet }}</div>
                        <div class="text-xs text-gray-400 flex gap-2 mt-0.5">
                            <a href="mailto:{{ $p->email }}" class="hover:text-red-500">{{ $p->email }}</a>
                            <span>·</span>
                            <span>{{ $p->telephone }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                        {{ $p->organisation ?: '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-block text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded-lg px-2 py-1">
                            {{ \App\Models\Partenaire::typesPartenariat()[$p->type_partenariat] ?? $p->type_partenariat }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">
                        {{ $p->ville }}, {{ $p->pays }}
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $sc = [
                                'nouveau'  => ['bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400', 'Nouveau'],
                                'en_cours' => ['bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400', 'En cours'],
                                'accepte'  => ['bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400', 'Accepté'],
                                'refuse'   => ['bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400', 'Refusé'],
                            ][$p->statut] ?? ['bg-gray-100 text-gray-600', ucfirst($p->statut)];
                        @endphp
                        <span class="inline-block text-xs font-semibold rounded-full px-2.5 py-0.5 {{ $sc[0] }}">
                            {{ $sc[1] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-400 text-xs">
                        {{ $p->created_at->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex gap-2 justify-end items-center">
                            {{-- Quick status update --}}
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open" class="btn-secondary text-xs py-1 px-2">
                                    <i class="bi bi-pencil-square"></i> Gérer
                                </button>
                                <div x-show="open" @click.away="open = false"
                                     class="absolute right-0 top-full mt-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 rounded-xl shadow-lg z-50 w-72 p-4"
                                     style="display:none;">
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-3 uppercase tracking-wide">Mettre à jour le statut</p>
                                    <form method="POST" action="{{ route('erp.partenaires.statut', $p) }}">
                                        @csrf @method('PATCH')
                                        <div class="mb-3">
                                            <label class="block text-xs text-gray-500 mb-1">Statut</label>
                                            <select name="statut" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400">
                                                @foreach(['nouveau'=>'Nouveau','en_cours'=>'En cours','accepte'=>'Accepté','refuse'=>'Refusé'] as $v => $l)
                                                    <option value="{{ $v }}" {{ $p->statut == $v ? 'selected' : '' }}>{{ $l }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="block text-xs text-gray-500 mb-1">Notes internes</label>
                                            <textarea name="notes_admin" rows="2"
                                                      class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200 focus:outline-none focus:border-red-400 resize-none"
                                                      placeholder="Notes pour l'équipe...">{{ $p->notes_admin }}</textarea>
                                        </div>
                                        <button type="submit" class="btn-primary text-xs w-full py-1.5">
                                            <i class="bi bi-check-lg"></i> Enregistrer
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('erp.partenaires.destroy', $p) }}"
                                  onsubmit="return confirm('Supprimer cette demande ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-sm">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>

                {{-- Expanded description row --}}
                @if($p->description)
                <tr class="bg-gray-50/50 dark:bg-slate-700/20">
                    <td colspan="7" class="px-5 py-2 text-xs text-gray-500 dark:text-gray-400 italic">
                        <span class="font-semibold text-gray-600 dark:text-gray-300">Description :</span>
                        {{ $p->description }}
                        @if($p->notes_admin)
                            <span class="ml-3 font-semibold text-gray-600 dark:text-gray-300">Notes admin :</span>
                            {{ $p->notes_admin }}
                        @endif
                    </td>
                </tr>
                @endif

                @empty
                <tr>
                    <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                        <i class="bi bi-handshake text-4xl mb-3 block opacity-30"></i>
                        Aucune demande de partenariat
                        @if(request('q') || request('statut'))
                            pour les filtres sélectionnés.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($partenaires->hasPages())
    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">
        {{ $partenaires->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection

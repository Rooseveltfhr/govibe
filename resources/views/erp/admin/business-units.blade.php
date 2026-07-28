@extends('erp.layouts.app')
@section('title','Unités Business')
@section('page-title','Unités Business')
@section('page-subtitle','Gérer les départements de GOVIBE Innovation Hub')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Create form --}}
    <div class="content-card p-5 h-fit">
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="bi bi-building-fill-add text-yellow-500"></i> Nouvelle unité
        </h3>
        <form action="{{ route('erp.admin.business-units.store') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nom *</label>
                <input type="text" name="name" required placeholder="Ex: GOVIBE Media"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Description</label>
                <textarea name="description" rows="2" placeholder="Description courte..."
                          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 resize-none dark:bg-slate-700 dark:border-slate-600 dark:text-white"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Icône (Bootstrap)</label>
                    <input type="text" name="icon" placeholder="bi-building"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Couleur hex</label>
                    <input type="text" name="color" placeholder="#1e3a5f"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
            </div>
            <button type="submit" class="btn-gold w-full"><i class="bi bi-plus-lg mr-2"></i>Créer</button>
        </form>
    </div>

    {{-- List --}}
    <div class="lg:col-span-2">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @forelse($units as $unit)
            <div class="content-card p-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background:{{ $unit->color ?? '#1e3a5f' }}1a">
                    <i class="bi {{ $unit->icon ?? 'bi-building' }} text-xl" style="color:{{ $unit->color ?? '#1e3a5f' }}"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-white text-sm">{{ $unit->name }}</h4>
                            @if($unit->description)
                            <p class="text-xs text-gray-400 mt-0.5 line-clamp-2">{{ $unit->description }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <span class="badge text-xs {{ $unit->active ? 'status-active' : 'status-inactive' }}">
                                {{ $unit->active ? 'Actif' : 'Inactif' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                        <span><i class="bi bi-kanban mr-1"></i>{{ $unit->projects_count }} projets</span>
                        <span><i class="bi bi-people mr-1"></i>{{ $unit->clients_count }} clients</span>
                    </div>
                    <div class="flex items-center gap-1 mt-2">
                        <form action="{{ route('erp.admin.business-units.destroy',$unit) }}" method="POST" class="inline"
                              onsubmit="return confirm('Supprimer {{ addslashes($unit->name) }} ?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 flex items-center gap-1">
                                <i class="bi bi-trash"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-2 content-card p-12 text-center text-gray-400">
                <i class="bi bi-building text-4xl block mb-3 opacity-30"></i>
                <p>Aucune unité business. Créez la première à gauche.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

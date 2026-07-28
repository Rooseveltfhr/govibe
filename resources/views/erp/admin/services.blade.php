@extends('erp.layouts.app')
@section('title','Gérer les services')
@section('page-title','Gestion des services')
@section('page-subtitle','Ajouter, modifier et supprimer les services GOVIBE')

@section('content')

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Add service form --}}
    <div class="space-y-5">
        {{-- Add service --}}
        <div class="content-card p-5">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="bi bi-plus-circle-fill text-yellow-500"></i> Nouveau service
            </h3>
            <form action="{{ route('erp.admin.services.store') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nom du service *</label>
                    <input type="text" name="name" required placeholder="Ex: Développement Web"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Catégorie</label>
                    <select name="category_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        <option value="">-- Sans catégorie --</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Unité business</label>
                    <select name="business_unit_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        <option value="">-- Toutes --</option>
                        @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}">{{ $bu->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Prix (HTG) *</label>
                        <input type="number" name="price" required min="0" placeholder="0"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Unité *</label>
                        <select name="unit" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            <option value="hour">/ heure</option>
                            <option value="day">/ jour</option>
                            <option value="month">/ mois</option>
                            <option value="project">/ projet</option>
                            <option value="session">/ session</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Description</label>
                    <textarea name="description" rows="2" placeholder="Description courte..."
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 resize-none dark:bg-slate-700 dark:border-slate-600 dark:text-white"></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded" id="active_new">
                    <label for="active_new" class="text-xs text-gray-600 dark:text-gray-400">Service actif</label>
                </div>
                <button type="submit" class="btn-gold w-full"><i class="bi bi-plus-lg mr-2"></i>Ajouter le service</button>
            </form>
        </div>

        {{-- Add category --}}
        <div class="content-card p-5">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                <i class="bi bi-tags-fill text-blue-500"></i> Nouvelle catégorie
            </h3>
            <form action="{{ route('erp.admin.services.category.store') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nom de la catégorie *</label>
                    <input type="text" name="name" required placeholder="Ex: Développement, Formation..."
                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Icône Bootstrap</label>
                        <input type="text" name="icon" placeholder="bi-globe2"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Couleur hex</label>
                        <input type="text" name="color" placeholder="#1e3a5f"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full text-sm">Créer la catégorie</button>
            </form>

            @if($categories->count())
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700 space-y-1">
                <p class="text-xs text-gray-400 mb-2">Catégories existantes</p>
                @foreach($categories as $cat)
                <div class="flex items-center justify-between py-1">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        @if($cat->icon)<i class="bi {{ $cat->icon }} mr-1.5"></i>@endif{{ $cat->name }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Right: services list --}}
    <div class="lg:col-span-2">
        <div class="content-card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">
                    Catalogue de services
                    <span class="ml-2 bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 text-xs px-2 py-0.5 rounded-full">{{ $services->total() }}</span>
                </h3>
                <form method="GET" class="flex gap-2">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Rechercher..."
                           class="border border-gray-200 rounded-xl px-3 py-1.5 text-sm focus:outline-none focus:border-blue-400 w-40 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    <select name="category_id" class="border border-gray-200 rounded-xl px-3 py-1.5 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        <option value="">Toutes catégories</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id')==$cat->id?'selected':'' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-xl text-sm text-gray-600 transition-colors">Filtrer</button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-slate-800/60">
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Service</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Catégorie</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Prix</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Unité</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Statut</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                        @forelse($services as $service)
                        <tr class="table-row" x-data="{ edit: false }">
                            <td class="px-5 py-3.5">
                                <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $service->name }}</p>
                                @if($service->description)
                                <p class="text-xs text-gray-400 truncate max-w-xs">{{ $service->description }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="badge bg-blue-50 text-blue-700 text-xs">{{ $service->category->name ?? '—' }}</span>
                            </td>
                            <td class="px-5 py-3.5 font-semibold text-gray-800 dark:text-white text-sm">
                                HTG {{ number_format($service->price, 2) }}
                            </td>
                            <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">
                                / {{ ['hour'=>'heure','day'=>'jour','month'=>'mois','project'=>'projet','session'=>'session'][$service->unit] ?? $service->unit }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="badge text-xs {{ $service->is_active ? 'status-active' : 'status-inactive' }}">
                                    {{ $service->is_active ? 'Actif' : 'Inactif' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-1">
                                    <button @click="edit = !edit" class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors">
                                        <i class="bi bi-pencil text-xs"></i>
                                    </button>
                                    <form action="{{ route('erp.admin.services.destroy',$service) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Supprimer {{ addslashes($service->name) }} ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <i class="bi bi-trash text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                                {{-- Inline edit form --}}
                                <div x-show="edit" x-transition class="mt-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl p-3">
                                    <form action="{{ route('erp.admin.services.update',$service) }}" method="POST" class="space-y-2">
                                        @csrf @method('PUT')
                                        <input type="text" name="name" value="{{ $service->name }}" required
                                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none dark:bg-slate-600 dark:border-slate-500 dark:text-white">
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="number" name="price" value="{{ $service->price }}" min="0"
                                                   class="border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none dark:bg-slate-600 dark:border-slate-500 dark:text-white">
                                            <select name="unit" class="border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none dark:bg-slate-600 dark:border-slate-500 dark:text-white">
                                                @foreach(['hour'=>'heure','day'=>'jour','month'=>'mois','project'=>'projet','session'=>'session'] as $v=>$l)
                                                <option value="{{ $v }}" {{ $service->unit===$v?'selected':'' }}>/ {{ $l }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="hidden" name="description" value="{{ $service->description }}">
                                        <input type="hidden" name="category_id" value="{{ $service->category_id }}">
                                        <input type="hidden" name="business_unit_id" value="{{ $service->business_unit_id }}">
                                        <div class="flex items-center gap-2">
                                            <input type="checkbox" name="is_active" value="1" {{ $service->is_active?'checked':'' }} class="rounded">
                                            <span class="text-xs text-gray-500">Actif</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="flex-1 bg-navy-900 text-white text-xs py-2 rounded-lg font-medium" style="background:#1e3a5f">Enregistrer</button>
                                            <button type="button" @click="edit=false" class="flex-1 bg-gray-200 text-gray-600 text-xs py-2 rounded-lg">Annuler</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-stars text-3xl block mb-3 opacity-30"></i>
                            Aucun service. Créez le premier à gauche.
                        </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($services->hasPages())
            <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $services->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

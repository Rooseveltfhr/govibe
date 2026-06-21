@extends('layouts.admin')

@section('title', 'Formations')
@section('page-title', 'Gestion des formations')

@section('content')
<div class="flex justify-between items-center mb-6">
    <p class="text-gray-500 text-sm">{{ $formations->count() }} formation(s) au total</p>
    <a href="{{ route('admin.formations.create') }}"
       class="flex items-center space-x-2 text-sm font-semibold text-white px-5 py-2.5 rounded-xl transition-colors"
       style="background:linear-gradient(135deg,#d4a017,#f5c518); color:#1e3a5f">
        <i class="fas fa-plus"></i><span>Nouvelle formation</span>
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    @forelse($formations as $f)
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
        <div class="p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                    <h3 class="font-bold text-gray-800 text-base leading-tight">{{ $f->nom }}</h3>
                    @if($f->lieu)
                    <p class="text-gray-500 text-xs mt-1"><i class="fas fa-map-marker-alt mr-1 text-red-400"></i>{{ $f->lieu }}</p>
                    @endif
                </div>
                <span class="ml-2 text-xs font-medium px-2 py-1 rounded-lg {{ $f->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $f->active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            @if($f->description)
            <p class="text-gray-600 text-sm mb-4 line-clamp-2">{{ $f->description }}</p>
            @endif

            @if($f->date_debut)
            <p class="text-xs text-gray-500 mb-3">
                <i class="fas fa-calendar mr-1"></i>
                {{ $f->date_debut->format('d/m/Y') }}
                @if($f->date_fin) → {{ $f->date_fin->format('d/m/Y') }} @endif
            </p>
            @endif

            <!-- Progress bar -->
            <div class="mb-4">
                @php $pct = $f->max_participants > 0 ? min(100, ($f->inscriptions_count / $f->max_participants) * 100) : 0; @endphp
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>{{ $f->inscriptions_count }} inscrits</span>
                    <span>Max: {{ $f->max_participants }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="h-2 rounded-full" style="width:{{ $pct }}%; background: linear-gradient(135deg, #d4a017, #f5c518)"></div>
                </div>
            </div>

            @if($f->whatsapp_link)
            <a href="{{ $f->whatsapp_link }}" target="_blank" class="flex items-center space-x-1 text-xs text-green-600 hover:text-green-800 mb-4">
                <i class="fab fa-whatsapp"></i><span>Lien WhatsApp</span>
            </a>
            @endif
        </div>

        <div class="border-t border-gray-100 px-5 py-3 flex items-center justify-between bg-gray-50">
            <a href="{{ route('admin.inscriptions.index', ['formation_id' => $f->id]) }}"
               class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                <i class="fas fa-users mr-1"></i>Voir les inscrits
            </a>
            <div class="flex items-center space-x-1">
                <a href="{{ route('admin.formations.edit', $f) }}"
                   class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors" title="Modifier">
                    <i class="fas fa-pencil text-xs"></i>
                </a>
                @if($f->inscriptions_count === 0)
                <form action="{{ route('admin.formations.destroy', $f) }}" method="POST" class="inline"
                      onsubmit="return confirm('Supprimer {{ addslashes($f->nom) }} ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Supprimer">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-3 text-center py-16 text-gray-400">
        <i class="fas fa-graduation-cap text-5xl mb-4 opacity-30 block"></i>
        <p class="text-lg font-medium">Aucune formation pour le moment</p>
        <a href="{{ route('admin.formations.create') }}" class="text-blue-600 text-sm mt-2 inline-block">Créer la première formation</a>
    </div>
    @endforelse
</div>
@endsection

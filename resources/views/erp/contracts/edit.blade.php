@extends('erp.layouts.app')
@section('title', 'Modifier '.$contract->reference)

@section('content')
<div class="content-card max-w-4xl">

  <div class="flex items-center gap-3 mb-6">
    <a href="{{ route('erp.contracts.show', $contract) }}" class="text-gray-400 hover:text-gray-600">
      <i class="fas fa-arrow-left"></i>
    </a>
    <div>
      <h1 class="text-xl font-bold text-gray-900">Modifier le contrat</h1>
      <p class="text-sm text-gray-500 font-mono">{{ $contract->reference }}</p>
    </div>
  </div>

  <form action="{{ route('erp.contracts.update', $contract) }}" method="POST" class="space-y-5">
    @csrf @method('PUT')

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Client</label>
        <div class="border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-600">
          {{ $contract->client?->name ?? '—' }}
        </div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Titre *</label>
        <input type="text" name="title" value="{{ old('title', $contract->title) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Début *</label>
        <input type="date" name="start_date" value="{{ old('start_date', $contract->start_date->format('Y-m-d')) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Fin</label>
        <input type="date" name="end_date" value="{{ old('end_date', $contract->end_date?->format('Y-m-d')) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Valeur (HTG)</label>
        <input type="number" name="value" min="0" step="0.01" value="{{ old('value', $contract->value) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Statut</label>
        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
          <option value="draft" @selected($contract->status==='draft')>Brouillon</option>
          <option value="active" @selected($contract->status==='active')>Actif</option>
          <option value="expired" @selected($contract->status==='expired')>Expiré</option>
          <option value="terminated" @selected($contract->status==='terminated')>Résilié</option>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1">Contenu du contrat *</label>
      <textarea name="content" rows="28" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-red-500 focus:border-transparent"
                style="line-height:1.8"
      >{{ old('content', $contract->content) }}</textarea>
    </div>

    <div class="flex gap-3">
      <button type="submit" class="btn-gold px-8">
        <i class="fas fa-save mr-1"></i> Enregistrer
      </button>
      <a href="{{ route('erp.contracts.show', $contract) }}"
         class="bg-gray-100 text-gray-700 px-6 py-2.5 rounded-xl font-semibold text-sm hover:bg-gray-200 transition">
        Annuler
      </a>
      <form method="POST" action="{{ route('erp.contracts.destroy', $contract) }}"
            class="ml-auto"
            onsubmit="return confirm('Supprimer ce contrat définitivement ?')">
        @csrf @method('DELETE')
        <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-semibold px-2">
          <i class="fas fa-trash mr-1"></i> Supprimer
        </button>
      </form>
    </div>
  </form>
</div>
@endsection

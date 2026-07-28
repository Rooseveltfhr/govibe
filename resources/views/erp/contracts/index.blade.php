@extends('erp.layouts.app')
@section('title', 'Contrats')

@section('content')
<div class="content-card">

  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Contrats clients</h1>
      <p class="text-sm text-gray-500 mt-0.5">Créer, modifier, signer et envoyer des contrats</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <a href="{{ route('erp.contracts.templates') }}" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">
        <i class="fas fa-layer-group mr-1"></i> Modèles
      </a>
      <a href="{{ route('erp.contracts.create') }}" class="btn-gold text-sm">
        <i class="fas fa-plus mr-1"></i> Nouveau contrat
      </a>
    </div>
  </div>

  {{-- Stats --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    @php $cards = [
      ['label'=>'Total','value'=>$stats['total'],'icon'=>'file-contract','color'=>'blue'],
      ['label'=>'Actifs','value'=>$stats['active'],'icon'=>'check-circle','color'=>'green'],
      ['label'=>'Brouillons','value'=>$stats['draft'],'icon'=>'edit','color'=>'yellow'],
      ['label'=>'Signés','value'=>$stats['signed'],'icon'=>'signature','color'=>'purple'],
    ]; @endphp
    @foreach($cards as $c)
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
      <i class="fas fa-{{ $c['icon'] }} text-{{ $c['color'] }}-500 text-lg mb-1"></i>
      <div class="text-2xl font-bold text-gray-900">{{ $c['value'] }}</div>
      <div class="text-xs text-gray-500">{{ $c['label'] }}</div>
    </div>
    @endforeach
  </div>

  {{-- Filters --}}
  <form method="GET" class="flex flex-wrap gap-3 mb-5">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      <option value="all" @selected($status==='all')>Tous les statuts</option>
      <option value="draft" @selected($status==='draft')>Brouillons</option>
      <option value="active" @selected($status==='active')>Actifs</option>
      <option value="expired" @selected($status==='expired')>Expirés</option>
      <option value="terminated" @selected($status==='terminated')>Résiliés</option>
    </select>
    <button type="submit" class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-800 transition">
      <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
  </form>

  @if(session('success'))
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-4 text-sm">
    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
  </div>
  @endif

  {{-- Table --}}
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold">
          <th class="pb-3 pr-4">Référence</th>
          <th class="pb-3 pr-4">Client</th>
          <th class="pb-3 pr-4">Titre</th>
          <th class="pb-3 pr-4">Valeur</th>
          <th class="pb-3 pr-4">Statut</th>
          <th class="pb-3 pr-4">Signé le</th>
          <th class="pb-3 pr-4">Envoyé le</th>
          <th class="pb-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($contracts as $c)
        <tr class="hover:bg-gray-50 transition">
          <td class="py-3 pr-4 font-mono text-xs font-semibold text-red-600">{{ $c->reference }}</td>
          <td class="py-3 pr-4 font-medium text-gray-900">{{ $c->client?->name ?? '—' }}</td>
          <td class="py-3 pr-4 text-gray-700 max-w-xs truncate">{{ $c->title }}</td>
          <td class="py-3 pr-4 text-gray-600">
            {{ $c->value ? 'HTG '.number_format($c->value,0,'.',',') : '—' }}
          </td>
          <td class="py-3 pr-4">{!! $c->status_badge !!}</td>
          <td class="py-3 pr-4 text-gray-500 text-xs">
            {{ $c->signed_at ? $c->signed_at->format('d/m/Y') : '—' }}
          </td>
          <td class="py-3 pr-4 text-gray-500 text-xs">
            {{ $c->sent_at ? $c->sent_at->format('d/m/Y') : '—' }}
          </td>
          <td class="py-3">
            <div class="flex gap-2">
              <a href="{{ route('erp.contracts.show', $c) }}"
                 class="text-blue-600 hover:text-blue-800 text-xs font-semibold">
                <i class="fas fa-eye"></i>
              </a>
              <a href="{{ route('erp.contracts.edit', $c) }}"
                 class="text-gray-500 hover:text-gray-700 text-xs font-semibold">
                <i class="fas fa-edit"></i>
              </a>
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" class="py-10 text-center text-gray-400">
            <i class="fas fa-file-contract text-3xl mb-2 block opacity-40"></i>
            Aucun contrat.
            <a href="{{ route('erp.contracts.create') }}" class="text-red-600 font-semibold hover:underline ml-1">
              Créer le premier →
            </a>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">{{ $contracts->links() }}</div>
</div>
@endsection

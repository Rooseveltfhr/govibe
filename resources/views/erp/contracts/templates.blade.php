@extends('erp.layouts.app')
@section('title', 'Modèles de contrats')

@section('content')
<div class="content-card">

  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Modèles de contrats</h1>
      <p class="text-sm text-gray-500 mt-0.5">Bibliothèque de templates prêts à utiliser</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <a href="{{ route('erp.contracts.index') }}" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">
        <i class="fas fa-list mr-1"></i> Tous les contrats
      </a>
      {{-- Seed button (first time) --}}
      @if($templates->count() === 0)
      <form method="POST" action="{{ route('erp.contracts.templates.seed') }}">
        @csrf
        <button type="submit" class="btn-gold text-sm">
          <i class="fas fa-magic mr-1"></i> Installer les 5 modèles GOVIBE
        </button>
      </form>
      @else
      <button onclick="document.getElementById('modal-new-tpl').classList.remove('hidden')"
              class="btn-gold text-sm">
        <i class="fas fa-plus mr-1"></i> Nouveau modèle
      </button>
      @endif
    </div>
  </div>

  @if(session('success'))
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-5 text-sm">
    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
  </div>
  @endif

  @if($templates->isEmpty())
  {{-- Empty state --}}
  <div class="text-center py-16 text-gray-400">
    <i class="fas fa-file-alt text-5xl mb-3 block opacity-30"></i>
    <p class="text-lg font-semibold mb-1">Aucun modèle installé</p>
    <p class="text-sm mb-4">Cliquez sur « Installer les 5 modèles GOVIBE » pour démarrer avec des contrats prêts-à-l'emploi.</p>
  </div>
  @else

  {{-- Templates by category --}}
  @php
    $byCategory = $templates->groupBy('category');
    $catColors  = [
      'coworking'     => ['bg'=>'#DBEAFE','text'=>'#1D4ED8','label'=>'Coworking'],
      'erp'           => ['bg'=>'#FEE2E2','text'=>'#DC2626','label'=>'ERP / SaaS'],
      'formation'     => ['bg'=>'#D1FAE5','text'=>'#065F46','label'=>'Formation'],
      'developpement' => ['bg'=>'#EDE9FE','text'=>'#5B21B6','label'=>'Développement'],
      'nda'           => ['bg'=>'#FEF3C7','text'=>'#92400E','label'=>'Confidentialité'],
      'autre'         => ['bg'=>'#F3F4F6','text'=>'#374151','label'=>'Autre'],
    ];
  @endphp

  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    @foreach($templates as $tpl)
    @php $cc = $catColors[$tpl->category] ?? $catColors['autre']; @endphp

    <div class="border border-gray-200 rounded-2xl p-5 hover:shadow-md transition">
      <div class="flex items-start justify-between mb-3">
        <span class="text-xs font-bold px-2 py-0.5 rounded-full"
              style="background:{{ $cc['bg'] }};color:{{ $cc['text'] }}">
          {{ $cc['label'] }}
        </span>
        <span class="text-xs text-gray-400">{{ $tpl->contracts_count ?? 0 }} contrats</span>
      </div>

      <h3 class="font-bold text-gray-900 mb-1">{{ $tpl->title }}</h3>
      <p class="text-xs text-gray-500 mb-3 line-clamp-2">{{ $tpl->description }}</p>

      @if($tpl->variables)
      <div class="flex flex-wrap gap-1 mb-4">
        @foreach(array_slice($tpl->variables, 0, 5) as $v)
        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded-full font-mono">&#123;&#123;{{ $v }}&#125;&#125;</span>
        @endforeach
        @if(count($tpl->variables) > 5)
        <span class="text-xs text-gray-400">+{{ count($tpl->variables) - 5 }}…</span>
        @endif
      </div>
      @endif

      <a href="{{ route('erp.contracts.create', ['template_id' => $tpl->id]) }}"
         class="block w-full text-center py-2 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700 transition">
        <i class="fas fa-file-signature mr-1"></i> Utiliser ce modèle
      </a>
    </div>
    @endforeach
  </div>
  @endif

  {{-- Reinstall option if templates exist --}}
  @if($templates->isNotEmpty())
  <div class="mt-6 text-center">
    <form method="POST" action="{{ route('erp.contracts.templates.seed') }}"
          onsubmit="return confirm('Réinstaller les 5 modèles GOVIBE ? Les modèles existants ne seront pas écrasés.')">
      @csrf
      <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 underline">
        <i class="fas fa-redo mr-1"></i> Réinstaller les modèles GOVIBE par défaut
      </button>
    </form>
  </div>
  @endif
</div>

{{-- New Template Modal --}}
<div id="modal-new-tpl" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between p-5 border-b border-gray-200 sticky top-0 bg-white">
      <h2 class="font-bold text-gray-900"><i class="fas fa-plus text-red-500 mr-2"></i>Nouveau modèle</h2>
      <button onclick="document.getElementById('modal-new-tpl').classList.add('hidden')"
              class="text-gray-400 hover:text-gray-700 text-xl">&times;</button>
    </div>
    <form action="{{ route('erp.contracts.templates.store') }}" method="POST" class="p-5 space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Titre *</label>
        <input type="text" name="title" required placeholder="Ex: Contrat de service personnalisé"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Catégorie *</label>
        <select name="category" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
          <option value="coworking">Coworking</option>
          <option value="erp">ERP / SaaS</option>
          <option value="formation">Formation</option>
          <option value="developpement">Développement</option>
          <option value="nda">Confidentialité (NDA)</option>
          <option value="autre">Autre</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Description courte</label>
        <input type="text" name="description" placeholder="Brève description du modèle"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">
          Corps du contrat *
          <span class="text-xs text-gray-400 ml-1 font-normal">Utilisez &#123;&#123;variable&#125;&#125; pour les champs à remplir</span>
        </label>
        <textarea name="body" rows="12" required placeholder="Saisissez le contenu du contrat avec des &#123;&#123;variables&#125;&#125; à remplacer..."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-red-500 focus:border-transparent"></textarea>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="btn-gold flex-1 text-sm">
          <i class="fas fa-save mr-1"></i> Créer le modèle
        </button>
        <button type="button" onclick="document.getElementById('modal-new-tpl').classList.add('hidden')"
                class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-xl font-semibold text-sm">Annuler</button>
      </div>
    </form>
  </div>
</div>
@endsection

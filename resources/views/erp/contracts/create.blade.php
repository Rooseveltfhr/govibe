@extends('erp.layouts.app')
@section('title', 'Nouveau contrat')

@section('content')
<div class="content-card max-w-4xl">

  <div class="flex items-center gap-3 mb-6">
    <a href="{{ route('erp.contracts.index') }}" class="text-gray-400 hover:text-gray-600">
      <i class="fas fa-arrow-left"></i>
    </a>
    <div>
      <h1 class="text-xl font-bold text-gray-900">Nouveau contrat</h1>
      @if($template)
      <p class="text-sm text-gray-500 mt-0.5">
        Modèle : <span class="font-semibold text-red-600">{{ $template->title }}</span>
      </p>
      @endif
    </div>
  </div>

  @if(!$template && $templates->isNotEmpty())
  {{-- Template picker --}}
  <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
    <p class="text-sm text-blue-700 font-semibold mb-3">
      <i class="fas fa-lightbulb mr-1"></i> Démarrer depuis un modèle (recommandé)
    </p>
    <div class="flex flex-wrap gap-2">
      @foreach($templates as $tpl)
      <a href="{{ route('erp.contracts.create', ['template_id' => $tpl->id]) }}"
         class="text-xs bg-white border border-blue-200 text-blue-700 px-3 py-1.5 rounded-lg font-semibold hover:bg-blue-700 hover:text-white transition">
        {{ $tpl->title }}
      </a>
      @endforeach
    </div>
  </div>
  @endif

  <form action="{{ route('erp.contracts.store') }}" method="POST" class="space-y-5">
    @csrf

    @if($template)
    <input type="hidden" name="template_id" value="{{ $template->id }}">
    @endif

    {{-- Client + Title --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Client *</label>
        <select name="client_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
          <option value="">— Sélectionner un client —</option>
          @foreach($clients as $c)
          <option value="{{ $c->id }}" @selected(old('client_id') == $c->id)>{{ $c->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Titre du contrat *</label>
        <input type="text" name="title" required
               value="{{ old('title', $template?->title ?? '') }}"
               placeholder="Ex: Contrat de location coworking"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
    </div>

    {{-- Variables (if template has them) --}}
    @if($template && $template->variables)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
      <p class="text-sm font-bold text-amber-800 mb-3">
        <i class="fas fa-fill-drip mr-1"></i> Remplir les variables du modèle
      </p>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" id="variables-grid">
        @foreach($template->variables as $var)
        <div>
          <label class="block text-xs font-semibold text-amber-700 mb-1 uppercase tracking-wide">{{ str_replace('_',' ', $var) }}</label>
          <input type="text" id="var_{{ $var }}" placeholder="{{ str_replace('_',' ', $var) }}"
                 class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 focus:border-transparent bg-white"
                 oninput="replaceVar('{{ $var }}', this.value)">
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Dates + Value + Status --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Date de début *</label>
        <input type="date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Date de fin</label>
        <input type="date" name="end_date" value="{{ old('end_date') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Valeur (HTG)</label>
        <input type="number" name="value" min="0" step="0.01" value="{{ old('value') }}" placeholder="0"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Statut</label>
        <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
          <option value="draft">Brouillon</option>
          <option value="active">Actif</option>
        </select>
      </div>
    </div>

    {{-- Content editor --}}
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1">Contenu du contrat *</label>
      <textarea name="content" id="contract-content" rows="24" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-red-500 focus:border-transparent"
                style="line-height:1.8"
      >{{ old('content', $template?->body ?? '') }}</textarea>
      <p class="text-xs text-gray-400 mt-1">
        <i class="fas fa-info-circle mr-1"></i>
        Le contenu peut être modifié librement. Les variables sont déjà remplacées si vous les avez renseignées ci-dessus.
      </p>
    </div>

    {{-- Submit --}}
    <div class="flex gap-3 pt-2">
      <button type="submit" class="btn-gold px-8">
        <i class="fas fa-save mr-1"></i> Créer le contrat
      </button>
      <a href="{{ route('erp.contracts.index') }}"
         class="bg-gray-100 text-gray-700 px-6 py-2.5 rounded-xl font-semibold text-sm hover:bg-gray-200 transition">
        Annuler
      </a>
    </div>
  </form>
</div>

@push('scripts')
<script>
// Live variable replacement in textarea
const originalContent = document.getElementById('contract-content').value;
const varState = {};

function replaceVar(varName, value) {
  varState[varName] = value;
  let content = originalContent;
  for (const [k, v] of Object.entries(varState)) {
    content = content.replaceAll('{{' + k + '}}', v || '{{' + k + '}}');
    content = content.replaceAll('{{ ' + k + ' }}', v || '{{ ' + k + ' }}');
  }
  document.getElementById('contract-content').value = content;
}
</script>
@endpush
@endsection

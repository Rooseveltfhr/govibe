@extends('erp.layouts.app')
@section('title', 'Plans & Abonnements')

@section('content')
<div class="content-card">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Plans & Abonnements</h1>
      <p class="text-sm text-gray-500 mt-0.5">Sélectionnez un plan et assignez-le à un client</p>
    </div>
    <a href="{{ route('erp.contracts.templates') }}" class="text-sm text-red-600 font-semibold hover:text-red-700">
      <i class="fas fa-file-contract mr-1"></i> Gérer les modèles de contrats
    </a>
  </div>

  {{-- Stats --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-8">
    @php $sc = [
      ['label'=>'Abonnements actifs','value'=>$stats['active'],'icon'=>'check-circle','color'=>'green'],
      ['label'=>'En essai','value'=>$stats['trial'],'icon'=>'clock','color'=>'blue'],
      ['label'=>'Expirés','value'=>$stats['expired'],'icon'=>'times-circle','color'=>'red'],
      ['label'=>'Revenu mensuel','value'=>number_format($stats['revenue'],0,'.',',').' HTG','icon'=>'chart-line','color'=>'yellow'],
    ]; @endphp
    @foreach($sc as $c)
    <div class="bg-white border border-gray-200 rounded-xl p-4">
      <i class="fas fa-{{ $c['icon'] }} text-{{ $c['color'] }}-500 text-lg mb-1"></i>
      <div class="text-xl font-bold text-gray-900 mt-1">{{ $c['value'] }}</div>
      <div class="text-xs text-gray-500">{{ $c['label'] }}</div>
    </div>
    @endforeach
  </div>

  @if(session('success'))
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-5 text-sm">
    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
  </div>
  @endif

  {{-- Plans Cards --}}
  <h2 class="text-base font-bold text-gray-700 mb-4 uppercase tracking-wide text-sm">
    <i class="fas fa-layer-group text-red-500 mr-1"></i> Nos plans
  </h2>

  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-10">
    @php
      $planIcons = [
        'starter'    => ['icon'=>'seedling',      'color'=>'#6B7280'],
        'business'   => ['icon'=>'briefcase',     'color'=>'#DC2626'],
        'ong'        => ['icon'=>'hands-helping', 'color'=>'#16A34A'],
        'academy'    => ['icon'=>'graduation-cap','color'=>'#7C3AED'],
        'enterprise' => ['icon'=>'building',      'color'=>'#D97706'],
      ];
    @endphp

    @foreach($plans as $plan)
    @php
      $slug  = is_object($plan) ? $plan->slug : ($plan['slug'] ?? '');
      $pname = is_object($plan) ? $plan->name : ($plan['name'] ?? '');
      $desc  = is_object($plan) ? $plan->description : ($plan['description'] ?? '');
      $tgt   = is_object($plan) ? $plan->target_audience : ($plan['target_audience'] ?? '');
      $pm    = is_object($plan) ? $plan->price_monthly : ($plan['price_monthly'] ?? 0);
      $py    = is_object($plan) ? $plan->price_yearly : ($plan['price_yearly'] ?? 0);
      $cur   = is_object($plan) ? $plan->currency : ($plan['currency'] ?? 'HTG');
      $feats = is_object($plan) ? $plan->features : ($plan['features'] ?? []);
      $color = is_object($plan) ? $plan->color : ($plan['color'] ?? '#DC2626');
      $badge = is_object($plan) ? $plan->badge : ($plan['badge'] ?? null);
      $pi    = $planIcons[$slug] ?? ['icon'=>'cube','color'=>$color];
    @endphp

    <div class="border-2 rounded-2xl p-5 relative transition hover:shadow-lg"
         style="border-color:{{ $color }}22;">

      @if($badge)
      <span class="absolute top-0 right-0 text-white text-xs font-bold px-3 py-1 rounded-bl-xl rounded-tr-xl"
            style="background:{{ $color }}">{{ $badge }}</span>
      @endif

      <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3"
           style="background:{{ $color }}22;">
        <i class="fas fa-{{ $pi['icon'] }}" style="color:{{ $color }}"></i>
      </div>

      <div class="font-black text-gray-900 text-lg leading-tight">{{ $pname }}</div>
      <div class="text-xs text-gray-500 mb-2">{{ $tgt }}</div>
      <div class="text-2xl font-black mb-1" style="color:{{ $color }}">
        {{ number_format($pm, 0, '.', ',') }}
        <span class="text-sm font-semibold text-gray-500">{{ $cur }}/mois</span>
      </div>
      <div class="text-xs text-gray-400 mb-3">
        {{ number_format($py, 0, '.', ',') }} {{ $cur }}/an
      </div>

      <ul class="text-xs text-gray-600 space-y-1 mb-4">
        @foreach(array_slice((array)$feats, 0, 4) as $f)
        <li class="flex items-start gap-1.5">
          <i class="fas fa-check text-green-500 mt-0.5 flex-shrink-0"></i>{{ $f }}
        </li>
        @endforeach
        @if(count((array)$feats) > 4)
        <li class="text-gray-400 italic text-xs">+{{ count((array)$feats) - 4 }} autres…</li>
        @endif
      </ul>

      {{-- Assign button --}}
      <button
        onclick="openAssignModal('{{ $slug }}','{{ $pname }}',{{ $pm }},'{{ $cur }}')"
        class="w-full py-2 rounded-xl text-sm font-bold text-white transition hover:opacity-90"
        style="background:{{ $color }}">
        <i class="fas fa-user-plus mr-1"></i> Assigner à un client
      </button>
    </div>
    @endforeach
  </div>

  {{-- Active subscriptions table --}}
  <h2 class="text-base font-bold text-gray-700 mb-4 uppercase tracking-wide text-sm">
    <i class="fas fa-list text-red-500 mr-1"></i> Abonnements clients actifs
  </h2>

  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold">
          <th class="pb-3 pr-4">Client</th>
          <th class="pb-3 pr-4">Plan</th>
          <th class="pb-3 pr-4">Prix</th>
          <th class="pb-3 pr-4">Cycle</th>
          <th class="pb-3 pr-4">Début</th>
          <th class="pb-3 pr-4">Statut</th>
          <th class="pb-3 pr-4">Assigné par</th>
          <th class="pb-3">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($subscriptions as $sub)
        <tr class="hover:bg-gray-50">
          <td class="py-3 pr-4 font-medium text-gray-900">{{ $sub->client?->name ?? '—' }}</td>
          <td class="py-3 pr-4">
            <span class="font-semibold text-gray-800">{{ $sub->plan_name }}</span>
          </td>
          <td class="py-3 pr-4">{{ number_format($sub->price,0,'.',',') }} {{ $sub->currency }}</td>
          <td class="py-3 pr-4 capitalize text-gray-600">{{ $sub->billing_cycle === 'monthly' ? 'Mensuel' : 'Annuel' }}</td>
          <td class="py-3 pr-4 text-gray-500">{{ $sub->starts_at->format('d/m/Y') }}</td>
          <td class="py-3 pr-4">{!! $sub->status_badge !!}</td>
          <td class="py-3 pr-4 text-gray-500">{{ $sub->assignedBy?->name ?? 'Auto' }}</td>
          <td class="py-3">
            <form method="POST" action="{{ route('erp.plans.cancel', $sub) }}"
                  onsubmit="return confirm('Annuler cet abonnement ?')">
              @csrf @method('PATCH')
              <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-semibold">
                <i class="fas fa-times mr-0.5"></i> Annuler
              </button>
            </form>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8" class="py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-inbox text-2xl block mb-2 opacity-40"></i>
            Aucun abonnement actif pour l'instant.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Assign Modal --}}
<div id="modal-assign" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
    <div class="flex items-center justify-between p-5 border-b border-gray-200">
      <h2 class="font-bold text-gray-900">
        <i class="fas fa-user-plus text-red-500 mr-2"></i>
        Assigner le plan <span id="modal-plan-name" class="text-red-600"></span>
      </h2>
      <button onclick="document.getElementById('modal-assign').classList.add('hidden')"
              class="text-gray-400 hover:text-gray-700 text-xl">&times;</button>
    </div>

    <form action="{{ route('erp.plans.assign') }}" method="POST" class="p-5 space-y-4">
      @csrf
      <input type="hidden" name="plan_slug" id="f-plan-slug">
      <input type="hidden" name="plan_name" id="f-plan-name">

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Client *</label>
        <select name="client_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
          <option value="">— Sélectionner un client —</option>
          @foreach($clients as $c)
          <option value="{{ $c->id }}">{{ $c->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Prix</label>
          <input type="number" name="price" id="f-price" step="0.01" min="0" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Devise</label>
          <input type="text" name="currency" id="f-currency" value="HTG"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Cycle de facturation</label>
        <select name="billing_cycle"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
          <option value="monthly">Mensuel</option>
          <option value="yearly">Annuel</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Début *</label>
          <input type="date" name="starts_at" value="{{ date('Y-m-d') }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Fin (optionnel)</label>
          <input type="date" name="ends_at"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
        </div>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Statut</label>
        <select name="status"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
          <option value="active">Actif</option>
          <option value="trial">Essai gratuit</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Notes internes</label>
        <textarea name="notes" rows="2" placeholder="Remarques sur l'abonnement…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"></textarea>
      </div>

      <div class="flex gap-3 pt-1">
        <button type="submit" class="btn-gold flex-1 text-sm">
          <i class="fas fa-check mr-1"></i> Assigner
        </button>
        <button type="button"
                onclick="document.getElementById('modal-assign').classList.add('hidden')"
                class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-xl font-semibold text-sm hover:bg-gray-200 transition">
          Annuler
        </button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function openAssignModal(slug, name, price, currency) {
  document.getElementById('f-plan-slug').value  = slug;
  document.getElementById('f-plan-name').value  = name;
  document.getElementById('f-price').value      = price;
  document.getElementById('f-currency').value   = currency;
  document.getElementById('modal-plan-name').textContent = name;
  document.getElementById('modal-assign').classList.remove('hidden');
}
</script>
@endpush
@endsection

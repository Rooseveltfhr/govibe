@extends('erp.layouts.app')

@section('title', 'Catalogue des plans')
@section('page-title', 'Catalogue des plans')
@section('page-subtitle', 'Ce que GOVIBE vend par abonnement, et à quel prix')

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-400">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="flex items-center justify-between mb-4 flex-wrap gap-3">
    <a href="{{ route('erp.abonnements.index') }}" class="text-sm text-gray-400 hover:text-red-500">
        <i class="bi bi-arrow-left"></i> Abonnements
    </a>
    <button type="button" class="btn-primary text-sm" onclick="ouvrirPlan(null)">
        <i class="bi bi-plus-lg"></i> Nouveau plan
    </button>
</div>

<div class="content-card">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Plan</th>
                    <th class="px-4 py-3 text-left font-semibold">Service</th>
                    <th class="px-4 py-3 text-right font-semibold">Mensuel</th>
                    <th class="px-4 py-3 text-right font-semibold">Annuel</th>
                    <th class="px-4 py-3 text-right font-semibold">Taxe</th>
                    <th class="px-4 py-3 text-center font-semibold">Abonnés</th>
                    <th class="px-4 py-3 text-center font-semibold">Actif</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($plans as $p)
                    @php
                        $chargePlan = [
                            'slug' => $p->slug,
                            'nom' => $p->nom,
                            'service' => $p->service,
                            'description' => $p->description,
                            'prix_mensuel' => $p->prix_mensuel,
                            'prix_annuel' => $p->prix_annuel,
                            'devise' => $p->devise,
                            'tca_taux' => $p->tca_taux,
                            'quotas' => collect($p->quotas ?? [])->map(fn ($v, $k) => $k.': '.$v)->implode("\n"),
                            'fonctionnalites' => implode("\n", $p->fonctionnalites ?? []),
                            'essai_jours' => $p->essai_jours,
                            'sur_devis' => $p->sur_devis,
                            'actif' => $p->actif,
                            'mis_en_avant' => $p->mis_en_avant,
                            'ordre' => $p->ordre,
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30">
                        <td class="px-5 py-3">
                            <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $p->nom }}</div>
                            <div class="text-xs text-gray-400">
                                /{{ $p->slug }}
                                @if ($p->essai_jours > 0) &middot; essai {{ $p->essai_jours }} j @endif
                                @if ($p->sur_devis) &middot; sur devis @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $p->service_libelle }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            @if ($p->sur_devis || $p->prix_mensuel === null) <span class="text-xs text-gray-400">—</span>
                            @else {{ rtrim(rtrim(number_format((float) $p->prix_mensuel, 2, ',', ' '), '0'), ',') }} {{ $p->devise }} @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            @if ($p->sur_devis || $p->prix_annuel === null) <span class="text-xs text-gray-400">—</span>
                            @else {{ rtrim(rtrim(number_format((float) $p->prix_annuel, 2, ',', ' '), '0'), ',') }} {{ $p->devise }} @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums {{ (float) $p->tca_taux === 0.0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-700 dark:text-gray-200' }}">
                            {{ rtrim(rtrim(number_format((float) $p->tca_taux, 2, ',', ' '), '0'), ',') }} %
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300 tabular-nums">{{ $p->abonnements_count }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($p->actif)
                                <span class="text-xs font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-2 py-1">Oui</span>
                            @else
                                <span class="text-xs font-bold bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400 rounded px-2 py-1">Non</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" class="text-xs text-gray-400 hover:text-red-500 mr-2"
                                    onclick='ouvrirPlan(@json($chargePlan))'>
                                <i class="bi bi-pencil"></i> Modifier
                            </button>
                            <form method="POST" action="{{ route('erp.abonnements.plans.destroy', $p) }}" class="inline"
                                  onsubmit="return confirm('Retirer « {{ $p->nom }} » du catalogue ? Les abonnements en cours gardent leur nom et leur prix.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-500"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-boxes text-4xl mb-3 block opacity-30"></i>
                            Aucun plan. Créez-en un pour commencer à vendre par abonnement.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div id="modalePlan" class="fixed inset-0 z-50 items-center justify-center p-4 bg-black/50" style="display:none">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-800">
            <span class="font-semibold text-gray-800 dark:text-gray-100" id="modaleTitrePlan">Nouveau plan</span>
            <button type="button" onclick="fermerPlan()" class="text-gray-400 hover:text-red-500"><i class="bi bi-x-lg"></i></button>
        </div>

        <form method="POST" id="formPlan" class="p-5 space-y-4">
            @csrf
            <input type="hidden" name="_method" id="planMethod" value="POST">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Nom *</label>
                    <input type="text" name="nom" id="p_nom" required maxlength="120" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Service *</label>
                    <select name="service" id="p_service" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        @foreach (\App\Models\Plan::services() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Description</label>
                <textarea name="description" id="p_desc" rows="2" maxlength="2000" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200"></textarea>
            </div>

            <div class="grid grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Mensuel</label>
                    <input type="number" step="0.01" min="0" name="prix_mensuel" id="p_mensuel" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Annuel</label>
                    <input type="number" step="0.01" min="0" name="prix_annuel" id="p_annuel" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Devise</label>
                    <select name="devise" id="p_devise" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        <option value="USD">USD</option><option value="HTG">HTG</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Taxe %</label>
                    <input type="number" step="0.01" min="0" max="100" name="tca_taux" id="p_taxe" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Quotas <span class="text-gray-300">— une ligne « clé: valeur »</span></label>
                <textarea name="quotas" id="p_quotas" rows="4" maxlength="2000" placeholder="stockage_mo: 5000&#10;boites_email: 1&#10;sites: 1" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200"></textarea>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Fonctionnalités <span class="text-gray-300">— une par ligne</span></label>
                <textarea name="fonctionnalites" id="p_fonctions" rows="5" maxlength="4000" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3 items-end">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Jours d'essai</label>
                    <input type="number" min="0" max="365" name="essai_jours" id="p_essai" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Ordre</label>
                    <input type="number" min="0" max="999" name="ordre" id="p_ordre" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
            </div>

            <div class="flex items-center gap-5 flex-wrap">
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="hidden" name="sur_devis" value="0">
                    <input type="checkbox" name="sur_devis" id="p_devis" value="1"> Sur devis
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" id="p_actif" value="1"> Actif
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="hidden" name="mis_en_avant" value="0">
                    <input type="checkbox" name="mis_en_avant" id="p_avant" value="1"> Mis en avant
                </label>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primary text-sm flex-1">Enregistrer</button>
                <button type="button" onclick="fermerPlan()" class="btn-secondary text-sm">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
  var URL_PLAN_CREER    = @json(route('erp.abonnements.plans.store'));
  var URL_PLAN_MODIFIER = @json(route('erp.abonnements.plans.update', ['plan' => '__SLUG__']));

  function ouvrirPlan(p) {
    var f = document.getElementById('formPlan');
    document.getElementById('modaleTitrePlan').textContent = p ? 'Modifier ' + p.nom : 'Nouveau plan';
    f.action = p ? URL_PLAN_MODIFIER.replace('__SLUG__', p.slug) : URL_PLAN_CREER;
    document.getElementById('planMethod').value = p ? 'PUT' : 'POST';

    var v = {
      nom: '', service: 'site_web', description: '', prix_mensuel: '', prix_annuel: '',
      devise: 'USD', tca_taux: 0, quotas: '', fonctionnalites: '', essai_jours: 0,
      sur_devis: false, actif: true, mis_en_avant: false, ordre: 0,
    };
    if (p) { for (var k in v) if (p[k] !== undefined && p[k] !== null) v[k] = p[k]; }

    document.getElementById('p_nom').value      = v.nom;
    document.getElementById('p_service').value  = v.service;
    document.getElementById('p_desc').value     = v.description;
    document.getElementById('p_mensuel').value  = v.prix_mensuel;
    document.getElementById('p_annuel').value   = v.prix_annuel;
    document.getElementById('p_devise').value   = v.devise;
    document.getElementById('p_taxe').value     = v.tca_taux;
    document.getElementById('p_quotas').value   = v.quotas;
    document.getElementById('p_fonctions').value= v.fonctionnalites;
    document.getElementById('p_essai').value    = v.essai_jours;
    document.getElementById('p_ordre').value    = v.ordre;
    document.getElementById('p_devis').checked  = !!v.sur_devis;
    document.getElementById('p_actif').checked  = !!v.actif;
    document.getElementById('p_avant').checked  = !!v.mis_en_avant;

    document.getElementById('modalePlan').style.display = 'flex';
  }

  function fermerPlan() { document.getElementById('modalePlan').style.display = 'none'; }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fermerPlan(); });
</script>

@endsection

@extends('erp.layouts.app')

@section('title', 'Catalogue Agents IA')
@section('page-title', 'Catalogue Agents IA')
@section('page-subtitle', "Ce que GOVIBE propose, et à quel prix")

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
    <a href="{{ route('erp.agents-ia.demandes') }}" class="text-sm text-gray-400 hover:text-red-500">
        <i class="bi bi-arrow-left"></i> Demandes clients
    </a>
    <div class="flex items-center gap-3">
        <a href="{{ route('agents-ia.index') }}" target="_blank" rel="noopener" class="text-xs text-gray-400 hover:text-red-500">
            <i class="bi bi-box-arrow-up-right"></i> Voir la page publique
        </a>
        <button type="button" class="btn-primary text-sm" onclick="ouvrirAgent(null)">
            <i class="bi bi-plus-lg"></i> Nouvel agent
        </button>
    </div>
</div>

<div class="content-card">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Agent</th>
                    <th class="px-4 py-3 text-right font-semibold">Installation</th>
                    <th class="px-4 py-3 text-right font-semibold">Mensuel</th>
                    <th class="px-4 py-3 text-left font-semibold">Canaux</th>
                    <th class="px-4 py-3 text-center font-semibold">Demandes</th>
                    <th class="px-4 py-3 text-center font-semibold">Visible</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($agents as $a)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 flex items-center justify-center shrink-0">
                                    <i class="fas {{ $a->icone }}"></i>
                                </span>
                                <span>
                                    <span class="block font-semibold text-gray-800 dark:text-gray-100">{{ $a->nom }}</span>
                                    <span class="block text-xs text-gray-400">{{ $a->categorie }} &middot; /{{ $a->slug }}</span>
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            @if ($a->sur_devis)<span class="text-xs text-gray-400">Sur devis</span>
                            @else {{ $a->prix_installation !== null ? rtrim(rtrim(number_format((float) $a->prix_installation, 2, ',', ' '), '0'), ',').' '.$a->devise : '—' }} @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            @if ($a->sur_devis)<span class="text-xs text-gray-400">—</span>
                            @else {{ $a->prix_mensuel !== null ? rtrim(rtrim(number_format((float) $a->prix_mensuel, 2, ',', ' '), '0'), ',').' '.$a->devise : '—' }} @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($a->canaux ?? [] as $c)
                                    <span class="text-[10px] bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded px-1.5 py-0.5">{{ $c }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300 tabular-nums">{{ $a->demandes_count }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($a->actif)
                                <span class="text-xs font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-2 py-1">Oui</span>
                            @else
                                <span class="text-xs font-bold bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400 rounded px-2 py-1">Non</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button type="button" class="text-xs text-gray-400 hover:text-red-500 mr-2"
                                    onclick='ouvrirAgent(@json([
                                        "id" => $a->id, "nom" => $a->nom, "slug" => $a->slug,
                                        "categorie" => $a->categorie, "icone" => $a->icone,
                                        "description_courte" => $a->description_courte,
                                        "capacites" => implode("\n", $a->capacites ?? []),
                                        "canaux" => implode("\n", $a->canaux ?? []),
                                        "prix_installation" => $a->prix_installation,
                                        "prix_mensuel" => $a->prix_mensuel,
                                        "devise" => $a->devise, "sur_devis" => $a->sur_devis,
                                        "avertissement" => $a->avertissement,
                                        "actif" => $a->actif, "ordre" => $a->ordre,
                                    ]))'>
                                <i class="bi bi-pencil"></i> Modifier
                            </button>
                            <form method="POST" action="{{ route('erp.agents-ia.destroy', $a) }}" class="inline"
                                  onsubmit="return confirm('Retirer « {{ $a->nom }} » du catalogue ? Les demandes déjà passées gardent leur nom et leurs prix.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-gray-400 hover:text-red-500"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">Aucun agent au catalogue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Fiche d'édition --}}
<div id="modaleAgent" class="fixed inset-0 z-50 items-center justify-center p-4 bg-black/50" style="display:none">
    <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-800">
            <span class="font-semibold text-gray-800 dark:text-gray-100" id="modaleTitre">Nouvel agent</span>
            <button type="button" onclick="fermerAgent()" class="text-gray-400 hover:text-red-500"><i class="bi bi-x-lg"></i></button>
        </div>

        <form method="POST" id="formAgent" class="p-5 space-y-4">
            @csrf
            <input type="hidden" name="_method" id="agentMethod" value="POST">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Nom *</label>
                    <input type="text" name="nom" id="a_nom" required maxlength="120" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Identifiant d'URL</label>
                    <input type="text" name="slug" id="a_slug" maxlength="80" placeholder="laisser vide = déduit du nom" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Catégorie</label>
                    <input type="text" name="categorie" id="a_categorie" maxlength="60" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Icône Font Awesome</label>
                    <input type="text" name="icone" id="a_icone" maxlength="60" placeholder="fa-robot" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Description courte *</label>
                <textarea name="description_courte" id="a_desc" rows="2" required maxlength="400" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200"></textarea>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Capacités <span class="text-gray-300">— une par ligne</span></label>
                <textarea name="capacites" id="a_capacites" rows="6" maxlength="4000" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200"></textarea>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Canaux <span class="text-gray-300">— un par ligne</span></label>
                <textarea name="canaux" id="a_canaux" rows="3" maxlength="400" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Installation</label>
                    <input type="number" step="0.01" min="0" name="prix_installation" id="a_installation" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Mensuel</label>
                    <input type="number" step="0.01" min="0" name="prix_mensuel" id="a_mensuel" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Devise</label>
                    <input type="text" name="devise" id="a_devise" maxlength="8" placeholder="USD" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Avertissement affiché sur la fiche</label>
                <textarea name="avertissement" id="a_avert" rows="2" maxlength="1000" placeholder="Ex. : cet agent ne pose aucun diagnostic médical." class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200"></textarea>
            </div>

            <div class="flex items-center gap-5 flex-wrap">
                {{-- Champ caché avant la case : décochée, elle n'enverrait rien. --}}
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="hidden" name="sur_devis" value="0">
                    <input type="checkbox" name="sur_devis" id="a_devis" value="1"> Sur devis
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input type="hidden" name="actif" value="0">
                    <input type="checkbox" name="actif" id="a_actif" value="1"> Visible sur le site
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    Ordre
                    <input type="number" name="ordre" id="a_ordre" min="0" max="999" class="w-20 border border-gray-200 rounded-xl px-2 py-1 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </label>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primary text-sm flex-1">Enregistrer</button>
                <button type="button" onclick="fermerAgent()" class="btn-secondary text-sm">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script>
  var URL_CREER   = @json(route('erp.agents-ia.store'));
  var URL_MODIFIER= @json(route('erp.agents-ia.update', ['agent' => '__SLUG__']));

  function ouvrirAgent(a) {
    var f = document.getElementById('formAgent');
    document.getElementById('modaleTitre').textContent = a ? 'Modifier ' + a.nom : 'Nouvel agent';
    f.action = a ? URL_MODIFIER.replace('__SLUG__', a.slug) : URL_CREER;
    document.getElementById('agentMethod').value = a ? 'PUT' : 'POST';

    var v = {
      nom: '', slug: '', categorie: '', icone: 'fa-robot', description_courte: '',
      capacites: '', canaux: '', prix_installation: '', prix_mensuel: '',
      devise: 'USD', sur_devis: false, avertissement: '', actif: true, ordre: 0,
    };
    if (a) { for (var k in v) if (a[k] !== undefined && a[k] !== null) v[k] = a[k]; }

    document.getElementById('a_nom').value          = v.nom;
    document.getElementById('a_slug').value         = v.slug;
    document.getElementById('a_categorie').value    = v.categorie;
    document.getElementById('a_icone').value        = v.icone;
    document.getElementById('a_desc').value         = v.description_courte;
    document.getElementById('a_capacites').value    = v.capacites;
    document.getElementById('a_canaux').value       = v.canaux;
    document.getElementById('a_installation').value = v.prix_installation;
    document.getElementById('a_mensuel').value      = v.prix_mensuel;
    document.getElementById('a_devise').value       = v.devise;
    document.getElementById('a_avert').value        = v.avertissement;
    document.getElementById('a_devis').checked      = !!v.sur_devis;
    document.getElementById('a_actif').checked      = !!v.actif;
    document.getElementById('a_ordre').value        = v.ordre;

    document.getElementById('modaleAgent').style.display = 'flex';
  }

  function fermerAgent() {
    document.getElementById('modaleAgent').style.display = 'none';
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fermerAgent();
  });
</script>

@endsection

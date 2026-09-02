@extends('layouts.public')

@section('title', 'Fiche technique — Diagnostic commercial GOVIBE')
@section('description', "Fiche de diagnostic commercial et numérique remplie par les agents GOVIBE lors des visites d'organisations.")

@section('head')
<style>
  .ft-hero {
    background: linear-gradient(135deg, #0a0000 0%, #1a0004 55%, #050505 100%);
    padding: 72px 1.2rem 40px; text-align: center;
  }
  .ft-hero h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.6rem, 5vw, 2.4rem);
    color: #fff; margin: 0 0 .6rem; letter-spacing: .02em;
  }
  .ft-hero h1 span { color: #DC2626; }
  .ft-hero p { color: rgba(255,255,255,.66); font-size: .93rem; max-width: 46ch; margin: 0 auto; line-height: 1.65; }

  .ft-body { background: #f8fafc; padding: 1.5rem 1rem 4rem; }
  .ft-wrap { max-width: 780px; margin: 0 auto; }

  /* Barre d'état : brouillon restauré, compteur de sections. */
  .ft-etat {
    position: sticky; top: 0; z-index: 20;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: .7rem 1rem; margin-bottom: 1.2rem;
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap; font-size: .8rem;
  }
  .ft-etat-info { color: #64748b; }
  .ft-etat-info strong { color: #0f172a; }
  .ft-vider {
    border: 1px solid #e5e7eb; background: #fff; color: #64748b;
    font-size: .74rem; padding: .3rem .8rem; border-radius: 50px;
    cursor: pointer; font-family: inherit;
  }
  .ft-vider:hover { border-color: #DC2626; color: #DC2626; }

  .ft-sect {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
    margin-bottom: 1rem; overflow: hidden;
  }
  .ft-sect > summary {
    padding: 1rem 1.2rem; cursor: pointer; list-style: none;
    display: flex; align-items: center; gap: .8rem;
    font-family: 'Anton', sans-serif; font-weight: 400;
    font-size: 1rem; color: #0f172a; letter-spacing: .02em;
  }
  .ft-sect > summary::-webkit-details-marker { display: none; }
  .ft-sect > summary::after {
    content: '+'; margin-left: auto; color: #DC2626;
    font-size: 1.3rem; font-family: system-ui, sans-serif;
  }
  .ft-sect[open] > summary::after { content: '\2212'; }
  .ft-sect > summary:hover { background: #fafafa; }
  .ft-num {
    width: 26px; height: 26px; border-radius: 8px; flex-shrink: 0;
    background: #fef2f2; color: #DC2626; font-size: .78rem;
    display: flex; align-items: center; justify-content: center;
    font-family: 'DM Sans', sans-serif; font-weight: 700;
  }
  .ft-inner { padding: 0 1.2rem 1.3rem; border-top: 1px solid #f1f5f9; padding-top: 1.1rem; }

  .ft-g { margin-bottom: 1.1rem; }
  .ft-g > label, .ft-lbl {
    display: block; font-size: .82rem; font-weight: 600;
    color: #334155; margin-bottom: .4rem;
  }
  .ft-req { color: #DC2626; }
  .ft-g input[type=text], .ft-g input[type=tel], .ft-g input[type=email],
  .ft-g input[type=url], .ft-g input[type=number], .ft-g input[type=date],
  .ft-g select, .ft-g textarea {
    width: 100%; padding: .65rem .85rem; border: 1.5px solid #d1d5db;
    border-radius: 9px; font-size: 16px; /* 16px : évite le zoom auto sur iOS */
    color: #0f172a; background: #fff; font-family: inherit; box-sizing: border-box;
  }
  .ft-g textarea { resize: vertical; min-height: 80px; }
  .ft-g input:focus, .ft-g select:focus, .ft-g textarea:focus {
    outline: none; border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220,38,38,.12);
  }
  .ft-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; }
  .ft-hint { font-size: .76rem; color: #94a3b8; margin-top: .3rem; }

  /* Cases et radios en pastilles : plus faciles à toucher qu'une case seule. */
  .ft-opts { display: flex; flex-wrap: wrap; gap: .45rem; }
  .ft-opt {
    display: inline-flex; align-items: center; gap: .4rem;
    border: 1.5px solid #e5e7eb; border-radius: 50px;
    padding: .45rem .85rem; font-size: .85rem; color: #334155;
    cursor: pointer; background: #fff; user-select: none;
  }
  .ft-opt input { accent-color: #DC2626; margin: 0; }
  .ft-opt:has(input:checked) { border-color: #DC2626; background: #fef2f2; color: #b91c1c; font-weight: 600; }
  .ft-opt:focus-within { box-shadow: 0 0 0 3px rgba(220,38,38,.14); }

  /* Tableau des fonctions gérées */
  .ft-tab { width: 100%; border-collapse: collapse; font-size: .85rem; }
  .ft-tab th {
    text-align: center; font-size: .68rem; letter-spacing: .08em;
    text-transform: uppercase; color: #94a3b8; padding: .4rem;
  }
  .ft-tab th:first-child { text-align: left; }
  .ft-tab td { padding: .45rem .3rem; border-top: 1px solid #f1f5f9; }
  .ft-tab td:first-child { color: #334155; }
  .ft-tab td:not(:first-child) { text-align: center; }
  .ft-tab input { accent-color: #DC2626; width: 18px; height: 18px; }

  .ft-score { display: flex; flex-wrap: wrap; gap: .4rem; }
  .ft-score .ft-opt { flex-direction: column; align-items: flex-start; min-width: 5.5rem; }
  .ft-score .ft-opt b { font-size: 1.1rem; font-family: 'Anton', sans-serif; font-weight: 400; }
  .ft-score .ft-opt span { font-size: .7rem; color: #64748b; }

  .ft-alert {
    background: rgba(185,28,28,.07); border: 1px solid rgba(185,28,28,.28);
    color: #b91c1c; border-radius: 10px; padding: .9rem 1.1rem;
    font-size: .87rem; margin-bottom: 1.2rem;
  }
  .ft-alert ul { margin: .4rem 0 0 1.1rem; padding: 0; }

  .ft-envoyer {
    display: flex; align-items: center; justify-content: center; gap: .5rem;
    width: 100%; background: linear-gradient(135deg, #DC2626, #991b1b);
    color: #fff; font-family: 'Anton', sans-serif; font-weight: 400;
    letter-spacing: .05em; font-size: 1.05rem; padding: .95rem 1.5rem;
    border: none; border-radius: 12px; cursor: pointer; margin-top: .5rem;
  }
  .ft-envoyer:hover { opacity: .93; }

  @media (max-width: 560px) {
    .ft-2 { grid-template-columns: 1fr; gap: 0; }
    .ft-body { padding: 1rem .7rem 3rem; }
    .ft-inner { padding-left: .9rem; padding-right: .9rem; }
    .ft-sect > summary { padding: .9rem; font-size: .95rem; }
  }
</style>
@endsection

@section('content')

@php
  $types      = \App\Models\FicheTechnique::typesOrganisation();
  $secteurs   = \App\Models\FicheTechnique::secteurs();
  $tailles    = \App\Models\FicheTechnique::taillesEmployes();
  $fonctions  = \App\Models\FicheTechnique::fonctions();
  $actions    = \App\Models\FicheTechnique::prochainesActions();
  $solutions  = \App\Models\FicheTechnique::solutions();
  $niveaux    = \App\Models\FicheTechnique::niveauxScore();
  $potentiels = \App\Models\FicheTechnique::niveauxPotentiel();
  $questions  = \App\Models\FicheTechnique::questionsCochables();
  $gestion    = \App\Models\FicheTechnique::fonctionsGestion();
@endphp

<section class="ft-hero">
  <h1>Fiche <span>technique</span></h1>
  <p>Diagnostic commercial et numérique. À remplir pendant la visite, section par section.</p>
</section>

<section class="ft-body">
  <div class="ft-wrap">

    <div class="ft-etat">
      <span class="ft-etat-info" data-ft-etat>Les réponses sont gardées sur cet appareil au fur et à mesure.</span>
      <button type="button" class="ft-vider" data-ft-vider>Vider le brouillon</button>
    </div>

    @if ($errors->any())
      <div class="ft-alert">
        <strong>Corrigez les points suivants :</strong>
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('fiche-technique.store') }}" data-ft-form>
      @csrf

      {{-- 1 --}}
      <details class="ft-sect" open>
        <summary><span class="ft-num">1</span> Identification de l'organisation</summary>
        <div class="ft-inner">
          <div class="ft-g">
            <label for="nom_organisation">Nom de l'organisation <span class="ft-req">*</span></label>
            <input type="text" id="nom_organisation" name="nom_organisation" required value="{{ old('nom_organisation') }}">
          </div>
          <div class="ft-g">
            <label for="nom_commercial">Nom commercial</label>
            <input type="text" id="nom_commercial" name="nom_commercial" value="{{ old('nom_commercial') }}">
          </div>

          <div class="ft-g">
            <span class="ft-lbl">Type <span class="ft-req">*</span></span>
            <div class="ft-opts">
              @foreach ($types as $v => $l)
                <label class="ft-opt"><input type="radio" name="type_organisation" value="{{ $v }}" required @checked(old('type_organisation') === $v)> {{ $l }}</label>
              @endforeach
            </div>
          </div>

          <div class="ft-g">
            <span class="ft-lbl">Secteur d'activité</span>
            <div class="ft-opts">
              @foreach ($secteurs as $v => $l)
                <label class="ft-opt"><input type="radio" name="secteur" value="{{ $v }}" @checked(old('secteur') === $v)> {{ $l }}</label>
              @endforeach
            </div>
          </div>

          <div class="ft-2">
            <div class="ft-g"><label for="adresse">Adresse</label><input type="text" id="adresse" name="adresse" value="{{ old('adresse') }}"></div>
            <div class="ft-g"><label for="commune">Commune / Zone</label><input type="text" id="commune" name="commune" value="{{ old('commune') }}"></div>
          </div>
          <div class="ft-2">
            <div class="ft-g"><label for="telephone">Téléphone</label><input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}"></div>
            <div class="ft-g"><label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email') }}"></div>
          </div>
          <div class="ft-2">
            <div class="ft-g"><label for="r_site">Site web</label><input type="text" id="r_site" name="reponses[site_web_url]" value="{{ old('reponses.site_web_url') }}"></div>
            <div class="ft-g"><label for="r_fb">Facebook</label><input type="text" id="r_fb" name="reponses[facebook]" value="{{ old('reponses.facebook') }}"></div>
          </div>
          <div class="ft-2">
            <div class="ft-g"><label for="r_ig">Instagram</label><input type="text" id="r_ig" name="reponses[instagram]" value="{{ old('reponses.instagram') }}"></div>
            <div class="ft-g"><label for="r_wa">WhatsApp Business</label><input type="text" id="r_wa" name="reponses[whatsapp_business]" value="{{ old('reponses.whatsapp_business') }}"></div>
          </div>
          <div class="ft-2">
            <div class="ft-g"><label for="r_annee">Année de création</label><input type="number" id="r_annee" name="reponses[annee_creation]" min="1800" max="{{ date('Y') }}" value="{{ old('reponses.annee_creation') }}"></div>
            <div class="ft-g">
              <label for="taille_employes">Nombre d'employés</label>
              <select id="taille_employes" name="taille_employes">
                <option value="">—</option>
                @foreach ($tailles as $v => $l)<option value="{{ $v }}" @selected(old('taille_employes') === $v)>{{ $l }}</option>@endforeach
              </select>
            </div>
          </div>
        </div>
      </details>

      {{-- 2 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">2</span> Personne rencontrée</summary>
        <div class="ft-inner">
          <div class="ft-2">
            <div class="ft-g"><label for="contact_nom">Nom et prénom</label><input type="text" id="contact_nom" name="contact_nom" value="{{ old('contact_nom') }}"></div>
            <div class="ft-g">
              <label for="contact_fonction">Fonction</label>
              <select id="contact_fonction" name="contact_fonction">
                <option value="">—</option>
                @foreach ($fonctions as $v => $l)<option value="{{ $v }}" @selected(old('contact_fonction') === $v)>{{ $l }}</option>@endforeach
              </select>
            </div>
          </div>
          <div class="ft-2">
            <div class="ft-g"><label for="contact_telephone">Téléphone / WhatsApp</label><input type="tel" id="contact_telephone" name="contact_telephone" value="{{ old('contact_telephone') }}"></div>
            <div class="ft-g"><label for="contact_email">Email</label><input type="email" id="contact_email" name="contact_email" value="{{ old('contact_email') }}"></div>
          </div>

          <div class="ft-g">
            <span class="ft-lbl">Est-ce la personne qui décide des solutions numériques ?</span>
            <div class="ft-opts">
              <label class="ft-opt"><input type="radio" name="est_decideur" value="1" @checked(old('est_decideur') === '1')> Oui</label>
              <label class="ft-opt"><input type="radio" name="est_decideur" value="0" @checked(old('est_decideur') === '0')> Non</label>
              <label class="ft-opt"><input type="radio" name="est_decideur" value="partiel" @checked(old('est_decideur') === 'partiel')> Partiellement</label>
            </div>
          </div>

          <div class="ft-2">
            <div class="ft-g"><label for="decideur_nom">Nom du décideur</label><input type="text" id="decideur_nom" name="decideur_nom" value="{{ old('decideur_nom') }}"></div>
            <div class="ft-g"><label for="decideur_contact">Contact du décideur</label><input type="text" id="decideur_contact" name="decideur_contact" value="{{ old('decideur_contact') }}"></div>
          </div>

          <div class="ft-g">
            <span class="ft-lbl">Meilleur moment pour le contacter</span>
            <div class="ft-opts">
              @foreach (['Matin', 'Midi', 'Après-midi', 'Soir'] as $m)
                <label class="ft-opt"><input type="radio" name="reponses[moment_contact]" value="{{ $m }}" @checked(old('reponses.moment_contact') === $m)> {{ $m }}</label>
              @endforeach
            </div>
          </div>
        </div>
      </details>

      {{-- 3 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">3</span> Comprendre l'activité</summary>
        <div class="ft-inner">
          <div class="ft-g"><label for="q_act">Expliquez brièvement votre activité</label><textarea id="q_act" name="reponses[description_activite]">{{ old('reponses.description_activite') }}</textarea></div>
          <div class="ft-g"><label for="q_prod">Principaux produits ou services</label><textarea id="q_prod" name="reponses[produits_services]">{{ old('reponses.produits_services') }}</textarea></div>
          <div class="ft-g"><label for="q_cli">Principaux clients</label><textarea id="q_cli" name="reponses[principaux_clients]">{{ old('reponses.principaux_clients') }}</textarea></div>
          <div class="ft-g"><label for="q_nb">Clients servis par mois (approximatif)</label><input type="text" id="q_nb" name="reponses[clients_par_mois]" value="{{ old('reponses.clients_par_mois') }}"></div>

          @foreach ($questions['activite']['champs'] as $cle => $champ)
            <div class="ft-g">
              <span class="ft-lbl">{{ $champ['label'] }}</span>
              <div class="ft-opts">
                @foreach ($champ['options'] as $opt)
                  <label class="ft-opt"><input type="checkbox" name="reponses[{{ $cle }}][]" value="{{ $opt }}" @checked(in_array($opt, (array) old("reponses.$cle", []), true))> {{ $opt }}</label>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </details>

      {{-- 4 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">4</span> Diagnostic numérique</summary>
        <div class="ft-inner">
          <div class="ft-g">
            <span class="ft-lbl">Avez-vous un site web ?</span>
            <div class="ft-opts">
              <label class="ft-opt"><input type="radio" name="reponses[a_site_web]" value="Oui" @checked(old('reponses.a_site_web') === 'Oui')> Oui</label>
              <label class="ft-opt"><input type="radio" name="reponses[a_site_web]" value="Non" @checked(old('reponses.a_site_web') === 'Non')> Non</label>
            </div>
          </div>
          <div class="ft-2">
            <div class="ft-g">
              <span class="ft-lbl">Le site est-il fonctionnel ?</span>
              <div class="ft-opts">
                <label class="ft-opt"><input type="radio" name="reponses[site_fonctionnel]" value="Oui" @checked(old('reponses.site_fonctionnel') === 'Oui')> Oui</label>
                <label class="ft-opt"><input type="radio" name="reponses[site_fonctionnel]" value="Non" @checked(old('reponses.site_fonctionnel') === 'Non')> Non</label>
              </div>
            </div>
            <div class="ft-g">
              <span class="ft-lbl">Mis à jour régulièrement ?</span>
              <div class="ft-opts">
                <label class="ft-opt"><input type="radio" name="reponses[site_a_jour]" value="Oui" @checked(old('reponses.site_a_jour') === 'Oui')> Oui</label>
                <label class="ft-opt"><input type="radio" name="reponses[site_a_jour]" value="Non" @checked(old('reponses.site_a_jour') === 'Non')> Non</label>
              </div>
            </div>
          </div>

          @foreach ($questions['numerique']['champs'] as $cle => $champ)
            <div class="ft-g">
              <span class="ft-lbl">{{ $champ['label'] }}</span>
              <div class="ft-opts">
                @foreach ($champ['options'] as $opt)
                  <label class="ft-opt"><input type="checkbox" name="reponses[{{ $cle }}][]" value="{{ $opt }}" @checked(in_array($opt, (array) old("reponses.$cle", []), true))> {{ $opt }}</label>
                @endforeach
              </div>
            </div>
          @endforeach

          <div class="ft-g">
            <span class="ft-lbl">Qui gère les réseaux sociaux ?</span>
            <div class="ft-opts">
              @foreach (['Employé interne', 'Agence', 'Direction', 'Personne'] as $opt)
                <label class="ft-opt"><input type="radio" name="reponses[gestion_reseaux]" value="{{ $opt }}" @checked(old('reponses.gestion_reseaux') === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          </div>
        </div>
      </details>

      {{-- 5 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">5</span> Gestion de l'entreprise</summary>
        <div class="ft-inner">
          <p class="ft-hint" style="margin-bottom:.8rem">Pour chaque fonction : dispose d'un système, n'en a pas, ou le fait à la main.</p>
          <div style="overflow-x:auto">
            <table class="ft-tab">
              <thead><tr><th>Fonction</th><th>Oui</th><th>Non</th><th>Manuel</th></tr></thead>
              <tbody>
                @foreach ($gestion as $cle => $libelle)
                  <tr>
                    <td>{{ $libelle }}</td>
                    @foreach (['oui', 'non', 'manuel'] as $etat)
                      <td><input type="radio" name="reponses[gestion][{{ $cle }}]" value="{{ $etat }}" @checked(old("reponses.gestion.$cle") === $etat)></td>
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="ft-g" style="margin-top:1.2rem"><label for="q_temps">Quelle tâche fait perdre le plus de temps ?</label><textarea id="q_temps" name="reponses[tache_chronophage]">{{ old('reponses.tache_chronophage') }}</textarea></div>
          <div class="ft-g"><label for="q_err">Quelle tâche crée le plus d'erreurs ?</label><textarea id="q_err" name="reponses[tache_erreurs]">{{ old('reponses.tache_erreurs') }}</textarea></div>
          <div class="ft-g"><label for="q_info">Quelle information est la plus difficile à retrouver ?</label><textarea id="q_info" name="reponses[info_difficile]">{{ old('reponses.info_difficile') }}</textarea></div>
        </div>
      </details>

      {{-- 6 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">6</span> Identifier les problèmes</summary>
        <div class="ft-inner">
          <span class="ft-lbl">Si vous pouviez automatiser 3 choses aujourd'hui, lesquelles ?</span>
          @for ($i = 1; $i <= 3; $i++)
            <div class="ft-g"><input type="text" name="reponses[automatiser_{{ $i }}]" placeholder="{{ $i }}." value="{{ old("reponses.automatiser_$i") }}" aria-label="Automatisation {{ $i }}"></div>
          @endfor
          <div class="ft-g"><label for="q_pb">Plus grand problème opérationnel</label><textarea id="q_pb" name="reponses[probleme_principal]">{{ old('reponses.probleme_principal') }}</textarea></div>
          <div class="ft-g"><label for="q_frein">Qu'est-ce qui empêche de travailler plus efficacement ?</label><textarea id="q_frein" name="reponses[frein_efficacite]">{{ old('reponses.frein_efficacite') }}</textarea></div>
          <div class="ft-g"><label for="q_amel">À améliorer dans les 6 à 12 mois</label><textarea id="q_amel" name="reponses[amelioration_12_mois]">{{ old('reponses.amelioration_12_mois') }}</textarea></div>
        </div>
      </details>

      {{-- 7 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">7</span> Paiement et vente</summary>
        <div class="ft-inner">
          @foreach ($questions['paiement']['champs'] as $cle => $champ)
            <div class="ft-g">
              <span class="ft-lbl">{{ $champ['label'] }}</span>
              <div class="ft-opts">
                @foreach ($champ['options'] as $opt)
                  <label class="ft-opt"><input type="checkbox" name="reponses[{{ $cle }}][]" value="{{ $opt }}" @checked(in_array($opt, (array) old("reponses.$cle", []), true))> {{ $opt }}</label>
                @endforeach
              </div>
            </div>
          @endforeach

          @foreach ([
            'besoin_pos' => ["Besoin d'un système POS ?", ['Oui', 'Non', 'Peut-être']],
            'suivi_ventes' => ['Besoin de suivre les ventes quotidiennement ?', ['Oui', 'Non']],
            'gestion_stocks_besoin' => ['Besoin de gérer les stocks ?', ['Oui', 'Non']],
            'plusieurs_points_vente' => ['Plusieurs points de vente ?', ['Oui', 'Non']],
          ] as $cle => [$label, $opts])
            <div class="ft-g">
              <span class="ft-lbl">{{ $label }}</span>
              <div class="ft-opts">
                @foreach ($opts as $opt)
                  <label class="ft-opt"><input type="radio" name="reponses[{{ $cle }}]" value="{{ $opt }}" @checked(old("reponses.$cle") === $opt)> {{ $opt }}</label>
                @endforeach
              </div>
            </div>
          @endforeach

          <div class="ft-g"><label for="q_pv">Nombre de points de vente</label><input type="number" id="q_pv" name="reponses[nombre_points_vente]" min="0" value="{{ old('reponses.nombre_points_vente') }}"></div>
        </div>
      </details>

      {{-- 8 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">8</span> TAGTOA — NFC et QR</summary>
        <div class="ft-inner">
          <p class="ft-hint" style="margin-bottom:.8rem">Identifier le besoin avant de présenter la solution.</p>

          <div class="ft-g">
            <span class="ft-lbl">Ont-ils déjà utilisé QR Code ou NFC ?</span>
            <div class="ft-opts">
              <label class="ft-opt"><input type="radio" name="reponses[deja_qr_nfc]" value="Oui" @checked(old('reponses.deja_qr_nfc') === 'Oui')> Oui</label>
              <label class="ft-opt"><input type="radio" name="reponses[deja_qr_nfc]" value="Non" @checked(old('reponses.deja_qr_nfc') === 'Non')> Non</label>
            </div>
          </div>

          @foreach ($questions['tagtoa']['champs'] as $cle => $champ)
            <div class="ft-g">
              <span class="ft-lbl">{{ $champ['label'] }}</span>
              <div class="ft-opts">
                @foreach ($champ['options'] as $opt)
                  <label class="ft-opt"><input type="checkbox" name="reponses[{{ $cle }}][]" value="{{ $opt }}" @checked(in_array($opt, (array) old("reponses.$cle", []), true))> {{ $opt }}</label>
                @endforeach
              </div>
            </div>
          @endforeach

          <div class="ft-g">
            <span class="ft-lbl">Cartes professionnelles imprimées ?</span>
            <div class="ft-opts">
              <label class="ft-opt"><input type="radio" name="reponses[cartes_imprimees]" value="Oui" @checked(old('reponses.cartes_imprimees') === 'Oui')> Oui</label>
              <label class="ft-opt"><input type="radio" name="reponses[cartes_imprimees]" value="Non" @checked(old('reponses.cartes_imprimees') === 'Non')> Non</label>
            </div>
          </div>
          <div class="ft-g"><label for="q_nfc">Combien de personnes pourraient utiliser une carte NFC ?</label><input type="number" id="q_nfc" name="reponses[nb_cartes_nfc]" min="0" value="{{ old('reponses.nb_cartes_nfc') }}"></div>
          <div class="ft-g">
            <span class="ft-lbl">Intéressé par un accès instantané à leurs informations ?</span>
            <div class="ft-opts">
              @foreach (['Oui', 'Non', 'À discuter'] as $opt)
                <label class="ft-opt"><input type="radio" name="reponses[interet_tagtoa]" value="{{ $opt }}" @checked(old('reponses.interet_tagtoa') === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          </div>
        </div>
      </details>

      {{-- 9 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">9</span> KLASYO — formation</summary>
        <div class="ft-inner">
          <div class="ft-g">
            <span class="ft-lbl">Organisent-ils des formations pour leurs employés ?</span>
            <div class="ft-opts">
              <label class="ft-opt"><input type="radio" name="reponses[organise_formations]" value="Oui" @checked(old('reponses.organise_formations') === 'Oui')> Oui</label>
              <label class="ft-opt"><input type="radio" name="reponses[organise_formations]" value="Non" @checked(old('reponses.organise_formations') === 'Non')> Non</label>
            </div>
          </div>
          <div class="ft-g">
            <span class="ft-lbl">À quelle fréquence ?</span>
            <div class="ft-opts">
              @foreach (['Chaque semaine', 'Chaque mois', 'Trimestrielle', 'Occasionnelle', 'Jamais'] as $opt)
                <label class="ft-opt"><input type="radio" name="reponses[frequence_formations]" value="{{ $opt }}" @checked(old('reponses.frequence_formations') === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          </div>

          @foreach ($questions['klasyo']['champs'] as $cle => $champ)
            <div class="ft-g">
              <span class="ft-lbl">{{ $champ['label'] }}</span>
              <div class="ft-opts">
                @foreach ($champ['options'] as $opt)
                  <label class="ft-opt"><input type="checkbox" name="reponses[{{ $cle }}][]" value="{{ $opt }}" @checked(in_array($opt, (array) old("reponses.$cle", []), true))> {{ $opt }}</label>
                @endforeach
              </div>
            </div>
          @endforeach

          <div class="ft-g">
            <span class="ft-lbl">Intéressé par une plateforme de formation numérique ?</span>
            <div class="ft-opts">
              @foreach (['Oui', 'Non', 'À discuter'] as $opt)
                <label class="ft-opt"><input type="radio" name="reponses[interet_klasyo]" value="{{ $opt }}" @checked(old('reponses.interet_klasyo') === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          </div>
        </div>
      </details>

      {{-- 10 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">10</span> Écoles</summary>
        <div class="ft-inner">
          <p class="ft-hint" style="margin-bottom:.8rem">À remplir uniquement pour une école ou une université.</p>
          <div class="ft-2">
            <div class="ft-g"><label for="q_el">Nombre d'élèves</label><input type="number" id="q_el" name="reponses[nb_eleves]" min="0" value="{{ old('reponses.nb_eleves') }}"></div>
            <div class="ft-g"><label for="q_en">Nombre d'enseignants</label><input type="number" id="q_en" name="reponses[nb_enseignants]" min="0" value="{{ old('reponses.nb_enseignants') }}"></div>
          </div>
          <div class="ft-g">
            <span class="ft-lbl">Utilisent-ils une plateforme éducative ?</span>
            <div class="ft-opts">
              <label class="ft-opt"><input type="radio" name="reponses[a_plateforme_educative]" value="Oui" @checked(old('reponses.a_plateforme_educative') === 'Oui')> Oui</label>
              <label class="ft-opt"><input type="radio" name="reponses[a_plateforme_educative]" value="Non" @checked(old('reponses.a_plateforme_educative') === 'Non')> Non</label>
            </div>
          </div>

          @foreach ($questions['ecole']['champs'] as $cle => $champ)
            <div class="ft-g">
              <span class="ft-lbl">{{ $champ['label'] }}</span>
              <div class="ft-opts">
                @foreach ($champ['options'] as $opt)
                  <label class="ft-opt"><input type="checkbox" name="reponses[{{ $cle }}][]" value="{{ $opt }}" @checked(in_array($opt, (array) old("reponses.$cle", []), true))> {{ $opt }}</label>
                @endforeach
              </div>
            </div>
          @endforeach

          <div class="ft-g"><label for="q_part">Participants par formation (approximatif)</label><input type="number" id="q_part" name="reponses[nb_participants_formation]" min="0" value="{{ old('reponses.nb_participants_formation') }}"></div>
        </div>
      </details>

      {{-- 11 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">11</span> Budget et intérêt commercial</summary>
        <div class="ft-inner">
          <div class="ft-g">
            <span class="ft-lbl">Budget consacré aux outils numériques ?</span>
            <div class="ft-opts">
              @foreach (['Oui', 'Non', 'Je ne sais pas'] as $opt)
                <label class="ft-opt"><input type="radio" name="reponses[a_budget]" value="{{ $opt }}" @checked(old('reponses.a_budget') === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          </div>
          <div class="ft-g">
            <span class="ft-lbl">Budget approximatif</span>
            <div class="ft-opts">
              @foreach (['< 5 000 GDS/mois', '5 000–15 000 GDS', '15 000–50 000 GDS', '50 000+ GDS', 'À déterminer'] as $opt)
                <label class="ft-opt"><input type="radio" name="reponses[budget_approx]" value="{{ $opt }}" @checked(old('reponses.budget_approx') === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          </div>
          <div class="ft-g">
            <span class="ft-lbl">Prêt à investir pour gagner du temps ou augmenter ses revenus ?</span>
            <div class="ft-opts">
              @foreach (['Oui', 'Non', 'À discuter'] as $opt)
                <label class="ft-opt"><input type="radio" name="reponses[pret_investir]" value="{{ $opt }}" @checked(old('reponses.pret_investir') === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          </div>
        </div>
      </details>

      {{-- 12 --}}
      <details class="ft-sect" open>
        <summary><span class="ft-num">12</span> Priorité du prospect</summary>
        <div class="ft-inner">
          <div class="ft-g">
            <span class="ft-lbl">Besoin numérique <span class="ft-req">*</span></span>
            <div class="ft-score">
              @foreach ($niveaux as $n => $l)
                <label class="ft-opt"><input type="radio" name="score_besoin" value="{{ $n }}" required @checked(old('score_besoin', '0') == $n)> <b>{{ $n }}</b><span>{{ $l }}</span></label>
              @endforeach
            </div>
          </div>
          <div class="ft-g">
            <span class="ft-lbl">Potentiel commercial <span class="ft-req">*</span></span>
            <div class="ft-score">
              @foreach ($potentiels as $n => $l)
                <label class="ft-opt"><input type="radio" name="score_potentiel" value="{{ $n }}" required @checked(old('score_potentiel', '0') == $n)> <b>{{ $n }}</b><span>{{ $l }}</span></label>
              @endforeach
            </div>
          </div>
          <div class="ft-g">
            <span class="ft-lbl">Rendez-vous possible ?</span>
            <div class="ft-opts">
              <label class="ft-opt"><input type="radio" name="rendez_vous_possible" value="1" @checked(old('rendez_vous_possible') === '1')> Oui</label>
              <label class="ft-opt"><input type="radio" name="rendez_vous_possible" value="0" @checked(old('rendez_vous_possible') === '0')> Non</label>
              <label class="ft-opt"><input type="radio" name="rendez_vous_possible" value="relancer" @checked(old('rendez_vous_possible') === 'relancer')> À relancer</label>
            </div>
          </div>
          <div class="ft-g">
            <span class="ft-lbl">Solution potentielle</span>
            <div class="ft-opts">
              @foreach ($solutions as $v => $l)
                <label class="ft-opt"><input type="checkbox" name="reponses[solutions][]" value="{{ $l }}" @checked(in_array($l, (array) old('reponses.solutions', []), true))> {{ $l }}</label>
              @endforeach
            </div>
          </div>
        </div>
      </details>

      {{-- 13 --}}
      <details class="ft-sect">
        <summary><span class="ft-num">13</span> Observation de l'agent</summary>
        <div class="ft-inner">
          <div class="ft-g"><label for="q_niv">Niveau de digitalisation observé</label><input type="text" id="q_niv" name="reponses[niveau_digitalisation]" value="{{ old('reponses.niveau_digitalisation') }}"></div>
          <div class="ft-g"><label for="q_out">Outils utilisés</label><input type="text" id="q_out" name="reponses[outils_utilises]" value="{{ old('reponses.outils_utilises') }}"></div>
          <div class="ft-g"><label for="q_pbo">Problème principal observé</label><textarea id="q_pbo" name="reponses[probleme_observe]">{{ old('reponses.probleme_observe') }}</textarea></div>
          <div class="ft-g"><label for="q_opp">Opportunité GOVIBE</label><textarea id="q_opp" name="reponses[opportunite_govibe]">{{ old('reponses.opportunite_govibe') }}</textarea></div>
          <div class="ft-g"><label for="observation_agent">Autres observations</label><textarea id="observation_agent" name="observation_agent">{{ old('observation_agent') }}</textarea></div>
          <div class="ft-2">
            <div class="ft-g">
              <span class="ft-lbl">Photos autorisées ?</span>
              <div class="ft-opts">
                <label class="ft-opt"><input type="radio" name="reponses[photos_autorisees]" value="Oui" @checked(old('reponses.photos_autorisees') === 'Oui')> Oui</label>
                <label class="ft-opt"><input type="radio" name="reponses[photos_autorisees]" value="Non" @checked(old('reponses.photos_autorisees') === 'Non')> Non</label>
              </div>
            </div>
            <div class="ft-g">
              <span class="ft-lbl">Documents disponibles ?</span>
              <div class="ft-opts">
                <label class="ft-opt"><input type="radio" name="reponses[documents_disponibles]" value="Oui" @checked(old('reponses.documents_disponibles') === 'Oui')> Oui</label>
                <label class="ft-opt"><input type="radio" name="reponses[documents_disponibles]" value="Non" @checked(old('reponses.documents_disponibles') === 'Non')> Non</label>
              </div>
            </div>
          </div>
        </div>
      </details>

      {{-- 14 --}}
      <details class="ft-sect" open>
        <summary><span class="ft-num">14</span> Prochaine action</summary>
        <div class="ft-inner">
          <div class="ft-g">
            <label for="prochaine_action">Action recommandée</label>
            <select id="prochaine_action" name="prochaine_action">
              <option value="">—</option>
              @foreach ($actions as $v => $l)<option value="{{ $v }}" @selected(old('prochaine_action') === $v)>{{ $l }}</option>@endforeach
            </select>
          </div>
          <div class="ft-2">
            <div class="ft-g"><label for="date_relance">Date de relance</label><input type="date" id="date_relance" name="date_relance" value="{{ old('date_relance') }}"></div>
            <div class="ft-g"><label for="responsable_assigne">Responsable GOVIBE à assigner</label><input type="text" id="responsable_assigne" name="responsable_assigne" value="{{ old('responsable_assigne') }}"></div>
          </div>
          <div class="ft-g">
            <label for="agent">Agent qui remplit cette fiche <span class="ft-req">*</span></label>
            <input type="text" id="agent" name="agent" required value="{{ old('agent') }}" placeholder="Votre nom">
            <p class="ft-hint">Ce nom identifie vos fiches dans l'ERP.</p>
          </div>

          <button type="submit" class="ft-envoyer">Enregistrer la fiche</button>
        </div>
      </details>

    </form>
  </div>
</section>

<script>
(function () {
  var form = document.querySelector('[data-ft-form]');
  if (!form) return;

  var CLE = 'govibe_fiche_brouillon';
  var etat = document.querySelector('[data-ft-etat]');
  var envoye = false;

  // Le stockage local peut être refusé (navigation privée, réglages) :
  // le formulaire doit rester utilisable sans lui.
  function dispo() {
    try { localStorage.setItem('__t', '1'); localStorage.removeItem('__t'); return true; }
    catch (e) { return false; }
  }
  if (!dispo()) {
    if (etat) etat.textContent = "Sauvegarde locale indisponible sur cet appareil. Envoyez la fiche avant de fermer la page.";
    return;
  }

  function champs() {
    return form.querySelectorAll('input:not([type=hidden]):not([type=submit]), select, textarea');
  }

  function enregistrer() {
    if (envoye) return;
    var donnees = {};
    champs().forEach(function (c) {
      if (!c.name) return;
      if (c.type === 'checkbox' || c.type === 'radio') {
        if (c.checked) (donnees[c.name] = donnees[c.name] || []).push(c.value);
      } else if (c.value) {
        donnees[c.name] = c.value;
      }
    });
    try {
      localStorage.setItem(CLE, JSON.stringify({ d: donnees, t: Date.now() }));
      if (etat) etat.innerHTML = '<strong>Brouillon gardé</strong> sur cet appareil — ' + new Date().toLocaleTimeString('fr-FR');
    } catch (e) { /* quota plein : on n'interrompt pas la saisie */ }
  }

  function restaurer() {
    var brut;
    try { brut = localStorage.getItem(CLE); } catch (e) { return; }
    if (!brut) return;

    var paquet;
    try { paquet = JSON.parse(brut); } catch (e) { return; }
    var donnees = paquet.d || {};

    // Un formulaire renvoyé en erreur a déjà ses valeurs : ne pas les écraser.
    if (form.querySelector('[name=nom_organisation]').value) return;

    champs().forEach(function (c) {
      if (!c.name || !(c.name in donnees)) return;
      var v = donnees[c.name];
      if (c.type === 'checkbox' || c.type === 'radio') {
        c.checked = Array.isArray(v) && v.indexOf(c.value) !== -1;
      } else {
        c.value = v;
      }
    });

    if (etat) {
      etat.innerHTML = '<strong>Brouillon restauré</strong> — ' + new Date(paquet.t).toLocaleString('fr-FR');
    }
  }

  restaurer();
  form.addEventListener('input', enregistrer);
  form.addEventListener('change', enregistrer);

  // La fiche est partie : le brouillon n'a plus lieu d'être.
  form.addEventListener('submit', function () {
    envoye = true;
    try { localStorage.removeItem(CLE); } catch (e) {}
  });

  var vider = document.querySelector('[data-ft-vider]');
  if (vider) {
    vider.addEventListener('click', function () {
      if (!window.confirm('Vider le brouillon et repartir d\'une fiche vierge ?')) return;
      try { localStorage.removeItem(CLE); } catch (e) {}
      window.location.reload();
    });
  }
})();
</script>

@endsection

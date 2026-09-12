@extends('layouts.public')

@section('title', 'Commander '.$plan->nom.' | GOVIBE Innovation Hub')
@section('description', 'Commandez l\'offre '.$plan->nom.' ('.$presentation['cle'].') chez GOVIBE Innovation Hub.')

@section('head')
<style>
  .cm { --cm-rouge:#DC2626; --cm-sombre:#991b1b; --cm-encre:#172033;
        --cm-gris:#667085; --cm-fond:#f7f8fa; --cm-trait:#e4e7ec; }

  .cm-tete { background:linear-gradient(135deg,var(--cm-sombre) 0%,var(--cm-rouge) 100%); color:#fff; }
  .cm-tete-in { max-width:1040px; margin:auto; padding:36px 16px 30px; }
  .cm-retour { color:rgba(255,255,255,.85); font-size:13px; text-decoration:none; display:inline-flex; gap:7px; align-items:center; }
  .cm-tete h1 { font-family:'Anton',sans-serif; font-weight:400; font-size:clamp(25px,5vw,38px); margin:12px 0 0; letter-spacing:.5px; }
  .cm-tete p { margin:8px 0 0; font-size:14.5px; color:rgba(255,255,255,.88); }

  .cm-corps { background:var(--cm-fond); padding:26px 0 48px; }
  .cm-wrap { max-width:1040px; margin:auto; padding:0 16px; display:grid; gap:20px; grid-template-columns:minmax(0,1fr) 320px; align-items:start; }
  @media (max-width:900px) {
    .cm-wrap { grid-template-columns:1fr; }
    /* Le récapitulatif passe au-dessus du formulaire sur mobile. */
    .cm-cote { order:-1; }
  }

  .cm-bloc { background:#fff; border:1px solid var(--cm-trait); border-radius:14px; padding:20px; margin-bottom:16px; }
  .cm-bloc-titre { display:flex; align-items:center; gap:9px; font-size:15px; font-weight:800; color:var(--cm-encre); margin:0 0 16px; }
  .cm-bloc-titre i { color:var(--cm-rouge); }
  .cm-bloc-titre span.cm-num {
    width:22px; height:22px; border-radius:50%; background:var(--cm-rouge); color:#fff;
    font-size:12px; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto;
  }

  .cm-champ { margin-bottom:16px; }
  .cm-champ:last-child { margin-bottom:0; }
  .cm-champ label { display:block; font-size:13.5px; font-weight:700; color:var(--cm-encre); margin-bottom:6px; }
  .cm-req { color:var(--cm-rouge); }
  .cm-aide { display:block; font-weight:500; font-size:12.5px; color:var(--cm-gris); margin-top:2px; }
  /* 16px : en dessous, iOS zoome à la mise au point du champ. */
  .cm-champ input[type=text], .cm-champ input[type=email], .cm-champ input[type=tel],
  .cm-champ select, .cm-champ textarea {
    width:100%; font:inherit; font-size:16px; color:var(--cm-encre);
    border:1px solid var(--cm-trait); border-radius:10px; padding:11px 13px; background:#fff;
  }
  .cm-champ textarea { min-height:92px; resize:vertical; }
  .cm-champ input:focus, .cm-champ select:focus, .cm-champ textarea:focus {
    outline:2px solid rgba(220,38,38,.35); outline-offset:1px; border-color:var(--cm-rouge);
  }
  .cm-erreur { color:var(--cm-rouge); font-size:12.5px; margin:5px 0 0; font-weight:600; }

  .cm-choix { display:grid; gap:9px; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); }
  .cm-opt { position:relative; cursor:pointer; }
  .cm-opt input { position:absolute; opacity:0; width:0; height:0; }
  .cm-opt > span {
    display:flex; align-items:center; gap:10px; min-height:52px;
    border:1px solid var(--cm-trait); border-radius:10px; padding:10px 13px;
    font-size:13.5px; font-weight:600; color:var(--cm-encre); background:#fff; height:100%;
  }
  .cm-opt input:checked + span { border-color:var(--cm-rouge); background:#fef2f2; box-shadow:inset 0 0 0 1px var(--cm-rouge); }
  .cm-opt input:focus-visible + span { outline:2px solid rgba(220,38,38,.4); outline-offset:2px; }
  .cm-opt img { width:30px; height:30px; object-fit:contain; flex:0 0 auto; }
  .cm-opt-init {
    width:30px; height:30px; border-radius:8px; background:var(--cm-fond); color:var(--cm-gris);
    font-size:11px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto;
  }
  .cm-opt-total { display:block; font-weight:800; color:var(--cm-sombre); font-size:13px; margin-top:2px; }

  .cm-coord { display:none; margin-top:14px; background:var(--cm-fond); border:1px solid var(--cm-trait); border-radius:12px; padding:14px 16px; }
  .cm-coord.cm-ouvert { display:block; }
  .cm-coord-tete { margin:0 0 10px; font-size:14px; font-weight:800; color:var(--cm-encre); }
  .cm-coord-ligne { display:flex; justify-content:space-between; gap:12px; font-size:13px; padding:5px 0; border-bottom:1px dashed var(--cm-trait); }
  .cm-coord-ligne:last-child { border-bottom:0; }
  .cm-coord-ligne span { color:var(--cm-gris); }
  .cm-coord-valeur { font-size:16px; font-weight:800; color:var(--cm-encre); word-break:break-all; }
  .cm-coord img { max-width:190px; margin-top:10px; border-radius:10px; border:1px solid var(--cm-trait); }
  .cm-coord-texte { font-size:13px; line-height:1.6; color:var(--cm-gris); margin:10px 0 0; white-space:pre-line; }
  .cm-coord-auto { font-size:13px; line-height:1.6; color:#047857; background:#ecfdf5; border-radius:9px; padding:10px 12px; margin:10px 0 0; }

  .cm-depot {
    display:flex; flex-direction:column; align-items:center; gap:3px; text-align:center; cursor:pointer;
    border:1.5px dashed var(--cm-trait); border-radius:12px; padding:20px 16px; background:#fff;
  }
  .cm-depot i { font-size:22px; color:var(--cm-rouge); }
  .cm-depot strong { font-size:14px; color:var(--cm-encre); }
  .cm-depot small { font-size:12px; color:var(--cm-gris); }
  .cm-depot input { position:absolute; width:1px; height:1px; opacity:0; }
  .cm-apercu { display:none; margin-top:10px; }
  .cm-apercu.cm-ouvert { display:block; }
  .cm-apercu img { max-width:100%; border-radius:10px; border:1px solid var(--cm-trait); }
  .cm-fichier { display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:12.5px; color:var(--cm-gris); margin-top:7px; }
  .cm-retirer { border:0; background:transparent; color:var(--cm-rouge); font:inherit; font-size:12.5px; font-weight:700; cursor:pointer; text-decoration:underline; }

  /* ── Récapitulatif ── */
  .cm-cote { position:sticky; top:14px; }
  .cm-recap { background:#fff; border:1px solid var(--cm-trait); border-radius:14px; padding:20px; }
  .cm-recap h2 { margin:0 0 14px; font-size:15px; font-weight:800; color:var(--cm-encre); }
  .cm-recap-ligne { display:flex; justify-content:space-between; gap:10px; font-size:13.5px; padding:7px 0; color:var(--cm-gris); }
  .cm-recap-ligne strong { color:var(--cm-encre); text-align:right; }
  .cm-recap-total { border-top:1px solid var(--cm-trait); margin-top:10px; padding-top:14px; }
  .cm-recap-total span { font-size:12.5px; color:var(--cm-gris); display:block; }
  .cm-recap-total strong { font-family:'Anton',sans-serif; font-weight:400; font-size:30px; color:var(--cm-encre); letter-spacing:.5px; display:block; margin-top:3px; }
  .cm-recap-equiv { font-size:12.5px; color:var(--cm-gris); margin:4px 0 0; }
  .cm-envoyer {
    width:100%; margin-top:16px; border:0; cursor:pointer; font:inherit; font-size:15px; font-weight:800;
    background:var(--cm-rouge); color:#fff; border-radius:11px; padding:14px 16px;
  }
  .cm-envoyer:disabled { opacity:.6; cursor:progress; }
  .cm-recap-note { font-size:12px; color:var(--cm-gris); line-height:1.6; margin:12px 0 0; }

  .cm-alerte { background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:12px; padding:13px 15px; font-size:13.5px; line-height:1.65; }
</style>
@endsection

@section('content')
@php
  $formater = fn ($v) => floor($v) == $v
      ? number_format($v, 0, ',', ' ')
      : number_format($v, 2, ',', ' ');
  $origines = \App\Models\CommandeAbonnement::originesDomaine();
@endphp

<div class="cm">

  <section class="cm-tete">
    <div class="cm-tete-in">
      <a href="{{ route('abonnements.service', $segment) }}" class="cm-retour">
        <i class="fas fa-arrow-left"></i> Retour aux offres {{ mb_strtolower($presentation['cle']) }}
      </a>
      <h1>{{ $plan->nom }}</h1>
      <p>{{ $presentation['cle'] }} · Facturation {{ mb_strtolower(\App\Models\Plan::cycles()[$cycle]) }}</p>
    </div>
  </section>

  <section class="cm-corps">
    <form class="cm-wrap" id="cmForm" method="POST"
          action="{{ route('abonnements.store', $plan) }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="cycle" value="{{ $cycle }}">

      <div>
        @if ($errors->any())
          <div class="cm-bloc cm-alerte" style="border-color:#fde68a">
            <strong>Votre commande n'a pas été enregistrée.</strong>
            Corrigez les champs signalés ci-dessous, puis renvoyez-la.
          </div>
        @endif

        {{-- ── 1. Qui commande ── --}}
        <div class="cm-bloc">
          <p class="cm-bloc-titre"><span class="cm-num">1</span> Vos coordonnées</p>

          <div class="cm-champ">
            <label for="nom_complet">Nom complet <span class="cm-req">*</span></label>
            <input type="text" id="nom_complet" name="nom_complet" value="{{ old('nom_complet') }}"
                   autocomplete="name" required>
            @error('nom_complet')<p class="cm-erreur">{{ $message }}</p>@enderror
          </div>

          <div class="cm-champ">
            <label for="entreprise">Entreprise ou organisation
              <span class="cm-aide">Laissez vide si la commande est à titre personnel</span>
            </label>
            <input type="text" id="entreprise" name="entreprise" value="{{ old('entreprise') }}"
                   autocomplete="organization">
            @error('entreprise')<p class="cm-erreur">{{ $message }}</p>@enderror
          </div>

          <div class="cm-champ">
            <label for="email">Adresse e-mail <span class="cm-req">*</span>
              <span class="cm-aide">Vos accès et vos factures arrivent à cette adresse</span>
            </label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   autocomplete="email" required>
            @error('email')<p class="cm-erreur">{{ $message }}</p>@enderror
          </div>

          <div class="cm-champ">
            <label for="whatsapp">Numéro WhatsApp <span class="cm-req">*</span>
              <span class="cm-aide">C'est par là que notre équipe vous répond</span>
            </label>
            <input type="tel" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}"
                   placeholder="+509 0000 0000" autocomplete="tel" required>
            @error('whatsapp')<p class="cm-erreur">{{ $message }}</p>@enderror
          </div>
        </div>

        {{-- ── 2. Le service ── --}}
        <div class="cm-bloc">
          <p class="cm-bloc-titre"><span class="cm-num">2</span> Votre {{ mb_strtolower($presentation['cle']) }}</p>

          @if ($plan->service === 'site_web')
            <div class="cm-champ">
              <label>Où en êtes-vous avec votre nom de domaine ? <span class="cm-req">*</span></label>
              <div class="cm-choix">
                @foreach ($origines as $valeur => $libelleOrigine)
                  <label class="cm-opt">
                    <input type="radio" name="domaine_origine" value="{{ $valeur }}"
                           data-sans-domaine="{{ $valeur === 'aucun' ? '1' : '0' }}"
                           @checked(old('domaine_origine') === $valeur) required>
                    <span>{{ $libelleOrigine }}</span>
                  </label>
                @endforeach
              </div>
              @error('domaine_origine')<p class="cm-erreur">{{ $message }}</p>@enderror
            </div>

            <div class="cm-champ" id="cmChampDomaine">
              <label for="domaine">Nom de domaine
                <span class="cm-aide">Celui que vous avez, ou celui que vous voulez — exemple : monentreprise.com</span>
              </label>
              <input type="text" id="domaine" name="domaine" value="{{ old('domaine') }}"
                     placeholder="monentreprise.com" autocapitalize="none" spellcheck="false">
              @error('domaine')<p class="cm-erreur">{{ $message }}</p>@enderror
            </div>

          @elseif ($plan->service === 'hebergement')
            <div class="cm-champ">
              <label for="domaine">Nom de domaine à héberger <span class="cm-req">*</span>
                <span class="cm-aide">Le site ou l'application que nous allons héberger</span>
              </label>
              <input type="text" id="domaine" name="domaine" value="{{ old('domaine') }}"
                     placeholder="monentreprise.com" autocapitalize="none" spellcheck="false" required>
              @error('domaine')<p class="cm-erreur">{{ $message }}</p>@enderror
            </div>

          @else
            <div class="cm-champ">
              <label for="domaine">Nom de domaine souhaité <span class="cm-req">*</span>
                <span class="cm-aide">Avec son extension — exemple : monentreprise.com</span>
              </label>
              <input type="text" id="domaine" name="domaine" value="{{ old('domaine') }}"
                     placeholder="monentreprise.com" autocapitalize="none" spellcheck="false" required>
              @error('domaine')<p class="cm-erreur">{{ $message }}</p>@enderror
            </div>

            <div class="cm-champ">
              <label>Ce domaine est… <span class="cm-aide">Nous vérifions sa disponibilité avant de l'enregistrer</span></label>
              <div class="cm-choix">
                @foreach (['a_enregistrer', 'a_transferer'] as $valeur)
                  <label class="cm-opt">
                    <input type="radio" name="domaine_origine" value="{{ $valeur }}"
                           @checked(old('domaine_origine', 'a_enregistrer') === $valeur)>
                    <span>{{ $origines[$valeur] }}</span>
                  </label>
                @endforeach
              </div>
              @error('domaine_origine')<p class="cm-erreur">{{ $message }}</p>@enderror
            </div>

            {{-- La durée vendue est celle de l'offre. Proposer « 5 ans » au prix
                 d'un an promettrait ce qui ne serait pas livré : pour une période
                 plus longue, l'équipe établit l'offre correspondante. --}}
            <p class="cm-aide" style="margin-top:-6px">
              Enregistré pour {{ $cycle === 'annuel' ? 'un an' : 'la période de l\'offre' }},
              renouvelé automatiquement. Pour une période plus longue, dites-le nous
              ci-dessous : nous vous établissons l'offre correspondante.
            </p>
          @endif

          <div class="cm-champ">
            <label for="besoins">Autre chose à nous dire ?
              <span class="cm-aide">Ce que vous voulez obtenir, vos contraintes, votre échéance</span>
            </label>
            <textarea id="besoins" name="besoins">{{ old('besoins') }}</textarea>
            @error('besoins')<p class="cm-erreur">{{ $message }}</p>@enderror
          </div>
        </div>

        {{-- ── 3. Le paiement ── --}}
        <div class="cm-bloc">
          <p class="cm-bloc-titre"><span class="cm-num">3</span> Votre moyen de paiement</p>

          @if (empty($moyens))
            {{-- Aucun moyen proposable : soit rien n'est publié, soit le taux de
                 change n'est pas réglé. Inventer un taux ferait payer au client
                 une somme que la comptabilité ne retrouverait pas. --}}
            <div class="cm-alerte">
              Aucun moyen de paiement n'est disponible en ligne pour cette offre
              en ce moment. Écrivez-nous sur
              <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">WhatsApp</a>
              et nous réglons la commande avec vous.
            </div>
          @else
            <div class="cm-champ">
              <label>Par quel moyen payez-vous ? <span class="cm-req">*</span>
                <span class="cm-aide">Le total s'affiche dans la devise du moyen choisi</span>
              </label>
              <div class="cm-choix">
                @foreach ($moyens as $moyen)
                  @php $p = $moyen['passerelle']; @endphp
                  <label class="cm-opt">
                    <input type="radio" name="moyen_paiement" value="{{ $p->code }}"
                           @checked(old('moyen_paiement') === $p->code)
                           data-nom="{{ $p->nom }}"
                           data-mode="{{ $p->mode }}"
                           data-montant="{{ $formater($moyen['montant']) }}"
                           data-devise="{{ $moyen['devise'] }}"
                           data-titulaire="{{ $p->titulaire }}"
                           data-valeur="{{ $p->valeur_a_copier }}"
                           data-lien="{{ $p->lien_paiement }}"
                           data-qr="{{ $p->qr_code_url }}"
                           data-instructions="{{ $p->instructions }}" required>
                    <span>
                      @if ($p->logo_url)
                        <img src="{{ $p->logo_url }}" alt="" loading="lazy">
                      @else
                        <span class="cm-opt-init">{{ $p->initiales }}</span>
                      @endif
                      <span>
                        {{ $p->nom }}
                        <span class="cm-opt-total">{{ $formater($moyen['montant']) }} {{ $moyen['devise'] }}</span>
                      </span>
                    </span>
                  </label>
                @endforeach
              </div>
              @error('moyen_paiement')<p class="cm-erreur">{{ $message }}</p>@enderror

              <div class="cm-coord" id="cmCoord">
                <p class="cm-coord-tete" id="cmCoordTitre"></p>
                <div id="cmCoordCorps"></div>
              </div>
            </div>

            {{-- Preuve : demandée aux seuls moyens manuels. Une passerelle
                 automatique confirme elle-même, et réclamer une capture avant
                 même de payer serait impossible à fournir. --}}
            <div class="cm-champ" id="cmChampPreuve" style="display:none">
              <label for="preuve">Preuve de paiement <span class="cm-req">*</span>
                <span class="cm-aide">La capture d'écran de votre transaction</span>
              </label>
              <label class="cm-depot" for="preuve">
                <i class="fas fa-cloud-arrow-up"></i>
                <strong>Choisir la capture</strong>
                <small>JPG, PNG, WEBP, HEIC ou PDF — 8 Mo maximum</small>
                <input type="file" name="preuve" id="preuve" accept="image/*,application/pdf">
              </label>
              <div class="cm-apercu" id="cmApercu">
                <img id="cmImage" alt="Aperçu de la capture">
                <div class="cm-fichier">
                  <span id="cmNomFichier"></span>
                  <button type="button" class="cm-retirer" id="cmRetirer">Retirer</button>
                </div>
              </div>
              @error('preuve')<p class="cm-erreur">{{ $message }}</p>@enderror
            </div>
          @endif
        </div>
      </div>

      {{-- ── Récapitulatif ── --}}
      <aside class="cm-cote">
        <div class="cm-recap">
          <h2>Votre commande</h2>

          <div class="cm-recap-ligne"><span>Service</span><strong>{{ $presentation['cle'] }}</strong></div>
          <div class="cm-recap-ligne"><span>Offre</span><strong>{{ $plan->nom }}</strong></div>
          <div class="cm-recap-ligne">
            <span>Facturation</span>
            <strong>{{ \App\Models\Plan::cycles()[$cycle] }}</strong>
          </div>
          <div class="cm-recap-ligne">
            <span>Montant {{ (float) $plan->tca_taux > 0 ? 'hors taxe' : '' }}</span>
            <strong>{{ $formater($montants['ht']) }} {{ $plan->devise }}</strong>
          </div>
          @if ((float) $plan->tca_taux > 0)
            <div class="cm-recap-ligne">
              <span>Taxe ({{ rtrim(rtrim(number_format((float) $plan->tca_taux, 2, ',', ' '), '0'), ',') }} %)</span>
              <strong>{{ $formater($montants['taxe']) }} {{ $plan->devise }}</strong>
            </div>
          @endif
          @if ($plan->essai_jours > 0)
            <div class="cm-recap-ligne">
              <span>Essai</span>
              <strong>{{ $plan->essai_jours }} jour{{ $plan->essai_jours > 1 ? 's' : '' }}</strong>
            </div>
          @endif

          <div class="cm-recap-total">
            <span id="cmTotalLibelle">Total par {{ $cycle === 'annuel' ? 'an' : 'mois' }}</span>
            <strong id="cmTotal">{{ $formater($montants['ttc']) }} {{ $plan->devise }}</strong>
            <p class="cm-recap-equiv" id="cmEquivalent">
              Choisissez un moyen de paiement : le total s'affichera dans sa devise.
            </p>
          </div>

          <button type="submit" class="cm-envoyer" id="cmBouton" @disabled(empty($moyens))>
            Envoyer ma commande
          </button>

          <p class="cm-recap-note">
            Rien n'est prélevé automatiquement. Votre commande est enregistrée,
            vérifiée par notre équipe, puis mise en service — et c'est à ce
            moment que l'abonnement commence.
          </p>
        </div>
      </aside>
    </form>
  </section>
</div>

{{-- JavaScript natif : Alpine n'est chargé que sur le layout ERP. --}}
<script>
(function () {
  var form = document.getElementById('cmForm');
  if (!form) return;

  // ── Le domaine n'est demandé que si le client en a un ou en veut un ──
  var champDomaine = document.getElementById('cmChampDomaine');

  form.addEventListener('change', function (e) {
    if (!champDomaine || e.target.name !== 'domaine_origine') return;

    var sans = e.target.dataset.sansDomaine === '1';
    champDomaine.style.display = sans ? 'none' : '';

    // Le champ caché est vidé : une valeur laissée derrière serait enregistrée
    // alors que le client vient de dire qu'il n'a pas de domaine.
    if (sans) {
      var saisie = document.getElementById('domaine');
      if (saisie) saisie.value = '';
    }
  });

  // ── Coordonnées et total du moyen choisi ──
  var coord = document.getElementById('cmCoord');
  var titre = document.getElementById('cmCoordTitre');
  var corps = document.getElementById('cmCoordCorps');
  var total = document.getElementById('cmTotal');
  var equivalent = document.getElementById('cmEquivalent');
  var champPreuve = document.getElementById('cmChampPreuve');
  var preuve = document.getElementById('preuve');

  var montantPlan = @json($formater($montants['ttc']).' '.$plan->devise);

  function ligne(libelle, valeur) {
    var d = document.createElement('div');
    d.className = 'cm-coord-ligne';
    var s = document.createElement('span'); s.textContent = libelle;
    var b = document.createElement('strong'); b.textContent = valeur;
    d.appendChild(s); d.appendChild(b);
    return d;
  }

  function choisir(el) {
    var d = el.dataset;

    // Total dans la devise de la passerelle.
    if (total) total.textContent = d.montant + ' ' + d.devise;
    if (equivalent) {
      equivalent.textContent = d.devise === @json($plan->devise)
        ? 'Réglé par ' + d.nom + '.'
        : 'Soit ' + montantPlan + ' au taux appliqué par GOVIBE.';
    }

    // Preuve : manuel seulement.
    var manuel = d.mode !== 'api';
    if (champPreuve) champPreuve.style.display = manuel ? '' : 'none';
    if (preuve) {
      preuve.required = manuel;
      if (!manuel) { preuve.value = ''; masquerApercu(); }
    }

    if (!coord) return;

    titre.textContent = manuel ? ('Payer par ' + d.nom) : (d.nom + ' — paiement automatique');
    corps.textContent = '';

    if (!manuel) {
      var auto = document.createElement('p');
      auto.className = 'cm-coord-auto';
      auto.textContent = 'Vous serez redirigé vers ' + d.nom
        + ' pour régler ' + d.montant + ' ' + d.devise
        + '. Votre commande est enregistrée avant le départ.';
      corps.appendChild(auto);
      coord.classList.add('cm-ouvert');
      return;
    }

    corps.appendChild(ligne('À envoyer', d.montant + ' ' + d.devise));
    if (d.titulaire) corps.appendChild(ligne('Au nom de', d.titulaire));

    if (d.valeur) {
      var enveloppe = document.createElement('div');
      enveloppe.className = 'cm-coord-ligne';
      var lab = document.createElement('span'); lab.textContent = 'Numéro / adresse';
      var val = document.createElement('span');
      val.className = 'cm-coord-valeur';
      val.textContent = d.valeur;
      enveloppe.appendChild(lab); enveloppe.appendChild(val);
      corps.appendChild(enveloppe);
    }

    // Une passerelle de type « lien » n'a pas de numéro à recopier : sans ce
    // bouton, le client n'aurait aucun moyen de payer.
    if (d.lien) {
      var aller = document.createElement('a');
      aller.href = d.lien;
      aller.target = '_blank';
      aller.rel = 'noopener';
      aller.className = 'cm-coord-auto';
      aller.style.display = 'block';
      aller.style.textAlign = 'center';
      aller.style.fontWeight = '800';
      aller.textContent = 'Ouvrir la page de paiement ' + d.nom;
      corps.appendChild(aller);
    }

    if (d.qr) {
      var img = document.createElement('img');
      img.src = d.qr;
      img.alt = 'QR code ' + d.nom;
      img.loading = 'lazy';
      corps.appendChild(img);
    }

    if (d.instructions) {
      var p = document.createElement('p');
      p.className = 'cm-coord-texte';
      p.textContent = d.instructions;
      corps.appendChild(p);
    }

    coord.classList.add('cm-ouvert');
  }

  form.addEventListener('change', function (e) {
    if (e.target.name === 'moyen_paiement') choisir(e.target);
  });

  // Au retour d'une erreur de validation, le choix est déjà coché : les
  // coordonnées doivent réapparaître sans nouveau clic.
  var dejaChoisi = form.querySelector('input[name="moyen_paiement"]:checked');
  if (dejaChoisi) choisir(dejaChoisi);

  var dejaSans = form.querySelector('input[name="domaine_origine"]:checked');
  if (dejaSans && champDomaine && dejaSans.dataset.sansDomaine === '1') {
    champDomaine.style.display = 'none';
  }

  // ── Aperçu de la capture ──
  var apercu = document.getElementById('cmApercu');
  var image = document.getElementById('cmImage');
  var nomFichier = document.getElementById('cmNomFichier');
  var retirer = document.getElementById('cmRetirer');

  function masquerApercu() {
    if (!apercu) return;
    apercu.classList.remove('cm-ouvert');
    if (image) image.removeAttribute('src');
  }

  if (preuve) {
    preuve.addEventListener('change', function () {
      var f = preuve.files && preuve.files[0];
      if (!f) { masquerApercu(); return; }

      nomFichier.textContent = f.name;
      apercu.classList.add('cm-ouvert');

      // Un PDF n'a pas d'aperçu : afficher une image vide donnerait une
      // vignette cassée.
      if (f.type.indexOf('image/') === 0) {
        image.src = URL.createObjectURL(f);
        image.style.display = '';
      } else {
        image.style.display = 'none';
      }
    });
  }

  if (retirer) {
    retirer.addEventListener('click', function () {
      preuve.value = '';
      nomFichier.textContent = '';
      masquerApercu();
    });
  }

  // ── Un seul envoi ──
  var bouton = document.getElementById('cmBouton');
  form.addEventListener('submit', function () {
    if (!form.checkValidity()) return;
    if (bouton) { bouton.disabled = true; bouton.textContent = 'Envoi en cours…'; }
  });
})();
</script>
@endsection

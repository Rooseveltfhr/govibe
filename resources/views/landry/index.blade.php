@extends('layouts.public')

@section('title', 'LANDRY — Réservation | GOVIBE Innovation Hub')
@section('description', "Réservez gratuitement le service de lavage LANDRY de GOVIBE : à domicile ou dans notre local aux Gonaïves. Services à partir du 11 octobre 2026.")

@section('head')
<style>
  .ld { --ld-rouge:#E01813; --ld-bleu:#0B3AB0; --ld-vert:#48A018;
        --ld-encre:#172033; --ld-gris:#667085; --ld-fond:#f6f7f9;
        --ld-trait:#e4e7ec; }

  .ld-hero { background:#fff; text-align:center; border-bottom:1px solid var(--ld-trait); }
  /* Bandeau : l'image si elle existe, sinon un aplat — jamais une image cassée. */
  .ld-banniere {
    height:290px; background:linear-gradient(135deg,#0B3AB0 0%,#E01813 100%);
    display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden;
  }
  .ld-banniere img { width:100%; height:100%; object-fit:cover; display:block; }
  .ld-banniere-texte {
    font-family:'Anton',sans-serif; font-size:clamp(3rem,12vw,5.5rem); color:rgba(255,255,255,.22);
    letter-spacing:.18em; text-transform:uppercase; user-select:none;
  }
  .ld-hero-in { padding:16px 15px 18px; }
  .ld-logo { width:125px; max-width:35%; margin-bottom:4px; }
  .ld-hero h1 {
    font-family:'Anton',sans-serif; font-size:38px; line-height:1; font-weight:400;
    color:var(--ld-rouge); letter-spacing:2px; margin:0;
  }
  .ld-sous { color:var(--ld-gris); font-size:14px; margin:4px 0 0; }
  .ld-reserve { color:var(--ld-bleu); font-size:16px; font-weight:800; margin-top:10px; }
  .ld-info { font-size:12px; color:var(--ld-gris); margin-top:3px; }

  .ld-sect { background:var(--ld-fond); padding:14px 0; }
  .ld-wrap { width:100%; max-width:850px; margin:auto; padding:0 15px; }

  .ld-boite { background:#fff; border-radius:12px; padding:14px 16px; border:1px solid var(--ld-trait); }
  .ld-boite-titre { text-align:center; font-size:14px; font-weight:800; margin:0 0 8px; }
  .ld-piste { height:13px; background:#e9ecf1; border-radius:50px; overflow:hidden; }
  .ld-barre {
    height:100%; border-radius:50px; position:relative;
    background:linear-gradient(90deg,var(--ld-rouge),#f0a500,var(--ld-vert));
  }
  .ld-barre::after {
    content:""; position:absolute; top:0; left:-80px; width:80px; height:100%;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.75),transparent);
    animation:ld-brille 2s linear infinite;
  }
  @keyframes ld-brille { to { left:100%; } }
  @media (prefers-reduced-motion: reduce) { .ld-barre::after { animation:none; } }
  .ld-jauge { display:flex; justify-content:space-between; align-items:center; margin-top:6px; font-size:11px; color:var(--ld-gris); }
  .ld-jours { text-align:center; margin-top:7px; font-size:13px; font-weight:800; color:var(--ld-bleu); }

  .ld-services-titre { text-align:center; font-size:20px; font-weight:800; margin:0 0 5px; }
  .ld-services-desc { text-align:center; color:var(--ld-gris); font-size:13px; margin:0 0 8px; }
  .ld-ligne {
    background:#fff; border:1px solid var(--ld-trait); border-radius:10px;
    padding:10px 13px; text-align:center; font-size:13px; font-weight:700;
  }
  .ld-ligne span { color:var(--ld-rouge); margin:0 7px; }

  .ld-carte { background:#fff; border-radius:14px; border:1px solid var(--ld-trait); padding:20px; }
  .ld-carte h2 { text-align:center; font-size:23px; font-weight:800; margin:0 0 3px; }
  .ld-carte-desc { text-align:center; color:var(--ld-gris); font-size:12px; margin:0 0 17px; }

  .ld-etapes { display:grid; grid-template-columns:repeat(4,1fr); gap:4px; margin-bottom:20px; }
  .ld-etape { text-align:center; font-size:10px; color:#98a2b3; font-weight:700; }
  .ld-etape-num {
    width:28px; height:28px; margin:0 auto 3px; border-radius:50%;
    display:flex; justify-content:center; align-items:center; background:#eaecf0;
  }
  .ld-etape.actif { color:var(--ld-bleu); }
  .ld-etape.actif .ld-etape-num { background:var(--ld-bleu); color:#fff; }
  .ld-etape.fait { color:var(--ld-vert); }
  .ld-etape.fait .ld-etape-num { background:var(--ld-vert); color:#fff; }

  .ld-pan { display:none; }
  .ld-pan.actif { display:block; animation:ld-fade .2s ease; }
  @keyframes ld-fade { from { opacity:0; transform:translateY(5px); } to { opacity:1; transform:none; } }
  .ld-pan h3 { font-size:18px; margin:0 0 15px; }

  .ld-champ { margin-bottom:13px; }
  .ld-champ label { display:block; font-size:13px; font-weight:700; margin-bottom:5px; }
  .ld-req { color:var(--ld-rouge); }
  /* 16px : en dessous, iOS zoome au focus. */
  .ld-champ input, .ld-champ textarea, .ld-champ select {
    width:100%; border:1px solid #d0d5dd; border-radius:8px; padding:11px 12px;
    font-size:16px; outline:none; background:#fff; font-family:inherit; color:var(--ld-encre);
  }
  .ld-champ input:focus, .ld-champ textarea:focus, .ld-champ select:focus {
    border-color:var(--ld-bleu); box-shadow:0 0 0 2px rgba(11,58,176,.08);
  }
  .ld-champ textarea { min-height:75px; resize:vertical; }
  .ld-aide { color:var(--ld-gris); font-size:11px; margin-top:3px; }

  .ld-choix { display:grid; gap:7px; }
  .ld-opt {
    display:flex; align-items:flex-start; gap:9px; border:1px solid var(--ld-trait);
    border-radius:8px; padding:10px; cursor:pointer; transition:.2s; background:#fff;
  }
  .ld-opt:hover { border-color:#b8c0cc; }
  .ld-opt.choisi { border-color:var(--ld-bleu); background:#f5f7ff; }
  .ld-opt input { width:auto; margin-top:3px; flex-shrink:0; }
  .ld-opt-titre { font-size:13px; font-weight:800; }
  .ld-opt-desc { font-size:11px; color:var(--ld-gris); margin-top:2px; }

  .ld-boutons { display:flex; gap:8px; margin-top:18px; }
  .ld-btn { flex:1; border:none; border-radius:8px; padding:12px; cursor:pointer; font-size:13px; font-weight:800; font-family:inherit; }
  .ld-btn-1 { background:var(--ld-rouge); color:#fff; }
  .ld-btn-1:hover { background:#bd120e; }
  .ld-btn-1:disabled { opacity:.6; cursor:default; }
  .ld-btn-2 { background:#eaecf0; color:var(--ld-encre); }

  .ld-resume { background:#f8fafc; border-radius:9px; padding:12px; }
  .ld-resume-l { display:flex; justify-content:space-between; gap:15px; padding:7px 0; border-bottom:1px solid #eaecf0; font-size:12px; }
  .ld-resume-l:last-child { border-bottom:none; }
  .ld-resume-l span { color:var(--ld-gris); }
  .ld-resume-l strong { font-weight:700; text-align:right; }
  .ld-check { display:flex; gap:8px; margin-top:14px; font-size:12px; align-items:flex-start; }
  .ld-check input { width:auto; margin-top:2px; }

  .ld-err {
    background:#fef2f2; border:1px solid #fecaca; border-radius:10px;
    padding:12px 14px; margin-bottom:15px; color:#991b1b; font-size:13px;
  }
  .ld-err ul { margin:5px 0 0; padding-left:18px; }
  .ld-clos {
    background:#fffbeb; border:1px solid #fde68a; border-radius:12px;
    padding:16px; text-align:center; color:#92400e; font-size:14px; line-height:1.7;
  }
  .ld-hidden { display:none !important; }

  @media (max-width:600px) {
    .ld-banniere { height:190px; }
    .ld-hero-in { padding:12px 10px 14px; }
    .ld-logo { width:105px; }
    .ld-hero h1 { font-size:32px; }
    .ld-reserve { font-size:14px; }
    .ld-services-titre { font-size:18px; }
    .ld-ligne { font-size:11px; padding:9px 5px; }
    .ld-ligne span { margin:0 3px; }
    .ld-carte { padding:15px; }
    .ld-boutons { flex-direction:column-reverse; }
    .ld-resume-l { flex-direction:column; gap:2px; }
    .ld-resume-l strong { text-align:left; }
  }
</style>
@endsection

@section('content')
<div class="ld">

  <header class="ld-hero">
    @php $banniere = file_exists(public_path('images/landry-header.jpg')) ? asset('images/landry-header.jpg') : null; @endphp
    <div class="ld-banniere">
      @if ($banniere)
        <img src="{{ $banniere }}" alt="LANDRY">
      @else
        <span class="ld-banniere-texte">Landry</span>
      @endif
    </div>

    <div class="ld-hero-in">
      <img src="{{ asset('images/govibe-logo.svg') }}" alt="GOVIBE Innovation Hub" class="ld-logo">
      <h1>LANDRY</h1>
      <p class="ld-sous">Service de lavage professionnel</p>
      @unless ($ouvert)
        <div class="ld-reserve">RÉSERVEZ gratuitement avant le {{ $ouverture->translatedFormat('j F') }}</div>
      @endunless
      <div class="ld-info">
        @if ($ouvert)
          Les services sont ouverts.
        @else
          Service yo ap komanse nan mwa {{ $ouverture->locale('fr')->translatedFormat('F') }} lan.
        @endif
      </div>
    </div>
  </header>

  <section class="ld-sect">
    <div class="ld-wrap">
      <div class="ld-boite">
        <p class="ld-boite-titre">
          Ouverture officielle des services : {{ $ouverture->locale('fr')->translatedFormat('j F Y') }}
        </p>
        <div class="ld-piste">
          {{-- Rendue à sa largeur réelle dès le serveur : la barre est juste
               même si le JavaScript ne s'exécute pas. --}}
          <div class="ld-barre" id="ldBarre" style="width:{{ $progression }}%"></div>
        </div>
        <div class="ld-jauge">
          <span>{{ $debut->locale('fr')->translatedFormat('j F') }}</span>
          <strong id="ldPourcent">{{ $progression }}%</strong>
          <span>{{ $ouverture->locale('fr')->translatedFormat('j F') }}</span>
        </div>
        <div class="ld-jours" id="ldJours">
          @if ($ouvert)
            Les services sont officiellement ouverts.
          @else
            Il reste {{ $joursRestants }} jour{{ $joursRestants > 1 ? 's' : '' }} avant le début des services.
          @endif
        </div>
      </div>
    </div>
  </section>

  <section class="ld-sect" style="padding-top:0">
    <div class="ld-wrap">
      <h2 class="ld-services-titre">Nos services sont offerts</h2>
      <p class="ld-services-desc">Choisissez la formule qui vous convient.</p>
      <div class="ld-ligne">
        À domicile <span>&bull;</span> Dans notre local GOVIBE ({{ $adresse }})
      </div>
    </div>
  </section>

  <section class="ld-sect" style="padding-top:8px; padding-bottom:40px">
    <div class="ld-wrap">
      <div class="ld-carte">

        @if ($errors->any())
          <div class="ld-err">
            <strong>Vérifiez ces points :</strong>
            <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        @endif

        <h2>Réservation</h2>
        <p class="ld-carte-desc">Remplissez les informations ci-dessous pour réserver votre service.</p>

        <div class="ld-etapes">
          @foreach (['Informations', 'Service', 'Paiement', 'Confirmation'] as $i => $nom)
            <div class="ld-etape {{ $i === 0 ? 'actif' : '' }}" data-indicateur="{{ $i + 1 }}">
              <div class="ld-etape-num">{{ $i + 1 }}</div>{{ $nom }}
            </div>
          @endforeach
        </div>

        <form method="POST" action="{{ route('landry.store') }}" id="ldForm">
          @csrf

          {{-- Étape 1 --}}
          <div class="ld-pan actif" data-pan="1">
            <h3>Informations du client</h3>

            <div class="ld-champ">
              <label for="nom_complet">Nom complet <span class="ld-req">*</span></label>
              <input type="text" name="nom_complet" id="nom_complet" value="{{ old('nom_complet') }}"
                     placeholder="Votre nom complet" required maxlength="150" autocomplete="name">
            </div>

            <div class="ld-champ">
              <label for="whatsapp">Numéro WhatsApp <span class="ld-req">*</span></label>
              <input type="tel" name="whatsapp" id="whatsapp" value="{{ old('whatsapp') }}"
                     placeholder="+509 0000 0000" required maxlength="40" autocomplete="tel">
              <div class="ld-aide">Ce numéro sera utilisé pour vous contacter.</div>
            </div>

            <div class="ld-champ">
              <label for="adresse">Adresse <span class="ld-req">*</span></label>
              <textarea name="adresse" id="adresse" placeholder="Votre adresse complète" required maxlength="2000">{{ old('adresse') }}</textarea>
            </div>

            <div class="ld-champ">
              <label for="point_repere">Point de repère</label>
              <input type="text" name="point_repere" id="point_repere" value="{{ old('point_repere') }}"
                     placeholder="Ex : près de..., maison de..." maxlength="255">
            </div>

            <div class="ld-boutons">
              <button type="button" class="ld-btn ld-btn-1" data-suivant>Continuer</button>
            </div>
          </div>

          {{-- Étape 2 --}}
          <div class="ld-pan" data-pan="2">
            <h3>Votre service</h3>

            <div class="ld-champ">
              <label>Comment souhaitez-vous prendre le service ? <span class="ld-req">*</span></label>
              <div class="ld-choix">
                @foreach ([
                  'domicile' => ['À domicile', 'Nous venons récupérer vos vêtements chez vous et nous vous les rapportons.'],
                  'local'    => ['Dans notre local GOVIBE', $adresse.'.'],
                ] as $v => [$titre, $desc])
                  <label class="ld-opt {{ old('mode_service') === $v ? 'choisi' : '' }}">
                    <input type="radio" name="mode_service" value="{{ $v }}" @checked(old('mode_service') === $v) required>
                    <span>
                      <span class="ld-opt-titre">{{ $titre }}</span>
                      <span class="ld-opt-desc">{{ $desc }}</span>
                    </span>
                  </label>
                @endforeach
              </div>
            </div>

            <div class="ld-champ {{ old('mode_service') === 'domicile' ? '' : 'ld-hidden' }}" id="ldRecup">
              <label for="instructions_recuperation">Instructions pour la récupération</label>
              <textarea name="instructions_recuperation" id="instructions_recuperation" maxlength="2000"
                        placeholder="Indiquez les informations utiles pour trouver votre domicile.">{{ old('instructions_recuperation') }}</textarea>
            </div>

            <div class="ld-champ">
              <label>Comment souhaitez-vous être facturé ? <span class="ld-req">*</span></label>
              <div class="ld-choix">
                @foreach ([
                  'unite' => ['Par unité de vêtement', 'Facturation selon le nombre de vêtements.'],
                  'poids' => ['Par poids', 'Le linge sera pesé lors de la réception.'],
                ] as $v => [$titre, $desc])
                  <label class="ld-opt {{ old('mode_facturation') === $v ? 'choisi' : '' }}">
                    <input type="radio" name="mode_facturation" value="{{ $v }}" @checked(old('mode_facturation') === $v) required>
                    <span>
                      <span class="ld-opt-titre">{{ $titre }}</span>
                      <span class="ld-opt-desc">{{ $desc }}</span>
                    </span>
                  </label>
                @endforeach
              </div>
            </div>

            <div class="ld-champ {{ old('mode_facturation') === 'unite' ? '' : 'ld-hidden' }}" id="ldQuantite">
              <label for="quantite_vetements">Nombre approximatif de vêtements</label>
              <input type="number" name="quantite_vetements" id="quantite_vetements" min="1" max="10000"
                     value="{{ old('quantite_vetements') }}" placeholder="Ex : 20">
            </div>

            <div class="ld-champ">
              <label for="frequence">À quelle fréquence avez-vous besoin de laver ? <span class="ld-req">*</span></label>
              <select name="frequence" id="frequence" required>
                <option value="">Sélectionnez</option>
                @foreach (\App\Models\ReservationLandry::frequences() as $v => $l)
                  <option value="{{ $v }}" @selected(old('frequence') === $v)>{{ $l }}</option>
                @endforeach
              </select>
            </div>

            <div class="ld-boutons">
              <button type="button" class="ld-btn ld-btn-2" data-retour>Retour</button>
              <button type="button" class="ld-btn ld-btn-1" data-suivant>Continuer</button>
            </div>
          </div>

          {{-- Étape 3 --}}
          <div class="ld-pan" data-pan="3">
            <h3>Paiement et inscription</h3>

            <div class="ld-champ">
              <label>Comment souhaitez-vous payer ? <span class="ld-req">*</span></label>
              <div class="ld-choix">
                @foreach ([
                  'abonnement'  => ['Abonnement', "Je préfère payer selon une formule d'abonnement."],
                  'par_service' => ['Paiement à chaque service', 'Je paie chaque fois que je prends le service.'],
                ] as $v => [$titre, $desc])
                  <label class="ld-opt {{ old('mode_paiement') === $v ? 'choisi' : '' }}">
                    <input type="radio" name="mode_paiement" value="{{ $v }}" @checked(old('mode_paiement') === $v) required>
                    <span>
                      <span class="ld-opt-titre">{{ $titre }}</span>
                      <span class="ld-opt-desc">{{ $desc }}</span>
                    </span>
                  </label>
                @endforeach
              </div>
            </div>

            @php $fraisAffiche = rtrim(rtrim(number_format($frais, 2, ',', ' '), '0'), ',').' '.$devise; @endphp
            <div class="ld-champ">
              <label>Acceptez-vous de payer {{ $fraisAffiche }} pour votre inscription ? <span class="ld-req">*</span></label>
              <div class="ld-choix">
                @foreach ([
                  'oui' => ["Oui, j'accepte", "J'accepte les frais d'inscription de {$fraisAffiche}."],
                  'non' => ['Non', "Je souhaite d'abord recevoir plus d'informations."],
                ] as $v => [$titre, $desc])
                  <label class="ld-opt {{ old('frais_inscription') === $v ? 'choisi' : '' }}">
                    <input type="radio" name="frais_inscription" value="{{ $v }}" @checked(old('frais_inscription') === $v) required>
                    <span>
                      <span class="ld-opt-titre">{{ $titre }}</span>
                      <span class="ld-opt-desc">{{ $desc }}</span>
                    </span>
                  </label>
                @endforeach
              </div>
            </div>

            <div class="ld-boutons">
              <button type="button" class="ld-btn ld-btn-2" data-retour>Retour</button>
              <button type="button" class="ld-btn ld-btn-1" data-suivant>Voir le résumé</button>
            </div>
          </div>

          {{-- Étape 4 --}}
          <div class="ld-pan" data-pan="4">
            <h3>Vérifiez votre réservation</h3>

            <div class="ld-resume">
              @foreach ([
                'Nom' => 'nom_complet', 'WhatsApp' => 'whatsapp', 'Adresse' => 'adresse',
                'Service' => 'mode_service', 'Facturation' => 'mode_facturation',
                'Fréquence' => 'frequence', 'Paiement' => 'mode_paiement', 'Inscription' => 'frais_inscription',
              ] as $libelle => $cle)
                <div class="ld-resume-l">
                  <span>{{ $libelle }}</span>
                  <strong data-resume="{{ $cle }}">—</strong>
                </div>
              @endforeach
            </div>

            <label class="ld-check">
              <input type="checkbox" id="ldConfirme" required>
              <span>J'ai vérifié les informations et elles sont correctes.</span>
            </label>

            <div class="ld-boutons">
              <button type="button" class="ld-btn ld-btn-2" data-retour>Modifier</button>
              <button type="submit" class="ld-btn ld-btn-1" id="ldEnvoyer">CONFIRMER MA RÉSERVATION</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

{{-- JavaScript natif : Alpine n'est chargé que sur le layout ERP. --}}
<script>
(function () {
  var form = document.getElementById('ldForm');
  if (!form) return;

  var etape = 1;
  var TOTAL = 4;

  function panneau(n) { return form.querySelector('.ld-pan[data-pan="' + n + '"]'); }

  // ── Validation de l'étape courante ──────────────────
  function valide() {
    var p = panneau(etape);
    var vus = {};

    var champs = p.querySelectorAll('input[required], textarea[required], select[required]');

    for (var i = 0; i < champs.length; i++) {
      var c = champs[i];

      if (c.type === 'radio') {
        if (vus[c.name]) continue;
        vus[c.name] = true;
        if (!p.querySelector('input[name="' + c.name + '"]:checked')) {
          alert('Veuillez sélectionner une option.');
          return false;
        }
      } else if (c.type === 'checkbox') {
        if (!c.checked) { alert('Veuillez confirmer les informations.'); return false; }
      } else if (!c.value.trim()) {
        c.focus();
        alert('Veuillez remplir les champs obligatoires.');
        return false;
      }
    }
    return true;
  }

  function afficher() {
    form.querySelectorAll('.ld-pan').forEach(function (p) {
      p.classList.toggle('actif', Number(p.dataset.pan) === etape);
    });
    document.querySelectorAll('.ld-etape').forEach(function (e) {
      var n = Number(e.dataset.indicateur);
      e.classList.toggle('actif', n === etape);
      e.classList.toggle('fait', n < etape);
    });
    var carte = document.querySelector('.ld-carte');
    if (carte) window.scrollTo({ top: carte.offsetTop - 15, behavior: 'smooth' });
  }

  form.addEventListener('click', function (e) {
    var suivant = e.target.closest('[data-suivant]');
    var retour = e.target.closest('[data-retour]');

    if (suivant) {
      if (!valide()) return;
      if (etape < TOTAL) { etape++; if (etape === TOTAL) resume(); afficher(); }
    } else if (retour && etape > 1) {
      etape--; afficher();
    }
  });

  // ── Pastilles et champs conditionnels ───────────────
  form.addEventListener('change', function (e) {
    var champ = e.target;

    if (champ.type === 'radio') {
      form.querySelectorAll('input[name="' + champ.name + '"]').forEach(function (autre) {
        autre.closest('.ld-opt').classList.toggle('choisi', autre.checked);
      });
    }

    // Les instructions ne servent qu'à domicile, la quantité qu'à l'unité :
    // afficher les deux tout le temps ferait remplir des champs inutiles.
    if (champ.name === 'mode_service') {
      document.getElementById('ldRecup').classList.toggle('ld-hidden', champ.value !== 'domicile');
    }
    if (champ.name === 'mode_facturation') {
      document.getElementById('ldQuantite').classList.toggle('ld-hidden', champ.value !== 'unite');
    }
  });

  // ── Résumé ──────────────────────────────────────────
  function libelle(nom) {
    var coche = form.querySelector('input[name="' + nom + '"]:checked');
    if (!coche) return '—';
    var titre = coche.closest('.ld-opt').querySelector('.ld-opt-titre');
    return titre ? titre.textContent.trim() : coche.value;
  }

  function resume() {
    var valeurs = {
      nom_complet: form.nom_complet.value,
      whatsapp: form.whatsapp.value,
      adresse: form.adresse.value,
      mode_service: libelle('mode_service'),
      mode_facturation: libelle('mode_facturation'),
      frequence: form.frequence.options[form.frequence.selectedIndex].text,
      mode_paiement: libelle('mode_paiement'),
      frais_inscription: libelle('frais_inscription'),
    };

    form.querySelectorAll('[data-resume]').forEach(function (el) {
      el.textContent = valeurs[el.dataset.resume] || '—';
    });
  }

  // ── Envoi ───────────────────────────────────────────
  form.addEventListener('submit', function (e) {
    if (!document.getElementById('ldConfirme').checked) {
      e.preventDefault();
      alert('Veuillez confirmer que les informations sont correctes.');
      return;
    }
    var b = document.getElementById('ldEnvoyer');
    b.disabled = true;
    b.textContent = 'Envoi en cours...';
  });

  // Après une erreur du serveur, on repart de l'étape qui la porte.
  @if ($errors->any())
    etape = 1;
    afficher();
  @endif
})();
</script>
@endsection

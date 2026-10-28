@extends('layouts.public')

@section('title', $textes['titre_page'])
@section('description', $textes['meta'])

@section('head')
<style>
  .eq { --eq-bleu:#1B3A8C; --eq-rouge:#DC2626; --eq-encre:#111827;
        --eq-gris:#5b6472; --eq-fond:#f6f8fb; --eq-trait:#dfe5ee; }

  /* ── En-tête ── */
  .eq-tete { background:linear-gradient(140deg,#15306f 0%,var(--eq-bleu) 55%,#24489f 100%); color:#fff; }
  .eq-tete-in { max-width:900px; margin:auto; padding:46px 16px 40px; }
  .eq-bandeau {
    display:inline-block; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.3);
    border-radius:999px; padding:6px 15px; font-size:12px; font-weight:700;
    letter-spacing:.12em; text-transform:uppercase;
  }
  .eq-tete h1 {
    font-family:'Anton',sans-serif; font-weight:400; letter-spacing:.5px;
    font-size:clamp(27px,5.4vw,45px); line-height:1.08; margin:16px 0 0; text-wrap:balance;
  }
  .eq-tete .eq-accroche { margin:14px 0 0; font-size:17px; font-weight:700; color:#ffd9d9; }
  .eq-tete p.eq-intro { margin:12px 0 0; font-size:15.5px; line-height:1.72; color:rgba(255,255,255,.92); max-width:62ch; }

  .eq-langues { display:flex; gap:8px; align-items:center; margin-top:24px; flex-wrap:wrap; }
  .eq-langues span { font-size:12.5px; color:rgba(255,255,255,.7); margin-right:3px; }
  .eq-langues a {
    border:1px solid rgba(255,255,255,.4); border-radius:999px; padding:8px 17px;
    font-size:13.5px; font-weight:700; color:#fff; text-decoration:none;
  }
  .eq-langues a[aria-current="true"] { background:#fff; color:var(--eq-bleu); border-color:#fff; }

  /* ── Pourquoi ── */
  .eq-sect { background:var(--eq-fond); padding:34px 0; }
  .eq-wrap { max-width:900px; margin:auto; padding:0 16px; }
  .eq-carte { background:#fff; border:1px solid var(--eq-trait); border-radius:14px; padding:22px; }
  .eq-carte h2 { margin:0 0 14px; font-size:17px; font-weight:800; color:var(--eq-encre); }
  .eq-raisons { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:11px; }
  .eq-raisons li { display:flex; gap:11px; font-size:14.5px; line-height:1.6; color:var(--eq-gris); }
  .eq-raisons i { color:var(--eq-rouge); margin-top:4px; font-size:13px; flex:0 0 auto; }

  .eq-notes { display:flex; flex-direction:column; gap:9px; margin-top:16px; }
  .eq-note { display:flex; gap:10px; font-size:13.5px; line-height:1.65; color:var(--eq-gris);
             background:var(--eq-fond); border-radius:10px; padding:11px 13px; }
  .eq-note i { color:var(--eq-bleu); margin-top:3px; flex:0 0 auto; }
  .eq-note.eq-prive { background:#f0fdf4; color:#166534; }
  .eq-note.eq-prive i { color:#16a34a; }

  /* ── Formulaire ── */
  .eq-bloc { background:#fff; border:1px solid var(--eq-trait); border-radius:14px; padding:22px; margin-bottom:16px; }
  .eq-bloc-titre { display:flex; align-items:center; gap:10px; font-size:16px; font-weight:800; color:var(--eq-encre); margin:0 0 18px; }
  .eq-num {
    width:25px; height:25px; border-radius:50%; background:var(--eq-bleu); color:#fff;
    font-size:12.5px; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto;
  }

  .eq-champ { margin-bottom:19px; }
  .eq-champ:last-child { margin-bottom:0; }
  .eq-champ > label, .eq-legende {
    display:block; font-size:14.5px; font-weight:700; color:var(--eq-encre); margin-bottom:8px;
  }
  .eq-aide { display:block; font-weight:500; font-size:12.5px; color:var(--eq-gris); margin-top:3px; }
  .eq-req { color:var(--eq-rouge); }
  /* 16px : en dessous, iOS zoome à la mise au point du champ. */
  .eq-champ input[type=text], .eq-champ input[type=email], .eq-champ input[type=tel],
  .eq-champ select, .eq-champ textarea {
    width:100%; font:inherit; font-size:16px; color:var(--eq-encre); background:#fff;
    border:1px solid var(--eq-trait); border-radius:10px; padding:12px 13px;
  }
  .eq-champ textarea { min-height:88px; resize:vertical; }
  .eq-champ input:focus, .eq-champ select:focus, .eq-champ textarea:focus {
    outline:2px solid rgba(27,58,140,.3); outline-offset:1px; border-color:var(--eq-bleu);
  }
  .eq-erreur { color:var(--eq-rouge); font-size:12.5px; font-weight:600; margin:6px 0 0; }

  fieldset { border:0; padding:0; margin:0; }

  .eq-choix { display:grid; gap:8px; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); }
  .eq-opt { position:relative; cursor:pointer; }
  .eq-opt input { position:absolute; opacity:0; width:0; height:0; }
  .eq-opt > span {
    display:flex; align-items:center; gap:10px; min-height:48px; height:100%;
    border:1px solid var(--eq-trait); border-radius:10px; padding:10px 13px;
    font-size:14px; line-height:1.4; color:var(--eq-encre); background:#fff;
  }
  .eq-opt > span::before {
    content:""; width:19px; height:19px; border:2px solid #c3ccda; border-radius:5px;
    flex:0 0 auto; background:#fff;
  }
  .eq-opt input[type=radio] + span::before { border-radius:50%; }
  .eq-opt input:checked + span {
    border-color:var(--eq-bleu); background:#eef3ff; box-shadow:inset 0 0 0 1px var(--eq-bleu);
  }
  .eq-opt input:checked + span::before { border-color:var(--eq-bleu); background:var(--eq-bleu); box-shadow:inset 0 0 0 3px #fff; }
  .eq-opt input:focus-visible + span { outline:2px solid rgba(27,58,140,.45); outline-offset:2px; }

  .eq-envoyer {
    width:100%; border:0; cursor:pointer; font:inherit; font-size:16px; font-weight:800;
    background:var(--eq-rouge); color:#fff; border-radius:12px; padding:16px;
  }
  .eq-envoyer:disabled { opacity:.6; cursor:progress; }
  .eq-conclusion { text-align:center; font-size:13.5px; line-height:1.7; color:var(--eq-gris); margin:16px 0 0; }
  .eq-alerte { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:12px; padding:14px 16px; font-size:14px; line-height:1.6; margin-bottom:16px; }

  @media (max-width:560px) {
    .eq-tete-in { padding:34px 16px 30px; }
    .eq-bloc, .eq-carte { padding:18px; }
  }
</style>
@endsection

@section('content')
@php
  // Le choix de langue voyage dans chaque lien et dans le formulaire : sans
  // cela, un rechargement ramènerait au français quelqu'un qui ne le lit pas.
  $autre = $langue === 'fr' ? 'ht' : 'fr';
@endphp

<div class="eq">

  <section class="eq-tete">
    <div class="eq-tete-in">
      <span class="eq-bandeau">{{ $textes['bandeau'] }}</span>
      <h1>{{ $textes['titre'] }}</h1>
      <p class="eq-accroche">{{ $textes['accroche'] }}</p>
      <p class="eq-intro">{{ $textes['intro_1'] }}</p>
      <p class="eq-intro">{{ $textes['intro_2'] }}</p>
      <p class="eq-intro">{{ $textes['intro_3'] }}</p>

      <nav class="eq-langues" aria-label="{{ $textes['choisir_langue'] }}">
        <span>{{ $textes['choisir_langue'] }} :</span>
        @foreach ($langues as $code => $nom)
          <a href="{{ route('enquete.show', ['lang' => $code]) }}"
             aria-current="{{ $code === $langue ? 'true' : 'false' }}"
             hreflang="{{ $code }}">{{ $nom }}</a>
        @endforeach
      </nav>
    </div>
  </section>

  <section class="eq-sect">
    <div class="eq-wrap">
      <div class="eq-carte">
        <h2>{{ $textes['pourquoi_titre'] }}</h2>
        <ul class="eq-raisons">
          @foreach (['pourquoi_1', 'pourquoi_2', 'pourquoi_3', 'pourquoi_4'] as $cle)
            <li><i class="fas fa-circle-check"></i> <span>{{ $textes[$cle] }}</span></li>
          @endforeach
        </ul>

        <div class="eq-notes">
          <p class="eq-note"><i class="fas fa-clock"></i> <span>{{ $textes['duree'] }}</span></p>
          <p class="eq-note"><i class="fas fa-globe"></i> <span>{{ $textes['langue_note'] }}</span></p>
          {{-- L'engagement de ne rien demander de secret est tenu par le
               formulaire lui-même : aucun champ n'y touche. --}}
          <p class="eq-note eq-prive"><i class="fas fa-lock"></i> <span>{{ $textes['confidentialite'] }}</span></p>
        </div>
      </div>
    </div>
  </section>

  <section class="eq-sect" style="padding-top:0">
    <form class="eq-wrap" method="POST" action="{{ route('enquete.store') }}" id="eqForm">
      @csrf
      <input type="hidden" name="lang" value="{{ $langue }}">

      @if ($errors->any())
        <div class="eq-alerte">{{ $textes['erreurs'] }}</div>
      @endif

      {{-- ── 1. L'activité ── --}}
      <div class="eq-bloc">
        <p class="eq-bloc-titre"><span class="eq-num">1</span> {{ $textes['s1_titre'] }}</p>

        <div class="eq-champ">
          <label for="entreprise">{{ $textes['q_entreprise'] }}
            <span class="eq-aide">{{ $textes['q_entreprise_aide'] }}</span>
          </label>
          <input type="text" id="entreprise" name="entreprise" value="{{ old('entreprise') }}" autocomplete="organization">
          @error('entreprise')<p class="eq-erreur">{{ $message }}</p>@enderror
        </div>

        <div class="eq-champ">
          <label for="type_activite">{{ $textes['q_type'] }} <span class="eq-req">*</span></label>
          <select id="type_activite" name="type_activite" required>
            <option value="">{{ $textes['choisir'] }}</option>
            @foreach ($types as $cle => $libelle)
              <option value="{{ $cle }}" @selected(old('type_activite') === $cle)>{{ $libelle[$langue] }}</option>
            @endforeach
          </select>
          @error('type_activite')<p class="eq-erreur">{{ $message }}</p>@enderror
        </div>

        {{-- N'apparaît qu'avec « Autre » : une précision demandée à tous
             alourdirait le formulaire sans rien apprendre. --}}
        <div class="eq-champ" id="eqChampAutre" style="display:none">
          <label for="type_activite_autre">{{ $textes['q_type_autre'] }} <span class="eq-req">*</span></label>
          <input type="text" id="type_activite_autre" name="type_activite_autre" value="{{ old('type_activite_autre') }}">
          @error('type_activite_autre')<p class="eq-erreur">{{ $message }}</p>@enderror
        </div>

        <div class="eq-champ">
          <label for="ville">{{ $textes['q_ville'] }} <span class="eq-aide">{{ $textes['facultatif'] }}</span></label>
          <input type="text" id="ville" name="ville" value="{{ old('ville') }}">
        </div>

        <div class="eq-champ">
          <label for="departement">{{ $textes['q_departement'] }} <span class="eq-aide">{{ $textes['facultatif'] }}</span></label>
          <select id="departement" name="departement">
            <option value="">{{ $textes['choisir'] }}</option>
            @foreach ($departements as $cle => $libelle)
              <option value="{{ $cle }}" @selected(old('departement') === $cle)>{{ $libelle[$langue] }}</option>
            @endforeach
          </select>
        </div>

        <fieldset class="eq-champ">
          <legend class="eq-legende">{{ $textes['q_effectif'] }}</legend>
          <div class="eq-choix">
            @foreach ($effectifs as $cle => $libelle)
              <label class="eq-opt">
                <input type="radio" name="effectif" value="{{ $cle }}" @checked(old('effectif') === $cle)>
                <span>{{ $libelle[$langue] }}</span>
              </label>
            @endforeach
          </div>
        </fieldset>

        <fieldset class="eq-champ">
          <legend class="eq-legende">{{ $textes['q_anciennete'] }}</legend>
          <div class="eq-choix">
            @foreach ($anciennetes as $cle => $libelle)
              <label class="eq-opt">
                <input type="radio" name="anciennete" value="{{ $cle }}" @checked(old('anciennete') === $cle)>
                <span>{{ $libelle[$langue] }}</span>
              </label>
            @endforeach
          </div>
        </fieldset>
      </div>

      {{-- ── 2. Comment l'activité est tenue ── --}}
      <div class="eq-bloc">
        <p class="eq-bloc-titre"><span class="eq-num">2</span> {{ $textes['s2_titre'] }}</p>

        @foreach ([
          ['q_outils', 'outils', $outils],
          ['q_encaissement', 'encaissements', $encaissements],
          ['q_presence', 'presences', $presences],
        ] as [$cleTexte, $nom, $liste])
          <fieldset class="eq-champ">
            <legend class="eq-legende">{{ $textes[$cleTexte] }}
              <span class="eq-aide">{{ $textes['multi_aide'] }}</span>
            </legend>
            <div class="eq-choix">
              @foreach ($liste as $cle => $libelle)
                <label class="eq-opt">
                  <input type="checkbox" name="{{ $nom }}[]" value="{{ $cle }}"
                         @checked(in_array($cle, old($nom, []), true))>
                  <span>{{ $libelle[$langue] }}</span>
                </label>
              @endforeach
            </div>
          </fieldset>
        @endforeach

        <fieldset class="eq-champ">
          <legend class="eq-legende">{{ $textes['q_connexion'] }}</legend>
          <div class="eq-choix">
            @foreach ($connexions as $cle => $libelle)
              <label class="eq-opt">
                <input type="radio" name="connexion" value="{{ $cle }}" @checked(old('connexion') === $cle)>
                <span>{{ $libelle[$langue] }}</span>
              </label>
            @endforeach
          </div>
        </fieldset>
      </div>

      {{-- ── 3. Les difficultés : le cœur de l'enquête ── --}}
      <div class="eq-bloc">
        <p class="eq-bloc-titre"><span class="eq-num">3</span> {{ $textes['s3_titre'] }}</p>

        <fieldset class="eq-champ">
          <legend class="eq-legende">{{ $textes['q_difficultes'] }} <span class="eq-req">*</span>
            <span class="eq-aide">{{ $textes['q_difficultes_aide'] }}</span>
          </legend>
          <div class="eq-choix">
            @foreach ($difficultes as $cle => $libelle)
              <label class="eq-opt">
                <input type="checkbox" name="difficultes[]" value="{{ $cle }}"
                       @checked(in_array($cle, old('difficultes', []), true))>
                <span>{{ $libelle[$langue] }}</span>
              </label>
            @endforeach
          </div>
          @error('difficultes')<p class="eq-erreur">{{ $message }}</p>@enderror
        </fieldset>

        @foreach ([
          ['q_temps', 'perte_de_temps'],
          ['q_pertes', 'pertes'],
          ['q_priorite', 'priorite'],
        ] as [$cleTexte, $nom])
          <div class="eq-champ">
            <label for="{{ $nom }}">{{ $textes[$cleTexte] }}
              <span class="eq-aide">{{ $textes['facultatif'] }}</span>
            </label>
            <textarea id="{{ $nom }}" name="{{ $nom }}" maxlength="2000">{{ old($nom) }}</textarea>
            @error($nom)<p class="eq-erreur">{{ $message }}</p>@enderror
          </div>
        @endforeach
      </div>

      {{-- ── 4. Ce qui intéresse ── --}}
      <div class="eq-bloc">
        <p class="eq-bloc-titre"><span class="eq-num">4</span> {{ $textes['s4_titre'] }}</p>

        <fieldset class="eq-champ">
          <legend class="eq-legende">{{ $textes['q_solutions'] }}
            <span class="eq-aide">{{ $textes['multi_aide'] }}</span>
          </legend>
          <div class="eq-choix">
            @foreach ($solutions as $cle => $libelle)
              <label class="eq-opt">
                <input type="checkbox" name="solutions[]" value="{{ $cle }}"
                       @checked(in_array($cle, old('solutions', []), true))>
                <span>{{ $libelle[$langue] }}</span>
              </label>
            @endforeach
          </div>
        </fieldset>

        <fieldset class="eq-champ">
          <legend class="eq-legende">{{ $textes['q_budget'] }}
            <span class="eq-aide">{{ $textes['q_budget_aide'] }}</span>
          </legend>
          <div class="eq-choix">
            @foreach ($budgets as $cle => $libelle)
              <label class="eq-opt">
                <input type="radio" name="budget" value="{{ $cle }}" @checked(old('budget') === $cle)>
                <span>{{ $libelle[$langue] }}</span>
              </label>
            @endforeach
          </div>
        </fieldset>
      </div>

      {{-- ── 5. Contact ── --}}
      <div class="eq-bloc">
        <p class="eq-bloc-titre"><span class="eq-num">5</span> {{ $textes['s5_titre'] }}</p>

        <fieldset class="eq-champ">
          <legend class="eq-legende">{{ $textes['q_contact'] }}</legend>
          {{-- Une case décochée n'est pas envoyée : sans cette valeur, refuser
               le rappel serait impossible à exprimer. --}}
          <input type="hidden" name="souhaite_contact" value="0">
          <div class="eq-choix">
            <label class="eq-opt">
              <input type="radio" name="souhaite_contact" value="1" id="eqContactOui" @checked(old('souhaite_contact') === '1')>
              <span>{{ $textes['q_contact_oui'] }}</span>
            </label>
            <label class="eq-opt">
              <input type="radio" name="souhaite_contact" value="0" @checked(old('souhaite_contact', '0') === '0')>
              <span>{{ $textes['q_contact_non'] }}</span>
            </label>
          </div>
        </fieldset>

        <div id="eqCoordonnees" style="display:none">
          <div class="eq-champ">
            <label for="nom_contact">{{ $textes['q_nom'] }} <span class="eq-req">*</span></label>
            <input type="text" id="nom_contact" name="nom_contact" value="{{ old('nom_contact') }}" autocomplete="name">
            @error('nom_contact')<p class="eq-erreur">{{ $message }}</p>@enderror
          </div>

          <div class="eq-champ">
            <label for="whatsapp">{{ $textes['q_whatsapp'] }} <span class="eq-req">*</span></label>
            <input type="tel" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}"
                   placeholder="+509 0000 0000" autocomplete="tel">
            @error('whatsapp')<p class="eq-erreur">{{ $message }}</p>@enderror
          </div>

          <div class="eq-champ">
            <label for="email">{{ $textes['q_email'] }} <span class="eq-aide">{{ $textes['q_email_aide'] }}</span></label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email">
            @error('email')<p class="eq-erreur">{{ $message }}</p>@enderror
          </div>
        </div>
      </div>

      <button type="submit" class="eq-envoyer" id="eqBouton">{{ $textes['envoyer'] }}</button>
      <p class="eq-conclusion">{{ $textes['conclusion'] }}</p>
    </form>
  </section>
</div>

{{-- JavaScript natif : Alpine n'est chargé que sur le layout ERP. --}}
<script>
(function () {
  var form = document.getElementById('eqForm');
  if (!form) return;

  // ── « Autre » : la précision n'apparaît que si elle est demandée ──
  var type = document.getElementById('type_activite');
  var champAutre = document.getElementById('eqChampAutre');
  var saisieAutre = document.getElementById('type_activite_autre');

  function basculerAutre() {
    var actif = type && type.value === 'autre';
    if (!champAutre) return;
    champAutre.style.display = actif ? '' : 'none';
    if (saisieAutre) {
      saisieAutre.required = actif;
      // Vidé quand il se referme : une valeur laissée derrière serait
      // enregistrée alors que la personne a changé de type d'activité.
      if (!actif) saisieAutre.value = '';
    }
  }

  if (type) type.addEventListener('change', basculerAutre);

  // ── Coordonnées : seulement si la personne veut être rappelée ──
  var coordonnees = document.getElementById('eqCoordonnees');
  var obligatoires = ['nom_contact', 'whatsapp'];

  function basculerContact() {
    var oui = document.getElementById('eqContactOui');
    var actif = !!(oui && oui.checked);
    if (!coordonnees) return;
    coordonnees.style.display = actif ? '' : 'none';
    obligatoires.forEach(function (nom) {
      var champ = document.getElementById(nom);
      if (champ) champ.required = actif;
    });
  }

  form.addEventListener('change', function (e) {
    if (e.target.name === 'souhaite_contact') basculerContact();
  });

  // Au retour d'une erreur de validation, les choix sont déjà cochés : les
  // champs conditionnels doivent réapparaître sans nouveau clic.
  basculerAutre();
  basculerContact();

  // ── Un seul envoi ──
  var bouton = document.getElementById('eqBouton');
  form.addEventListener('submit', function () {
    if (!form.checkValidity()) return;
    if (bouton) {
      bouton.disabled = true;
      bouton.textContent = @json($textes['envoi_en_cours']);
    }
  });
})();
</script>
@endsection

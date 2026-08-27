@extends('layouts.public')

@section('title', ($evenementSelection?->titre ? $evenementSelection->titre . ' — Inscription' : 'Inscription aux événements') . ' — GOVIBE Innovation Hub')
@section('description', $evenementSelection?->sous_titre ?? "Inscrivez-vous aux forums et événements organisés par GOVIBE Innovation Hub.")

@section('head')
@php
  // La couleur d'accent vient de l'événement mis en avant, sinon le rouge de
  // marque. Tout le style de la page passe par ces variables.
  $vedetteHead = $evenementSelection ?? $evenements->first();
  $accent = $vedetteHead?->couleur_accent ?? '#DC2626';
  $accentDark = $vedetteHead?->couleur_foncee ?? '#991b1b';
  $accentRgb = $vedetteHead?->couleur_rgb ?? '220, 38, 38';
@endphp
<style>
  :root {
    --ev-accent: {{ $accent }};
    --ev-accent-dark: {{ $accentDark }};
    --ev-accent-rgb: {{ $accentRgb }};
  }
  .ev-hero {
    background:linear-gradient(135deg,#0a0000 0%,#1a0004 55%,#050505 100%);
    padding:110px 1.5rem 70px; position:relative; overflow:hidden;
  }
  .ev-hero::before {
    content:''; position:absolute; inset:0;
    background:radial-gradient(ellipse 55% 60% at 75% 45%,rgba(var(--ev-accent-rgb),.14) 0%,transparent 70%);
  }
  .ev-hero-inner { position:relative; z-index:1; max-width:820px; margin:0 auto; text-align:center; }

  /* Anton sur les éléments de style : titres, intitulés, boutons. Les champs
     de saisie et les paragraphes gardent la police de lecture — Anton est un
     display, illisible sur du texte long ou dans un input. */
  .ev-tag, .ev-legend, .ev-group label,
  .ev-submit, .ev-group-link, .ev-meta-item {
    font-family:'Anton',sans-serif; font-weight:400;
  }
  .ev-group label { font-size:.86rem; letter-spacing:.04em; color:#0f172a; }
  .ev-legend { font-size:.82rem; letter-spacing:.18em; }
  .ev-submit { font-size:1.1rem; letter-spacing:.06em; }
  .ev-meta-item { letter-spacing:.03em; font-size:.9rem; }
  .ev-group-link { letter-spacing:.05em; font-size:.95rem; }
  .ev-tag { font-size:.8rem; }
  .ev-tag {
    display:inline-flex; align-items:center; gap:.5rem;
    background:rgba(var(--ev-accent-rgb),.15); border:1px solid rgba(var(--ev-accent-rgb),.35);
    color:var(--ev-accent); font-size:.75rem; font-weight:700; letter-spacing:.14em;
    text-transform:uppercase; padding:.35rem 1rem; border-radius:50px; margin-bottom:1.4rem;
  }
  .ev-hero h1 {
    font-family:'Anton',sans-serif; font-size:clamp(2rem,6vw,3.4rem);
    color:#fff; line-height:1.1; margin-bottom:1rem; letter-spacing:.02em;
  }
  .ev-hero h1 span { color:var(--ev-accent); }
  .ev-hero .lead { color:rgba(255,255,255,.72); font-size:1.02rem; line-height:1.75; max-width:640px; margin:0 auto; }
  .ev-meta { display:flex; justify-content:center; gap:1.6rem; flex-wrap:wrap; margin-top:1.8rem; }
  .ev-meta-item { display:flex; align-items:center; gap:.45rem; color:rgba(255,255,255,.6); font-size:.86rem; }
  .ev-meta-item i { color:var(--ev-accent); }

  .ev-section { padding:70px 1.5rem; background:#f8fafc; }
  .ev-wrap { max-width:820px; margin:0 auto; }

  .ev-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:20px;
    padding:2.5rem; box-shadow:0 10px 40px rgba(0,0,0,.05);
  }
  .ev-card h2 {
    font-family:'Anton',sans-serif; font-size:1.5rem; color:#0f172a;
    margin-bottom:.4rem; letter-spacing:.02em;
  }
  .ev-card .hint { color:#64748b; font-size:.9rem; margin-bottom:2rem; }

  .ev-fieldset { border:none; padding:0; margin:0 0 1.8rem; }
  .ev-legend {
    font-size:.72rem; font-weight:700; letter-spacing:.16em; text-transform:uppercase;
    color:var(--ev-accent); padding-bottom:.5rem; margin-bottom:1.1rem;
    border-bottom:1px solid #f1f5f9; width:100%;
  }
  .ev-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
  .ev-row-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; }
  .ev-group { margin-bottom:1rem; }
  .ev-group label { display:block; font-size:.82rem; font-weight:600; color:#374151; margin-bottom:.4rem; }
  .ev-req { color:var(--ev-accent); }
  .ev-group input, .ev-group select, .ev-group textarea {
    width:100%; padding:.7rem .9rem; border:1.5px solid #d1d5db; border-radius:9px;
    font-size:.92rem; color:#0f172a; background:#fff; font-family:inherit;
    transition:border-color .2s, box-shadow .2s; box-sizing:border-box;
  }
  .ev-group input:focus, .ev-group select:focus, .ev-group textarea:focus {
    outline:none; border-color:var(--ev-accent); box-shadow:0 0 0 3px rgba(var(--ev-accent-rgb),.12);
  }
  .ev-group textarea { resize:vertical; min-height:110px; }
  /* Rouge fixe : une erreur reste une erreur, quelle que soit la couleur
     d'accent de l'événement. Une alerte verte induirait en erreur. */
  .ev-err { display:block; font-size:.78rem; color:#b91c1c; margin-top:.3rem; }

  .ev-submit {
    display:flex; align-items:center; justify-content:center; gap:.5rem;
    width:100%; background:linear-gradient(135deg,var(--ev-accent),var(--ev-accent-dark)); color:#fff;
    font-weight:700; font-size:1rem; padding:.95rem 1.5rem; border:none;
    border-radius:10px; cursor:pointer; transition:opacity .2s, transform .2s; margin-top:.6rem;
  }
  .ev-submit:hover { opacity:.92; transform:translateY(-2px); }

  .ev-alert {
    border-radius:10px; padding:1rem 1.2rem; font-size:.88rem; margin-bottom:1.5rem;
    background:rgba(185,28,28,.07); border:1px solid rgba(185,28,28,.28); color:#b91c1c;
  }
  .ev-alert ul { margin:.5rem 0 0 1.1rem; padding:0; }

  .ev-closed {
    background:#fff; border:1px solid #e5e7eb; border-left:4px solid var(--ev-accent);
    border-radius:12px; padding:1.5rem; color:#475569; font-size:.92rem; line-height:1.7;
  }

  /* Lien du groupe proposé aussi avant l'inscription. */
  .ev-group-link {
    display:inline-flex; align-items:center; gap:.5rem;
    border:1.5px solid var(--ev-accent); color:var(--ev-accent); background:#fff;
    font-weight:700; font-size:.9rem; padding:.7rem 1.4rem;
    border-radius:50px; margin-top:1.5rem; transition:background .2s, color .2s;
  }
  .ev-group-link:hover { background:var(--ev-accent); color:#fff; }

  @media (max-width:768px) {
    .ev-hero { padding:80px 1.2rem 50px; }
    .ev-section { padding:44px 1rem; }
    .ev-card { padding:1.6rem 1.2rem; }
    .ev-row, .ev-row-3 { grid-template-columns:1fr; gap:0; }
    .ev-meta { gap:1rem; }
  }
</style>
@endsection

@section('content')

@php
  // Événement mis en avant : celui de l'URL, sinon le premier actif.
  $vedette = $evenementSelection ?? $evenements->first();
@endphp

<section class="ev-hero">
  <div class="ev-hero-inner">
    <span class="ev-tag">Inscription</span>

    @if ($vedette)
      <h1>{{ $vedette->titre }}</h1>
      @if ($vedette->sous_titre)
        <p class="lead">{{ $vedette->sous_titre }}</p>
      @endif
      <div class="ev-meta">
        @if ($vedette->dates_libelle)
          <span class="ev-meta-item"><i class="fas fa-calendar-day"></i> {{ $vedette->dates_libelle }}</span>
        @endif
        @if ($vedette->lieu)
          <span class="ev-meta-item"><i class="fas fa-map-marker-alt"></i> {{ $vedette->lieu }}</span>
        @endif
        <span class="ev-meta-item"><i class="fas fa-user-check"></i> Inscription gratuite</span>
      </div>
    @else
      <h1>Nos <span>événements</span></h1>
      <p class="lead">Aucun événement n'est ouvert aux inscriptions pour le moment. Revenez bientôt.</p>
    @endif
  </div>
</section>

<section class="ev-section">
  <div class="ev-wrap">

    @if ($vedette && $vedette->description)
      <div class="ev-card slide-up" style="margin-bottom:1.8rem;">
        <h2>À propos de l'événement</h2>
        <p style="color:#475569; line-height:1.85; font-size:.95rem; margin-top:.8rem;">{{ $vedette->description }}</p>
        @if ($vedette->whatsapp_group_url)
          <a href="{{ $vedette->whatsapp_group_url }}" target="_blank" rel="noopener" class="ev-group-link">
            <i class="fab fa-whatsapp"></i> Rejoindre le groupe
          </a>
        @endif
      </div>
    @endif

    @if ($evenements->isEmpty())
      <div class="ev-closed slide-up">
        Les inscriptions sont actuellement fermées. Pour être informé de la prochaine édition,
        écrivez-nous à <a href="mailto:contact@govibeht.com" style="color:var(--ev-accent);font-weight:700;">contact@govibeht.com</a>.
      </div>
    @else
      <div class="ev-card slide-up">
        <h2>Formulaire d'inscription</h2>
        <p class="hint">Les champs marqués d'un astérisque sont obligatoires.</p>

        @if ($errors->any())
          <div class="ev-alert">
            <strong>Veuillez corriger les points suivants :</strong>
            <ul>
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="POST" action="{{ route('evenements.store') }}">
          @csrf

          <fieldset class="ev-fieldset">
            <legend class="ev-legend">Événement</legend>
            <div class="ev-group">
              <label for="evenement_id">Sélectionnez l'événement <span class="ev-req">*</span></label>
              <select id="evenement_id" name="evenement_id" required>
                <option value="">Choisir un événement</option>
                @foreach ($evenements as $ev)
                  <option value="{{ $ev->id }}"
                    @disabled(! $ev->inscriptions_ouvertes)
                    @selected(old('evenement_id', $evenementSelection?->id) == $ev->id)>
                    {{ $ev->titre }}@if (! $ev->inscriptions_ouvertes) (inscriptions fermées) @endif
                  </option>
                @endforeach
              </select>
              @error('evenement_id')<span class="ev-err">{{ $message }}</span>@enderror
            </div>
          </fieldset>

          <fieldset class="ev-fieldset">
            <legend class="ev-legend">Identité</legend>
            <div class="ev-row">
              <div class="ev-group">
                <label for="prenom">Prénom <span class="ev-req">*</span></label>
                <input type="text" id="prenom" name="prenom" value="{{ old('prenom') }}" required>
                @error('prenom')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
              <div class="ev-group">
                <label for="nom">Nom <span class="ev-req">*</span></label>
                <input type="text" id="nom" name="nom" value="{{ old('nom') }}" required>
                @error('nom')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
            </div>
            <div class="ev-row">
              <div class="ev-group">
                <label for="sexe">Sexe</label>
                <select id="sexe" name="sexe">
                  <option value="">Non précisé</option>
                  @foreach (\App\Models\EvenementReservation::sexes() as $v => $l)
                    <option value="{{ $v }}" @selected(old('sexe') === $v)>{{ $l }}</option>
                  @endforeach
                </select>
                @error('sexe')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
              <div class="ev-group">
                <label for="situation_matrimoniale">Situation matrimoniale</label>
                <select id="situation_matrimoniale" name="situation_matrimoniale">
                  <option value="">Non précisé</option>
                  @foreach (\App\Models\EvenementReservation::situationsMatrimoniales() as $v => $l)
                    <option value="{{ $v }}" @selected(old('situation_matrimoniale') === $v)>{{ $l }}</option>
                  @endforeach
                </select>
                @error('situation_matrimoniale')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
            </div>
          </fieldset>

          <fieldset class="ev-fieldset">
            <legend class="ev-legend">Contact</legend>
            <div class="ev-group">
              <label for="email">Adresse email <span class="ev-req">*</span></label>
              <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="votre@email.com" required>
              @error('email')<span class="ev-err">{{ $message }}</span>@enderror
            </div>
            <div class="ev-row">
              <div class="ev-group">
                <label for="whatsapp">Numéro WhatsApp <span class="ev-req">*</span></label>
                <input type="tel" id="whatsapp" name="whatsapp" value="{{ old('whatsapp') }}" placeholder="+509 0000 0000" required>
                @error('whatsapp')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
              <div class="ev-group">
                <label for="telephone">Autre numéro de téléphone</label>
                <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}" placeholder="+509 0000 0000">
                @error('telephone')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
            </div>
          </fieldset>

          <fieldset class="ev-fieldset">
            <legend class="ev-legend">Localisation</legend>
            <div class="ev-row-3">
              <div class="ev-group">
                <label for="pays">Pays <span class="ev-req">*</span></label>
                <input type="text" id="pays" name="pays" value="{{ old('pays', 'Haïti') }}" required>
                @error('pays')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
              <div class="ev-group">
                <label for="ville">Ville <span class="ev-req">*</span></label>
                <input type="text" id="ville" name="ville" value="{{ old('ville') }}" required>
                @error('ville')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
              <div class="ev-group">
                <label for="commune">Commune</label>
                <input type="text" id="commune" name="commune" value="{{ old('commune') }}">
                @error('commune')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
            </div>
          </fieldset>

          <fieldset class="ev-fieldset">
            <legend class="ev-legend">Situation professionnelle</legend>
            <div class="ev-row">
              <div class="ev-group">
                <label for="profession">Profession</label>
                <input type="text" id="profession" name="profession" value="{{ old('profession') }}" placeholder="Infirmière, étudiante en soins infirmiers...">
                @error('profession')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
              <div class="ev-group">
                <label for="statut_actuel">Statut actuel</label>
                <select id="statut_actuel" name="statut_actuel">
                  <option value="">Non précisé</option>
                  @foreach (\App\Models\EvenementReservation::statutsActuels() as $v => $l)
                    <option value="{{ $v }}" @selected(old('statut_actuel') === $v)>{{ $l }}</option>
                  @endforeach
                </select>
                @error('statut_actuel')<span class="ev-err">{{ $message }}</span>@enderror
              </div>
            </div>
          </fieldset>

          <fieldset class="ev-fieldset">
            <legend class="ev-legend">Motivation</legend>
            <div class="ev-group">
              <label for="motivation">Pourquoi souhaitez-vous participer ?</label>
              <textarea id="motivation" name="motivation" placeholder="Ce que vous attendez de cette rencontre, les sujets qui vous intéressent...">{{ old('motivation') }}</textarea>
              @error('motivation')<span class="ev-err">{{ $message }}</span>@enderror
            </div>
          </fieldset>

          <button type="submit" class="ev-submit">
            <i class="fas fa-paper-plane"></i> Confirmer mon inscription
          </button>
        </form>
      </div>
    @endif

  </div>
</section>

@endsection

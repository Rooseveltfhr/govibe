@extends('layouts.public')

@section('title', $formation->titre.' — GOVIBE Innovation Hub')
@section('description', "Inscription à la {$formation->titre} de GOVIBE : {$formation->prix_affiche}, en présentiel ou en ligne. Places limitées.")

@section('head')
<style>
  :root {
    --fm: {{ $formation->couleur }};
    --fm-fonce: {{ $formation->couleur_foncee }};
  }
  .fs-hero {
    background: linear-gradient(135deg, #080002 0%, var(--fm-fonce) 55%, #050505 100%);
    padding: 82px 1.3rem 52px; text-align: center; position: relative; overflow: hidden;
  }
  .fs-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 55% 60% at 50% 35%, rgba(255,255,255,.09) 0%, transparent 70%);
  }
  .fs-hero-in { position: relative; z-index: 1; max-width: 660px; margin: 0 auto; }
  .fs-tag {
    display: inline-flex; align-items: center; gap: .45rem;
    background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.25);
    color: #fff; font-family: 'Anton', sans-serif; font-size: .74rem;
    letter-spacing: .16em; text-transform: uppercase;
    padding: .34rem .95rem; border-radius: 50px; margin-bottom: 1.1rem;
  }
  .fs-hero h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(2rem, 7vw, 3.2rem);
    color: #fff; line-height: 1.02; letter-spacing: .01em; margin: 0 0 .5rem;
    text-transform: uppercase; text-wrap: balance;
  }
  .fs-sous {
    font-family: 'Anton', sans-serif; font-size: clamp(1.1rem, 3.6vw, 1.6rem);
    color: #fff; letter-spacing: .02em; margin: 0 0 1.2rem;
    display: inline-block; background: var(--fm); padding: .1rem .9rem; transform: skewX(-6deg);
  }
  .fs-desc { color: rgba(255,255,255,.74); line-height: 1.72; font-size: .95rem; margin: 0; }

  /* Les faits de l'affiche, en une bande lisible au pouce. */
  .fs-faits {
    background: #0f0f10; padding: 1.5rem 1.3rem;
  }
  .fs-faits-in {
    max-width: 900px; margin: 0 auto;
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.1rem;
  }
  .fs-fait { text-align: center; }
  .fs-fait i {
    display: block; color: var(--fm); font-size: 1.15rem; margin-bottom: .4rem;
  }
  .fs-fait span {
    display: block; font-size: .68rem; letter-spacing: .13em; text-transform: uppercase;
    color: rgba(255,255,255,.42); margin-bottom: .18rem;
  }
  .fs-fait strong { color: #fff; font-size: .89rem; line-height: 1.4; }

  .fs-sect { background: #f8fafc; padding: 42px 1.1rem 72px; }
  .fs-wrap { max-width: 620px; margin: 0 auto; }

  .fs-modules {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 18px;
    padding: 1.4rem 1.5rem; margin-bottom: 1.1rem;
  }
  .fs-modules h2 {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1rem;
    color: #0f172a; letter-spacing: .05em; text-transform: uppercase; margin: 0 0 .9rem;
  }
  .fs-modules ul { list-style: none; padding: 0; margin: 0; }
  .fs-modules li {
    display: flex; gap: .6rem; align-items: flex-start;
    color: #334155; font-size: .93rem; line-height: 1.55; padding: .32rem 0;
  }
  .fs-modules li i { color: var(--fm); font-size: .55rem; margin-top: .55rem; flex-shrink: 0; }

  .fs-carte { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 1.6rem 1.5rem; }
  .fs-carte h2 {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1.15rem;
    color: #0f172a; letter-spacing: .03em; margin: 0 0 .3rem;
  }
  .fs-carte > p.fs-intro { color: #64748b; font-size: .88rem; line-height: 1.65; margin: 0 0 1.4rem; }

  .fs-champ { margin-bottom: 1.2rem; }
  .fs-champ label {
    display: block; font-family: 'Anton', sans-serif; font-weight: 400;
    font-size: .82rem; letter-spacing: .05em; text-transform: uppercase;
    color: #334155; margin-bottom: .45rem;
  }
  .fs-aide {
    display: block; font-family: 'DM Sans', sans-serif; text-transform: none;
    letter-spacing: 0; font-size: .78rem; color: #94a3b8; margin-top: .12rem;
  }
  .fs-req { color: var(--fm); }
  /* 16px : en dessous, iOS zoome au focus. */
  .fs-champ input[type=text], .fs-champ input[type=tel] {
    width: 100%; font-size: 16px; padding: .78rem .95rem; border: 1px solid #d1d5db;
    border-radius: 12px; background: #fff; color: #0f172a; font-family: inherit;
  }
  .fs-champ input:focus { outline: none; border-color: var(--fm); box-shadow: 0 0 0 3px rgba(0,0,0,.06); }

  /* Choix en grandes cibles : c'est un formulaire de téléphone. */
  .fs-choix { display: grid; gap: .55rem; }
  .fs-choix.fs-deux { grid-template-columns: 1fr 1fr; }
  .fs-opt { position: relative; }
  .fs-opt input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .fs-opt span {
    display: flex; align-items: center; gap: .6rem; height: 100%;
    border: 1px solid #d1d5db; border-radius: 12px; padding: .8rem .9rem;
    font-size: .92rem; color: #334155; cursor: pointer; background: #fff; line-height: 1.35;
  }
  .fs-opt span i { color: #94a3b8; width: 1.1rem; text-align: center; flex-shrink: 0; }
  .fs-opt span img { width: 24px; height: 24px; object-fit: contain; flex-shrink: 0; }
  .fs-opt-init {
    width: 24px; height: 24px; border-radius: 6px; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center;
    font-size: .6rem; font-weight: 700; color: #64748b; flex-shrink: 0;
  }
  .fs-opt input:checked + span {
    border-color: var(--fm); background: color-mix(in srgb, var(--fm) 8%, #fff); font-weight: 600;
  }
  .fs-opt input:checked + span i { color: var(--fm); }
  .fs-opt input:focus-visible + span { box-shadow: 0 0 0 3px rgba(0,0,0,.12); }
  .fs-opt small { display: block; color: #94a3b8; font-weight: 400; font-size: .76rem; }

  /* Coordonnées du moyen choisi : payer sans quitter la page. */
  .fs-coord {
    display: none; margin-top: .7rem; border: 1px solid var(--fm);
    background: color-mix(in srgb, var(--fm) 6%, #fff);
    border-radius: 12px; padding: .95rem 1.1rem;
  }
  .fs-coord.on { display: block; }
  .fs-coord-tete {
    font-family: 'Anton', sans-serif; font-size: .78rem; letter-spacing: .1em;
    text-transform: uppercase; color: var(--fm-fonce); margin: 0 0 .5rem;
  }
  .fs-coord-ligne { display: flex; justify-content: space-between; gap: .8rem; font-size: .86rem; padding: .2rem 0; }
  .fs-coord-ligne span { color: #64748b; }
  .fs-coord-ligne strong { color: #0f172a; text-align: right; word-break: break-all; }
  .fs-coord-valeur {
    display: block; font-family: 'Anton', sans-serif; letter-spacing: .05em;
    font-size: 1.05rem; color: #0f172a; margin-top: .1rem; word-break: break-all;
  }
  .fs-coord img.fs-qr { display: block; max-width: 150px; margin: .8rem auto 0; border-radius: 10px; }
  .fs-coord a.fs-lien {
    display: block; text-align: center; margin-top: .7rem; background: var(--fm); color: #fff;
    font-family: 'Anton', sans-serif; letter-spacing: .04em; font-size: .92rem;
    padding: .65rem 1rem; border-radius: 50px; text-decoration: none;
  }
  .fs-coord p.fs-instr { color: #64748b; font-size: .82rem; line-height: 1.6; margin: .6rem 0 0; }
  .fs-coord p.fs-manque { color: #92400e; font-size: .83rem; line-height: 1.6; margin: 0; }

  .fs-depot {
    border: 2px dashed #cbd5e1; border-radius: 14px; padding: 1.5rem 1rem;
    text-align: center; background: #f8fafc; cursor: pointer; display: block; position: relative;
  }
  .fs-depot:hover { border-color: var(--fm); }
  .fs-depot i { font-size: 1.7rem; color: var(--fm); display: block; margin-bottom: .45rem; }
  .fs-depot strong { display: block; color: #0f172a; font-size: .93rem; margin-bottom: .18rem; }
  .fs-depot small { color: #94a3b8; font-size: .79rem; }
  .fs-depot input[type=file] { position: absolute; width: 1px; height: 1px; opacity: 0; }
  .fs-apercu { margin-top: .8rem; display: none; }
  .fs-apercu.on { display: block; }
  .fs-apercu img { max-width: 100%; max-height: 260px; border-radius: 12px; border: 1px solid #e5e7eb; display: block; margin: 0 auto; }
  .fs-fichier {
    display: flex; align-items: center; justify-content: space-between; gap: .8rem;
    margin-top: .55rem; background: #f1f5f9; border-radius: 10px; padding: .55rem .8rem;
    font-size: .83rem; color: #475569;
  }
  .fs-retirer { background: none; border: none; color: var(--fm); cursor: pointer; font-size: .83rem; }

  .fs-envoyer {
    width: 100%; background: linear-gradient(135deg, var(--fm), var(--fm-fonce)); color: #fff;
    border: none; border-radius: 50px; padding: 1rem 1.5rem; cursor: pointer;
    font-family: 'Anton', sans-serif; font-size: 1.05rem; letter-spacing: .05em; margin-top: .4rem;
  }
  .fs-envoyer:hover { opacity: .93; }
  .fs-envoyer:disabled { opacity: .6; cursor: default; }
  .fs-note-fin { text-align: center; font-size: .79rem; color: #94a3b8; line-height: 1.6; margin: .8rem 0 0; }

  .fs-erreurs {
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
    padding: .95rem 1.15rem; margin-bottom: 1.2rem; color: #991b1b; font-size: .87rem;
  }
  .fs-erreurs ul { margin: .4rem 0 0; padding-left: 1.1rem; }
  .fs-close {
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 14px;
    padding: 1.2rem 1.4rem; text-align: center; color: #92400e; line-height: 1.7;
  }

  @media (max-width: 700px) {
    .fs-faits-in { grid-template-columns: repeat(2, 1fr); gap: 1.2rem; }
  }
  @media (max-width: 460px) {
    .fs-choix.fs-deux { grid-template-columns: 1fr; }
    .fs-carte { padding: 1.2rem 1.1rem; }
    .fs-hero { padding: 64px 1.1rem 42px; }
  }
</style>
@endsection

@section('content')

<section class="fs-hero">
  <div class="fs-hero-in">
    <span class="fs-tag"><i class="fas fa-bolt"></i> GOVIBE</span>
    <h1>{{ $formation->titre }}</h1>
    @if ($formation->sous_titre)
      <p><span class="fs-sous">{{ $formation->sous_titre }}</span></p>
    @endif
    @if ($formation->description)
      <p class="fs-desc">{{ $formation->description }}</p>
    @endif
  </div>
</section>

<section class="fs-faits">
  <div class="fs-faits-in">
    @if ($formation->date_texte)
      <div class="fs-fait">
        <i class="fas fa-calendar-check"></i>
        <span>Date</span>
        <strong>{{ $formation->date_texte }}@if ($formation->heure_texte) — {{ $formation->heure_texte }}@endif</strong>
      </div>
    @endif
    <div class="fs-fait">
      <i class="fas fa-coins"></i>
      <span>Prix</span>
      <strong>{{ $formation->prix_affiche }}</strong>
    </div>
    @if ($formation->lieu_presentiel)
      <div class="fs-fait">
        <i class="fas fa-location-dot"></i>
        <span>Présentiel</span>
        <strong>{{ $formation->lieu_presentiel }}</strong>
      </div>
    @endif
    @if ($formation->precision_online)
      <div class="fs-fait">
        <i class="fas fa-laptop"></i>
        <span>En ligne</span>
        <strong>{{ $formation->precision_online }}</strong>
      </div>
    @endif
  </div>
</section>

<section class="fs-sect">
  <div class="fs-wrap">

    @if ($formation->modules)
      <div class="fs-modules">
        <h2>Modules</h2>
        <ul>
          @foreach ($formation->modules as $m)
            <li><i class="fas fa-circle"></i> {{ $m }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if (! $formation->accepte_inscriptions)
      <div class="fs-close">
        <strong>Les inscriptions sont closes pour cette formation.</strong><br>
        @if ($formation->whatsapp_contact)
          Écrivez-nous sur WhatsApp au
          <a href="https://wa.me/{{ preg_replace('/\D+/', '', $formation->whatsapp_contact) }}" target="_blank" rel="noopener">
            +{{ $formation->whatsapp_contact }}</a> pour la prochaine session.
        @endif
      </div>
    @else

      @if ($errors->any())
        <div class="fs-erreurs">
          <strong>Vérifiez ces points :</strong>
          <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
      @endif

      <form method="POST" action="{{ route('formations.store', $formation) }}" enctype="multipart/form-data" class="fs-carte" id="fsForm">
        @csrf

        <h2>Je m'inscris</h2>
        <p class="fs-intro">
          @if ($formation->places_limitees) Les places sont limitées. @endif
          Payez {{ $formation->prix_affiche }} par le moyen de votre choix, puis joignez la capture du paiement.
        </p>

        <div class="fs-champ">
          <label for="nom_complet">Nom complet <span class="fs-req">*</span></label>
          <input type="text" name="nom_complet" id="nom_complet" value="{{ old('nom_complet') }}" required maxlength="150" autocomplete="name">
        </div>

        <div class="fs-champ">
          <label for="whatsapp">Numéro WhatsApp <span class="fs-req">*</span>
            <span class="fs-aide">C'est par là que nous vous envoyons le lien et les rappels</span>
          </label>
          <input type="tel" name="whatsapp" id="whatsapp" value="{{ old('whatsapp') }}" required maxlength="40" placeholder="+509 ..." autocomplete="tel">
        </div>

        <div class="fs-champ">
          <label>Comment voulez-vous suivre ? <span class="fs-req">*</span></label>
          <div class="fs-choix fs-deux">
            @foreach ($formation->modes_lisibles as $v => $l)
              <label class="fs-opt">
                <input type="radio" name="mode" value="{{ $v }}" @checked(old('mode') === $v) required>
                <span>
                  <i class="fas {{ $v === 'presentiel' ? 'fa-location-dot' : 'fa-laptop' }}"></i>
                  <span>
                    {{ $l }}
                    <small>{{ $v === 'presentiel' ? $formation->lieu_presentiel : $formation->precision_online }}</small>
                  </span>
                </span>
              </label>
            @endforeach
          </div>
        </div>

        <div class="fs-champ">
          <label>Par quel moyen payez-vous ? <span class="fs-req">*</span>
            <span class="fs-aide">Les coordonnées s'affichent dès que vous choisissez</span>
          </label>

          @if ($passerelles->isEmpty())
            <p style="color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:.8rem 1rem;font-size:.87rem;line-height:1.6;margin:0">
              Les moyens de paiement ne sont pas encore publiés. Écrivez-nous sur WhatsApp pour régler votre place.
            </p>
          @else
            <div class="fs-choix">
              @foreach ($passerelles as $p)
                <label class="fs-opt">
                  <input type="radio" name="moyen_paiement" value="{{ $p->code }}" @checked(old('moyen_paiement') === $p->code)
                         data-nom="{{ $p->nom }}"
                         data-titulaire="{{ $p->titulaire }}"
                         data-valeur="{{ $p->valeur_a_copier }}"
                         data-lien="{{ $p->lien_paiement }}"
                         data-qr="{{ $p->qr_code_url }}"
                         data-instructions="{{ $p->instructions }}" required>
                  <span>
                    @if ($p->logo_url)
                      <img src="{{ $p->logo_url }}" alt="" loading="lazy">
                    @else
                      <span class="fs-opt-init">{{ $p->initiales }}</span>
                    @endif
                    {{ $p->nom }}
                  </span>
                </label>
              @endforeach
            </div>

            <div class="fs-coord" id="fsCoord">
              <p class="fs-coord-tete" id="fsCoordTitre"></p>
              <div id="fsCoordCorps"></div>
            </div>
          @endif
        </div>

        <div class="fs-champ">
          <label for="preuve">Preuve de paiement <span class="fs-req">*</span>
            <span class="fs-aide">La capture d'écran de votre transaction</span>
          </label>
          <label class="fs-depot" for="preuve">
            <i class="fas fa-cloud-arrow-up"></i>
            <strong>Choisir la capture</strong>
            <small>JPG, PNG, WEBP, HEIC ou PDF — 8 Mo maximum</small>
            <input type="file" name="preuve" id="preuve" accept="image/*,application/pdf" required>
          </label>
          <div class="fs-apercu" id="fsApercu">
            <img id="fsImage" alt="Aperçu de la capture">
            <div class="fs-fichier">
              <span id="fsNom"></span>
              <button type="button" class="fs-retirer" id="fsRetirer">Retirer</button>
            </div>
          </div>
        </div>

        <button type="submit" class="fs-envoyer" id="fsBouton">Envoyer mon inscription</button>
        <p class="fs-note-fin">
          Vous recevrez une référence et un bouton pour envoyer votre inscription
          sur WhatsApp, afin que l'équipe vérifie votre paiement.
        </p>
      </form>
    @endif
  </div>
</section>

{{-- JavaScript natif : Alpine n'est chargé que sur le layout ERP. --}}
<script>
(function () {
  var form = document.getElementById('fsForm');
  if (!form) return;

  // ── Coordonnées du moyen choisi ──────────────────────
  var coord  = document.getElementById('fsCoord');
  var titre  = document.getElementById('fsCoordTitre');
  var corps  = document.getElementById('fsCoordCorps');

  function ligne(libelle, valeur) {
    var d = document.createElement('div');
    d.className = 'fs-coord-ligne';
    var s = document.createElement('span'); s.textContent = libelle;
    var b = document.createElement('strong'); b.textContent = valeur;
    d.appendChild(s); d.appendChild(b);
    return d;
  }

  function afficherCoordonnees(el) {
    if (!coord) return;
    var d = el.dataset;

    titre.textContent = 'Payer par ' + d.nom;
    corps.textContent = '';

    if (d.titulaire) corps.appendChild(ligne('Au nom de', d.titulaire));

    if (d.valeur) {
      var lab = document.createElement('span');
      lab.style.cssText = 'color:#64748b;font-size:.8rem';
      lab.textContent = 'Numéro / adresse';
      var val = document.createElement('span');
      val.className = 'fs-coord-valeur';
      val.textContent = d.valeur;
      corps.appendChild(lab); corps.appendChild(val);
    }

    if (d.lien) {
      var a = document.createElement('a');
      a.className = 'fs-lien'; a.href = d.lien;
      a.target = '_blank'; a.rel = 'noopener';
      a.textContent = 'Payer avec ' + d.nom;
      corps.appendChild(a);
    }

    if (d.instructions) {
      var p = document.createElement('p');
      p.className = 'fs-instr'; p.textContent = d.instructions;
      corps.appendChild(p);
    }

    if (d.qr) {
      var img = document.createElement('img');
      img.className = 'fs-qr'; img.src = d.qr; img.loading = 'lazy';
      img.alt = 'QR code ' + d.nom;
      corps.appendChild(img);
    }

    // Un moyen sans coordonnées laisserait le participant sans savoir où
    // payer : on le dit plutôt que d'afficher un cadre vide.
    if (!d.titulaire && !d.valeur && !d.lien) {
      var m = document.createElement('p');
      m.className = 'fs-manque';
      m.textContent = "Les coordonnées de ce moyen ne sont pas encore publiées. Écrivez-nous sur WhatsApp avant de payer.";
      corps.appendChild(m);
    }

    coord.classList.add('on');
  }

  form.addEventListener('change', function (e) {
    if (e.target.name === 'moyen_paiement') afficherCoordonnees(e.target);
  });

  var dejaChoisi = form.querySelector('input[name="moyen_paiement"]:checked');
  if (dejaChoisi) afficherCoordonnees(dejaChoisi);

  // ── Aperçu de la capture ─────────────────────────────
  var input   = document.getElementById('preuve');
  var apercu  = document.getElementById('fsApercu');
  var image   = document.getElementById('fsImage');
  var nom     = document.getElementById('fsNom');
  var retirer = document.getElementById('fsRetirer');

  function lisible(o) {
    return o >= 1048576 ? (o / 1048576).toFixed(1) + ' Mo'
                        : Math.max(1, Math.round(o / 1024)) + ' Ko';
  }

  input.addEventListener('change', function () {
    var f = input.files && input.files[0];
    if (!f) { apercu.classList.remove('on'); return; }

    nom.textContent = f.name + ' — ' + lisible(f.size);
    // Un PDF n'a pas de vignette : on garde la ligne, sans image.
    if (f.type === 'application/pdf') {
      image.style.display = 'none';
    } else {
      image.style.display = 'block';
      image.src = URL.createObjectURL(f);
    }
    apercu.classList.add('on');
  });

  retirer.addEventListener('click', function () {
    input.value = '';
    apercu.classList.remove('on');
  });

  // ── Envoi ────────────────────────────────────────────
  var bouton = document.getElementById('fsBouton');
  form.addEventListener('submit', function () {
    bouton.disabled = true;
    bouton.textContent = 'Envoi en cours...';
  });
})();
</script>

@endsection

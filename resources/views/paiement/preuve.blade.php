@extends('layouts.public')

@section('title', 'Envoyer une preuve de paiement — GOVIBE')
@section('description', "Envoyez la capture d'écran de votre paiement à GOVIBE Innovation Hub et recevez votre numéro de suivi.")

@section('head')
<style>
  .pv-hero {
    background: linear-gradient(135deg, #0a0000 0%, #1a0004 55%, #050505 100%);
    padding: 90px 1.5rem 50px; text-align: center;
  }
  .pv-hero h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.9rem, 5.5vw, 2.8rem);
    color: #fff; margin: 0 0 .8rem; letter-spacing: .02em;
  }
  .pv-hero h1 span { color: #DC2626; }
  .pv-hero p { color: rgba(255,255,255,.72); max-width: 560px; margin: 0 auto; line-height: 1.7; font-size: .98rem; }

  .pv-sect { background: #f8fafc; padding: 46px 1.2rem 76px; }
  .pv-wrap { max-width: 640px; margin: 0 auto; }
  .pv-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 1.8rem; }

  .pv-champ { margin-bottom: 1.15rem; }
  .pv-champ label {
    display: block; font-family: 'Anton', sans-serif; font-weight: 400;
    font-size: .82rem; letter-spacing: .06em; text-transform: uppercase;
    color: #334155; margin-bottom: .4rem;
  }
  .pv-champ .pv-aide { display: block; font-family: 'DM Sans', sans-serif; text-transform: none;
    letter-spacing: 0; font-size: .78rem; color: #94a3b8; margin-top: .15rem; }
  /* 16px : en dessous, iOS zoome sur le champ au focus. */
  .pv-champ input[type=text], .pv-champ input[type=tel], .pv-champ input[type=email],
  .pv-champ input[type=number], .pv-champ select, .pv-champ textarea {
    width: 100%; font-size: 16px; padding: .72rem .9rem; border: 1px solid #d1d5db;
    border-radius: 11px; background: #fff; color: #0f172a; font-family: inherit;
  }
  .pv-champ input:focus, .pv-champ select:focus, .pv-champ textarea:focus {
    outline: none; border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220,38,38,.12);
  }
  .pv-champ textarea { min-height: 90px; resize: vertical; }
  .pv-duo { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; }
  .pv-req { color: #DC2626; }

  .pv-depot {
    border: 2px dashed #cbd5e1; border-radius: 14px; padding: 1.6rem 1rem;
    text-align: center; background: #f8fafc; cursor: pointer; display: block;
  }
  .pv-depot:hover { border-color: #DC2626; background: #fef2f2; }
  .pv-depot i { font-size: 1.9rem; color: #DC2626; display: block; margin-bottom: .5rem; }
  .pv-depot strong { display: block; color: #0f172a; font-size: .95rem; margin-bottom: .2rem; }
  .pv-depot span { color: #94a3b8; font-size: .8rem; }
  .pv-depot input[type=file] { position: absolute; width: 1px; height: 1px; opacity: 0; }

  .pv-apercu { margin-top: .9rem; display: none; }
  .pv-apercu.on { display: block; }
  .pv-apercu img {
    max-width: 100%; max-height: 300px; border-radius: 12px; border: 1px solid #e5e7eb; display: block;
  }
  .pv-fichier {
    display: flex; align-items: center; justify-content: space-between; gap: .8rem;
    margin-top: .6rem; background: #f1f5f9; border-radius: 10px; padding: .6rem .85rem;
    font-size: .84rem; color: #475569;
  }
  .pv-retirer { background: none; border: none; color: #DC2626; cursor: pointer; font-size: .84rem; }

  .pv-envoyer {
    width: 100%; background: linear-gradient(135deg, #DC2626, #991b1b); color: #fff;
    border: none; border-radius: 50px; padding: .95rem 1.5rem; cursor: pointer;
    font-family: 'Anton', sans-serif; font-size: 1.05rem; letter-spacing: .05em; margin-top: .5rem;
  }
  .pv-envoyer:hover { opacity: .93; }
  .pv-envoyer:disabled { opacity: .6; cursor: default; }

  .pv-note {
    background: #fff; border: 1px solid #e5e7eb; border-left: 4px solid #DC2626;
    border-radius: 12px; padding: 1.1rem 1.3rem; margin-bottom: 1.3rem;
  }
  .pv-note p { margin: 0; color: #64748b; font-size: .88rem; line-height: 1.7; }
  .pv-erreurs {
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
    padding: .9rem 1.1rem; margin-bottom: 1.2rem; color: #991b1b; font-size: .88rem;
  }
  .pv-erreurs ul { margin: .4rem 0 0; padding-left: 1.1rem; }
  .pv-retour { display: inline-block; margin-top: 1.2rem; color: #64748b; font-size: .88rem; text-decoration: none; }
  .pv-retour:hover { color: #DC2626; }

  @media (max-width: 560px) {
    .pv-duo { grid-template-columns: 1fr; }
    .pv-card { padding: 1.3rem; }
    .pv-hero { padding: 66px 1.2rem 40px; }
  }
</style>
@endsection

@section('content')

<section class="pv-hero">
  <h1>Envoyer la <span>preuve</span> de paiement</h1>
  <p>Joignez la capture d'écran de votre transaction. Vous recevrez un numéro de suivi
     à citer sur WhatsApp pour que votre versement soit rattaché à votre dossier.</p>
</section>

<section class="pv-sect">
  <div class="pv-wrap">

    <div class="pv-note">
      <p>
        La capture n'est visible que par l'équipe GOVIBE. Elle n'apparaît sur aucune page publique.
      </p>
    </div>

    @if ($errors->any())
      <div class="pv-erreurs">
        <strong>Vérifiez ces points :</strong>
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('paiement.preuve.store') }}" enctype="multipart/form-data" class="pv-card" id="pvForm">
      @csrf

      <div class="pv-champ">
        <label for="capture">Capture du paiement <span class="pv-req">*</span></label>
        <label class="pv-depot" for="capture">
          <i class="fas fa-cloud-arrow-up"></i>
          <strong>Choisir la capture d'écran</strong>
          <span>JPG, PNG, WEBP, HEIC ou PDF — 8 Mo maximum</span>
          <input type="file" name="capture" id="capture" accept="image/*,application/pdf" required>
        </label>
        <div class="pv-apercu" id="pvApercu">
          <img id="pvImage" alt="Aperçu de la capture">
          <div class="pv-fichier">
            <span id="pvNom"></span>
            <button type="button" class="pv-retirer" id="pvRetirer">Retirer</button>
          </div>
        </div>
      </div>

      <div class="pv-duo">
        <div class="pv-champ">
          <label for="nom">Votre nom <span class="pv-req">*</span></label>
          <input type="text" name="nom" id="nom" value="{{ old('nom') }}" required maxlength="150">
        </div>
        <div class="pv-champ">
          <label for="telephone">WhatsApp <span class="pv-req">*</span>
            <span class="pv-aide">C'est par là que l'équipe vous répond</span>
          </label>
          <input type="tel" name="telephone" id="telephone" value="{{ old('telephone') }}" required maxlength="40" placeholder="+509 ...">
        </div>
      </div>

      <div class="pv-champ">
        <label for="email">Email <span class="pv-aide">Facultatif</span></label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" maxlength="190">
      </div>

      <div class="pv-champ">
        <label for="moyen">Moyen utilisé</label>
        <select name="moyen" id="moyen">
          <option value="">— Sélectionner —</option>
          @foreach ($passerelles as $p)
            <option value="{{ $p->code }}" @selected(old('moyen', $moyen) === $p->code)>{{ $p->nom }}</option>
          @endforeach
        </select>
      </div>

      <div class="pv-duo">
        <div class="pv-champ">
          <label for="montant">Montant payé</label>
          <input type="number" name="montant" id="montant" step="0.01" min="0" value="{{ old('montant', $montant) }}">
        </div>
        <div class="pv-champ">
          <label for="devise">Devise</label>
          <select name="devise" id="devise">
            <option value="HTG" @selected(old('devise') === 'HTG')>HTG (Gourdes)</option>
            <option value="USD" @selected(old('devise') === 'USD')>USD (Dollars)</option>
          </select>
        </div>
      </div>

      <div class="pv-champ">
        <label for="transaction_id">Numéro de transaction
          <span class="pv-aide">Celui du SMS ou du reçu, s'il y en a un</span>
        </label>
        <input type="text" name="transaction_id" id="transaction_id" value="{{ old('transaction_id') }}" maxlength="120">
      </div>

      <div class="pv-champ">
        <label for="motif">Motif du paiement
          <span class="pv-aide">Inscription, réservation, facture, événement…</span>
        </label>
        <input type="text" name="motif" id="motif" value="{{ old('motif', $motif) }}" maxlength="200">
      </div>

      <div class="pv-champ">
        <label for="note">Précision</label>
        <textarea name="note" id="note" maxlength="2000">{{ old('note') }}</textarea>
      </div>

      <button type="submit" class="pv-envoyer" id="pvBouton">Envoyer la preuve</button>
    </form>

    <a href="{{ route('paiement') }}" class="pv-retour">Revenir aux moyens de paiement</a>

  </div>
</section>

{{-- JavaScript natif : Alpine n'est chargé que sur le layout ERP. --}}
<script>
(function () {
  var input   = document.getElementById('capture');
  var apercu  = document.getElementById('pvApercu');
  var image   = document.getElementById('pvImage');
  var nom     = document.getElementById('pvNom');
  var retirer = document.getElementById('pvRetirer');
  var form    = document.getElementById('pvForm');
  var bouton  = document.getElementById('pvBouton');
  if (!input) return;

  function lisible(o) {
    return o >= 1048576 ? (o / 1048576).toFixed(1) + ' Mo'
                        : Math.max(1, Math.round(o / 1024)) + ' Ko';
  }

  input.addEventListener('change', function () {
    var f = input.files && input.files[0];
    if (!f) { apercu.classList.remove('on'); return; }

    nom.textContent = f.name + ' — ' + lisible(f.size);

    // Un PDF n'a pas de vignette : on garde la ligne de fichier, sans image.
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

  // Un envoi porte un fichier : sur une connexion lente le client cliquerait
  // plusieurs fois et déposerait la même preuve autant de fois.
  form.addEventListener('submit', function () {
    bouton.disabled = true;
    bouton.textContent = 'Envoi en cours...';
  });
})();
</script>

@endsection

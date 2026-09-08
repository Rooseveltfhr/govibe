@extends('layouts.public')

@section('title', 'Inscription reçue — '.$formation->titre)

@section('head')
<style>
  :root { --fm: {{ $formation->couleur }}; --fm-fonce: {{ $formation->couleur_foncee }}; }
  .fk-sect {
    background: linear-gradient(135deg, #080002 0%, var(--fm-fonce) 55%, #050505 100%);
    min-height: calc(100vh - 140px); padding: 70px 1.2rem;
    display: flex; align-items: center; justify-content: center;
  }
  .fk-card {
    max-width: 520px; width: 100%; text-align: center;
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
    border-radius: 22px; padding: 2.3rem 1.8rem;
  }
  .fk-check {
    width: 66px; height: 66px; border-radius: 50%; margin: 0 auto 1.3rem;
    background: linear-gradient(135deg, var(--fm), var(--fm-fonce));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem; color: #fff; box-shadow: 0 0 36px rgba(0,0,0,.35);
  }
  .fk-card h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.5rem, 4.5vw, 2rem);
    color: #fff; margin: 0 0 .4rem; letter-spacing: .02em;
  }
  .fk-ref {
    display: inline-block; font-family: 'Anton', sans-serif; letter-spacing: .09em;
    color: var(--fm); font-size: 1.12rem; margin-bottom: 1.3rem;
  }
  .fk-recap {
    background: rgba(0,0,0,.25); border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px; padding: 1rem 1.15rem; margin-bottom: 1.2rem; text-align: left;
  }
  .fk-ligne { display: flex; justify-content: space-between; gap: 1rem; padding: .33rem 0; font-size: .88rem; }
  .fk-ligne + .fk-ligne { border-top: 1px solid rgba(255,255,255,.06); }
  .fk-ligne span { color: rgba(255,255,255,.5); }
  .fk-ligne strong { color: #fff; font-weight: 600; text-align: right; }
  .fk-badge {
    display: inline-block; font-size: .74rem; font-weight: 700; border-radius: 6px;
    padding: .16rem .5rem; background: rgba(245,158,11,.2); color: #fbbf24;
  }

  .fk-etape {
    background: rgba(37,211,102,.09); border: 1px solid rgba(37,211,102,.3);
    border-radius: 12px; padding: 1rem 1.15rem; margin-bottom: 1.1rem;
  }
  .fk-etape p { color: rgba(255,255,255,.78); font-size: .88rem; line-height: 1.65; margin: 0; }

  .fk-actions { display: flex; flex-direction: column; gap: .6rem; }
  .fk-wa {
    display: inline-flex; align-items: center; justify-content: center; gap: .55rem;
    background: #25D366; color: #062e16; font-family: 'Anton', sans-serif;
    letter-spacing: .04em; font-size: 1.02rem; padding: .92rem 1.5rem;
    border-radius: 50px; text-decoration: none;
  }
  .fk-wa:hover { background: #1eb855; color: #062e16; }
  .fk-ghost {
    display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
    border: 1px solid rgba(255,255,255,.22); color: rgba(255,255,255,.8);
    font-size: .88rem; padding: .7rem 1.4rem; border-radius: 50px; text-decoration: none;
  }
  .fk-ghost:hover { background: rgba(255,255,255,.08); color: #fff; }
</style>
@endsection

@section('content')

<section class="fk-sect">
  <div class="fk-card">
    <div class="fk-check"><i class="fas fa-check"></i></div>

    <h1>Inscription reçue</h1>
    <span class="fk-ref">{{ $inscription->reference }}</span>

    <div class="fk-recap">
      <div class="fk-ligne"><span>Formation</span><strong>{{ $formation->titre }}</strong></div>
      <div class="fk-ligne"><span>Nom</span><strong>{{ $inscription->nom_complet }}</strong></div>
      <div class="fk-ligne"><span>Suivi</span><strong>{{ $inscription->mode_lisible }}</strong></div>
      @if ($inscription->montant_affiche)
        <div class="fk-ligne"><span>Montant</span><strong>{{ $inscription->montant_affiche }}</strong></div>
      @endif
      @if ($inscription->moyen_paiement_nom)
        <div class="fk-ligne"><span>Moyen</span><strong>{{ $inscription->moyen_paiement_nom }}</strong></div>
      @endif
      @if ($inscription->taille_lisible)
        <div class="fk-ligne"><span>Preuve</span><strong>Reçue ({{ $inscription->taille_lisible }})</strong></div>
      @endif
      <div class="fk-ligne">
        <span>Paiement</span>
        <strong><span class="fk-badge">{{ $inscription->statut_libelle }}</span></strong>
      </div>
    </div>

    {{-- La preuve est déjà chez GOVIBE. Ce message met le dossier sous les
         yeux de l'équipe tout de suite : WhatsApp n'accepte pas de pièce
         jointe par un lien, seulement du texte. --}}
    <div class="fk-etape">
      <p>
        Dernière étape : envoyez ce message sur WhatsApp pour que votre paiement
        soit vérifié et votre place confirmée. Le texte est déjà écrit.
      </p>
    </div>

    <div class="fk-actions">
      <a href="{{ $inscription->lien_whatsapp }}" target="_blank" rel="noopener" class="fk-wa">
        <i class="fab fa-whatsapp"></i> Envoyer pour vérification
      </a>
      <a href="{{ route('formations.show', $formation) }}" class="fk-ghost">
        <i class="fas fa-arrow-left"></i> Retour à la formation
      </a>
    </div>
  </div>
</section>

@endsection

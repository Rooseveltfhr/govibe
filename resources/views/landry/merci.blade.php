@extends('layouts.public')

@section('title', 'Réservation reçue — LANDRY | GOVIBE')

@section('head')
<style>
  .lm-sect {
    background:#f6f7f9; min-height:calc(100vh - 140px);
    display:flex; align-items:center; justify-content:center; padding:50px 1.2rem;
  }
  .lm-carte {
    max-width:520px; width:100%; background:#fff; border:1px solid #e4e7ec;
    border-radius:14px; padding:26px 22px; text-align:center;
  }
  .lm-ico {
    width:55px; height:55px; margin:0 auto 12px; border-radius:50%;
    background:#e9f8ef; color:#48A018; display:flex; align-items:center;
    justify-content:center; font-size:26px; font-weight:bold;
  }
  .lm-carte h1 { font-size:24px; margin:0 0 6px; color:#172033; }
  .lm-carte p { font-size:13px; color:#667085; margin:0; line-height:1.65; }
  .lm-ref {
    display:inline-block; font-family:'Anton',sans-serif; letter-spacing:.08em;
    color:#E01813; font-size:1.05rem; margin:10px 0 16px;
  }
  .lm-date { margin:14px 0; padding:12px; background:#f4f7ff; border-radius:9px; }
  .lm-date span { display:block; color:#667085; font-size:12px; }
  .lm-date strong { display:block; color:#0B3AB0; font-size:23px; margin-top:2px; text-transform:uppercase; }
  .lm-recap { text-align:left; background:#f8fafc; border-radius:9px; padding:12px; margin:14px 0; }
  .lm-l { display:flex; justify-content:space-between; gap:15px; padding:6px 0; border-bottom:1px solid #eaecf0; font-size:12px; }
  .lm-l:last-child { border-bottom:none; }
  .lm-l span { color:#667085; }
  .lm-l strong { text-align:right; color:#172033; }
  .lm-wa {
    display:flex; align-items:center; justify-content:center; gap:.5rem;
    width:100%; margin-top:15px; background:#25D366; color:#062e16;
    border:none; border-radius:8px; padding:13px; font-size:14px; font-weight:800;
    text-decoration:none; font-family:inherit;
  }
  .lm-wa:hover { background:#1eb855; color:#062e16; }
  .lm-retour { display:inline-block; margin-top:14px; color:#667085; font-size:13px; text-decoration:none; }
  .lm-retour:hover { color:#E01813; }
  @media (max-width:600px) {
    .lm-l { flex-direction:column; gap:2px; }
    .lm-l strong { text-align:left; }
  }
</style>
@endsection

@section('content')
<section class="lm-sect">
  <div class="lm-carte">
    <div class="lm-ico">&check;</div>

    <h1>Merci pour votre réservation</h1>
    <span class="lm-ref">{{ $reservation->reference }}</span>
    <p>Votre réservation a bien été enregistrée.</p>

    <div class="lm-date">
      <span>Les services commencent</span>
      <strong>{{ $ouverture->locale('fr')->translatedFormat('j F Y') }}</strong>
    </div>

    <div class="lm-recap">
      <div class="lm-l"><span>Nom</span><strong>{{ $reservation->nom_complet }}</strong></div>
      <div class="lm-l"><span>Service</span><strong>{{ $reservation->mode_service_libelle }}</strong></div>
      <div class="lm-l"><span>Facturation</span><strong>{{ $reservation->mode_facturation_libelle }}</strong></div>
      @if ($reservation->quantite_vetements)
        <div class="lm-l"><span>Quantité</span><strong>{{ $reservation->quantite_vetements }} vêtements</strong></div>
      @endif
      <div class="lm-l"><span>Fréquence</span><strong>{{ $reservation->frequence_libelle }}</strong></div>
      <div class="lm-l"><span>Paiement</span><strong>{{ $reservation->mode_paiement_libelle }}</strong></div>
      <div class="lm-l">
        <span>Inscription</span>
        <strong>{{ $reservation->accepte_frais_inscription ? $reservation->frais_affiche : 'À discuter' }}</strong>
      </div>
    </div>

    <p>Notre équipe vous contactera sur WhatsApp pour confirmer votre inscription.</p>

    {{-- La réservation est déjà enregistrée : ce message prévient l'équipe,
         il ne transporte pas la donnée. --}}
    <a href="{{ $reservation->lien_whatsapp }}" target="_blank" rel="noopener" class="lm-wa">
      <i class="fab fa-whatsapp"></i> Continuer sur WhatsApp
    </a>

    <a href="{{ route('landry.index') }}" class="lm-retour">Faire une autre réservation</a>
  </div>
</section>
@endsection

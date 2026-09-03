@extends('layouts.public')

@section('title', 'Moyens de paiement — GOVIBE Innovation Hub')
@section('description', "Les moyens de paiement acceptés par GOVIBE Innovation Hub : MonCash, NatCash, virement bancaire, Zelle, PayPal et cryptomonnaies.")

@section('head')
<style>
  .pay-hero {
    background: linear-gradient(135deg, #0a0000 0%, #1a0004 55%, #050505 100%);
    padding: 100px 1.5rem 60px; position: relative; overflow: hidden;
  }
  .pay-hero::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 55% 60% at 70% 45%, rgba(220,38,38,.13) 0%, transparent 70%);
  }
  .pay-hero-inner { position: relative; z-index: 1; max-width: 780px; margin: 0 auto; text-align: center; }
  .pay-tag {
    display: inline-flex; align-items: center; gap: .5rem;
    background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.35);
    color: #f87171; font-family: 'Anton', sans-serif; font-weight: 400;
    font-size: .8rem; letter-spacing: .14em; text-transform: uppercase;
    padding: .35rem 1rem; border-radius: 50px; margin-bottom: 1.3rem;
  }
  .pay-hero h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(2rem, 6vw, 3.2rem);
    color: #fff; line-height: 1.05; margin-bottom: 1rem; letter-spacing: .02em;
  }
  .pay-hero h1 span { color: #DC2626; }
  .pay-hero p {
    color: rgba(255,255,255,.72); font-size: 1.02rem;
    line-height: 1.75; max-width: 600px; margin: 0 auto;
  }

  .pay-section { padding: 60px 1.5rem 80px; background: #f8fafc; }
  .pay-wrap { max-width: 820px; margin: 0 auto; }

  .pay-avis {
    background: #fff; border: 1px solid #e5e7eb; border-left: 4px solid #DC2626;
    border-radius: 12px; padding: 1.3rem 1.5rem; margin-bottom: 1.5rem;
  }
  .pay-avis h2 {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1.05rem;
    color: #0f172a; margin: 0 0 .5rem; letter-spacing: .02em;
  }
  .pay-avis p { color: #64748b; font-size: .9rem; line-height: 1.7; margin: 0; }

  @media (max-width: 640px) {
    .pay-hero { padding: 70px 1.2rem 44px; }
    .pay-section { padding: 40px 1rem 56px; }
  }
</style>
@endsection

@section('content')

<section class="pay-hero">
  <div class="pay-hero-inner">
    <span class="pay-tag">Paiement</span>
    <h1>Comment <span>payer</span> GOVIBE</h1>
    <p>
      Choisissez le moyen qui vous convient. Les coordonnées s'affichent aussitôt,
      avec le QR code lorsqu'il est disponible.
    </p>
  </div>
</section>

<section class="pay-section">
  <div class="pay-wrap">

    <div class="pay-avis">
      <h2>Avant de payer</h2>
      <p>
        Vérifiez le montant et le moyen de paiement auprès de l'équipe avant tout envoi.
        Pour une inscription ou une réservation, indiquez son numéro dans le motif du paiement :
        c'est ce qui permet de rattacher votre versement à votre dossier.
      </p>
    </div>

    <x-moyens-paiement titre="Moyens de paiement acceptés" />

  </div>
</section>

@endsection

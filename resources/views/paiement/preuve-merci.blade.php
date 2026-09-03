@extends('layouts.public')

@section('title', 'Preuve reçue — GOVIBE')

@section('head')
<style>
  .pm-sect {
    background: linear-gradient(135deg, #0a0000 0%, #1a0004 55%, #050505 100%);
    min-height: calc(100vh - 140px); display: flex; align-items: center;
    justify-content: center; padding: 70px 1.2rem;
  }
  .pm-card {
    max-width: 540px; width: 100%; text-align: center;
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
    border-radius: 22px; padding: 2.4rem 1.9rem;
  }
  .pm-check {
    width: 66px; height: 66px; border-radius: 50%; margin: 0 auto 1.3rem;
    background: linear-gradient(135deg, #DC2626, #991b1b);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem; color: #fff; box-shadow: 0 0 36px rgba(220,38,38,.4);
  }
  .pm-card h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.5rem, 4.5vw, 2rem);
    color: #fff; margin: 0 0 .5rem; letter-spacing: .02em;
  }
  .pm-ref {
    display: inline-block; font-family: 'Anton', sans-serif; letter-spacing: .09em;
    color: #DC2626; font-size: 1.1rem; margin-bottom: 1.3rem;
  }
  .pm-recap {
    background: rgba(0,0,0,.25); border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px; padding: 1rem 1.15rem; margin-bottom: 1.4rem; text-align: left;
  }
  .pm-ligne { display: flex; justify-content: space-between; gap: 1rem; padding: .32rem 0; font-size: .88rem; }
  .pm-ligne + .pm-ligne { border-top: 1px solid rgba(255,255,255,.06); }
  .pm-ligne span { color: rgba(255,255,255,.5); }
  .pm-ligne strong { color: #fff; font-weight: 600; text-align: right; }

  .pm-etape {
    background: rgba(37,211,102,.09); border: 1px solid rgba(37,211,102,.3);
    border-radius: 12px; padding: 1rem 1.15rem; margin-bottom: 1.2rem;
  }
  .pm-etape p { color: rgba(255,255,255,.78); font-size: .87rem; line-height: 1.65; margin: 0; }

  .pm-wa {
    display: inline-flex; align-items: center; justify-content: center; gap: .55rem;
    width: 100%; background: #25D366; color: #062e16;
    font-family: 'Anton', sans-serif; letter-spacing: .04em; font-size: 1.02rem;
    padding: .9rem 1.5rem; border-radius: 50px; text-decoration: none; margin-bottom: .7rem;
  }
  .pm-wa:hover { background: #1eb855; color: #062e16; }
  .pm-ghost {
    display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
    width: 100%; border: 1px solid rgba(255,255,255,.22); color: rgba(255,255,255,.8);
    font-size: .88rem; padding: .7rem 1.4rem; border-radius: 50px; text-decoration: none;
  }
  .pm-ghost:hover { background: rgba(255,255,255,.08); color: #fff; }
  .pm-actions { display: flex; flex-direction: column; gap: .55rem; }
</style>
@endsection

@section('content')

<section class="pm-sect">
  <div class="pm-card">
    <div class="pm-check"><i class="fas fa-check"></i></div>

    <h1>Preuve reçue</h1>
    <span class="pm-ref">{{ $preuve->reference }}</span>

    <div class="pm-recap">
      <div class="pm-ligne"><span>Nom</span><strong>{{ $preuve->nom }}</strong></div>
      @if ($preuve->moyen_nom)
        <div class="pm-ligne"><span>Moyen</span><strong>{{ $preuve->moyen_nom }}</strong></div>
      @endif
      @if ($preuve->montant !== null)
        <div class="pm-ligne"><span>Montant</span><strong>{{ number_format((float) $preuve->montant, 2, ',', ' ') }} {{ $preuve->devise }}</strong></div>
      @endif
      @if ($preuve->transaction_id)
        <div class="pm-ligne"><span>Transaction</span><strong>{{ $preuve->transaction_id }}</strong></div>
      @endif
      @if ($preuve->taille_lisible)
        <div class="pm-ligne"><span>Capture</span><strong>Reçue ({{ $preuve->taille_lisible }})</strong></div>
      @endif
    </div>

    {{-- Le fichier ne peut pas être joint depuis cette page : WhatsApp
         n'accepte pas de pièce jointe par un lien. La capture est déjà
         enregistrée côté GOVIBE ; le message ne porte que la référence. --}}
    <div class="pm-etape">
      <p>
        Votre capture est enregistrée. Ouvrez maintenant WhatsApp pour prévenir
        l'équipe : le message est déjà écrit avec votre référence, vous n'avez qu'à l'envoyer.
      </p>
    </div>

    <div class="pm-actions">
      <a href="{{ $preuve->lien_whatsapp }}" target="_blank" rel="noopener" class="pm-wa">
        <i class="fab fa-whatsapp"></i> Prévenir sur WhatsApp
      </a>
      <a href="{{ route('paiement') }}" class="pm-ghost">
        <i class="fas fa-arrow-left"></i> Retour aux moyens de paiement
      </a>
    </div>
  </div>
</section>

@endsection

@extends('layouts.public')

@section('title', 'Résultat du paiement — GOVIBE')

@section('head')
<style>
  .pr-sect {
    background: linear-gradient(135deg, #080002 0%, #1a0004 55%, #050505 100%);
    min-height: calc(100vh - 140px); padding: 70px 1.2rem;
    display: flex; align-items: center; justify-content: center;
  }
  .pr-card {
    max-width: 500px; width: 100%; text-align: center;
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
    border-radius: 22px; padding: 2.3rem 1.9rem;
  }
  .pr-ico {
    width: 66px; height: 66px; border-radius: 50%; margin: 0 auto 1.3rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem; color: #fff;
  }
  .pr-ok { background: linear-gradient(135deg,#059669,#047857); box-shadow: 0 0 36px rgba(5,150,105,.35); }
  .pr-attente { background: linear-gradient(135deg,#d97706,#b45309); box-shadow: 0 0 36px rgba(217,119,6,.3); }
  .pr-ko { background: linear-gradient(135deg,#DC2626,#991b1b); box-shadow: 0 0 36px rgba(220,38,38,.35); }
  .pr-card h1 {
    font-family:'Anton',sans-serif; font-size: clamp(1.4rem,4.4vw,1.9rem);
    color:#fff; margin:0 0 .5rem; letter-spacing:.02em;
  }
  .pr-card > p { color: rgba(255,255,255,.72); line-height:1.7; font-size:.93rem; margin:0 0 1.4rem; }
  .pr-ref {
    display:inline-block; font-family:'Anton',sans-serif; letter-spacing:.09em;
    color:#DC2626; font-size:1.1rem; margin-bottom:1.2rem;
  }
  .pr-recap {
    background: rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08);
    border-radius:12px; padding:1rem 1.15rem; margin-bottom:1.3rem; text-align:left;
  }
  .pr-ligne { display:flex; justify-content:space-between; gap:1rem; padding:.32rem 0; font-size:.88rem; }
  .pr-ligne + .pr-ligne { border-top:1px solid rgba(255,255,255,.06); }
  .pr-ligne span { color: rgba(255,255,255,.5); }
  .pr-ligne strong { color:#fff; text-align:right; }
  .pr-actions { display:flex; flex-direction:column; gap:.55rem; }
  .pr-wa {
    display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
    background:#25D366; color:#062e16; font-family:'Anton',sans-serif;
    letter-spacing:.04em; padding:.85rem 1.5rem; border-radius:50px; text-decoration:none;
  }
  .pr-ghost {
    display:inline-flex; align-items:center; justify-content:center; gap:.45rem;
    border:1px solid rgba(255,255,255,.22); color:rgba(255,255,255,.8);
    font-size:.88rem; padding:.7rem 1.4rem; border-radius:50px; text-decoration:none;
  }
</style>
@endsection

@section('content')

@php
  $etat = match ($paiement->statut) {
      'reussi'                 => ['pr-ok', 'fa-check', 'Paiement confirmé'],
      'echoue', 'expire', 'annule' => ['pr-ko', 'fa-xmark', "Paiement non abouti"],
      default                  => ['pr-attente', 'fa-hourglass-half', 'Paiement en cours de vérification'],
  };
@endphp

<section class="pr-sect">
  <div class="pr-card">
    <div class="pr-ico {{ $etat[0] }}"><i class="fas {{ $etat[1] }}"></i></div>

    <h1>{{ $etat[2] }}</h1>
    <span class="pr-ref">{{ $paiement->reference }}</span>

    <p>
      @if ($paiement->estReussi())
        Votre paiement est enregistré. L'équipe GOVIBE poursuit le traitement de votre dossier.
      @elseif (in_array($paiement->statut, ['echoue','expire','annule']))
        Le paiement n'a pas abouti. Aucun montant n'a été retenu. Vous pouvez réessayer,
        ou écrire à l'équipe si la somme a malgré tout été débitée.
      @else
        Nous attendons la confirmation de la passerelle. Si le montant a été débité,
        écrivez-nous avec cette référence : l'équipe vérifie et confirme.
      @endif
    </p>

    <div class="pr-recap">
      <div class="pr-ligne"><span>Montant</span><strong>{{ $paiement->montant_affiche }}</strong></div>
      @if ($paiement->montant_converti_affiche)
        <div class="pr-ligne"><span>Équivalent</span><strong>{{ $paiement->montant_converti_affiche }}</strong></div>
      @endif
      @if ($paiement->passerelle_nom)
        <div class="pr-ligne"><span>Moyen</span><strong>{{ $paiement->passerelle_nom }}</strong></div>
      @endif
      @if ($paiement->reference_externe)
        <div class="pr-ligne"><span>Transaction</span><strong>{{ $paiement->reference_externe }}</strong></div>
      @endif
      <div class="pr-ligne"><span>Statut</span><strong>{{ $paiement->statut_libelle }}</strong></div>
    </div>

    <div class="pr-actions">
      <a href="https://wa.me/50933988754?text={{ rawurlencode('Bonjour, au sujet du paiement '.$paiement->reference.' sur govibeht.com.') }}"
         target="_blank" rel="noopener" class="pr-wa">
        <i class="fab fa-whatsapp"></i> Écrire à GOVIBE
      </a>
      <a href="{{ route('home') }}" class="pr-ghost">
        <i class="fas fa-arrow-left"></i> Retour à GOVIBE
      </a>
    </div>
  </div>
</section>

@endsection

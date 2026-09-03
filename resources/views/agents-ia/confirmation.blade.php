@extends('layouts.public')

@section('title', 'Demande reçue — GOVIBE AI Agents')

@section('head')
<style>
  .cf-sect {
    background: linear-gradient(135deg, #080002 0%, #1a0004 55%, #050505 100%);
    min-height: calc(100vh - 140px); padding: 72px 1.2rem;
    display: flex; align-items: center; justify-content: center;
  }
  .cf-card {
    max-width: 620px; width: 100%;
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
    border-radius: 22px; padding: 2.3rem 1.9rem;
  }
  .cf-check {
    width: 66px; height: 66px; border-radius: 50%; margin: 0 auto 1.3rem;
    background: linear-gradient(135deg, #DC2626, #991b1b);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem; color: #fff; box-shadow: 0 0 36px rgba(220,38,38,.4);
  }
  .cf-card h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.5rem, 4.4vw, 2rem);
    color: #fff; margin: 0 0 .5rem; letter-spacing: .02em; text-align: center;
  }
  .cf-intro {
    color: rgba(255,255,255,.7); line-height: 1.72; font-size: .93rem;
    text-align: center; margin: 0 0 1.6rem;
  }
  .cf-ref {
    display: block; text-align: center; font-family: 'Anton', sans-serif;
    letter-spacing: .09em; color: #DC2626; font-size: 1.15rem; margin-bottom: 1.4rem;
  }
  .cf-bloc {
    background: rgba(0,0,0,.25); border: 1px solid rgba(255,255,255,.08);
    border-radius: 13px; padding: 1.05rem 1.2rem; margin-bottom: 1.1rem;
  }
  .cf-bloc h2 {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: .8rem;
    letter-spacing: .13em; text-transform: uppercase;
    color: rgba(255,255,255,.45); margin: 0 0 .7rem;
  }
  .cf-ligne { display: flex; justify-content: space-between; gap: 1rem; padding: .34rem 0; font-size: .88rem; }
  .cf-ligne + .cf-ligne { border-top: 1px solid rgba(255,255,255,.06); }
  .cf-ligne span { color: rgba(255,255,255,.5); }
  .cf-ligne strong { color: #fff; font-weight: 600; text-align: right; }
  .cf-badge {
    display: inline-block; font-size: .74rem; font-weight: 700; border-radius: 6px;
    padding: .16rem .5rem; background: rgba(245,158,11,.2); color: #fbbf24;
  }
  .cf-badge-ok { background: rgba(34,197,94,.2); color: #4ade80; }

  .cf-paiement { border-color: rgba(220,38,38,.3); background: rgba(220,38,38,.07); }
  .cf-valeur {
    display: block; font-family: 'Anton', sans-serif; letter-spacing: .05em;
    color: #fff; font-size: 1.05rem; word-break: break-all; margin-top: .15rem;
  }
  .cf-qr { text-align: center; margin-top: .9rem; }
  .cf-qr img { max-width: 160px; border-radius: 10px; background: #fff; padding: .5rem; }

  .cf-etapes { list-style: none; padding: 0; margin: 0; counter-reset: e; }
  .cf-etapes li {
    counter-increment: e; position: relative; padding-left: 1.9rem;
    color: rgba(255,255,255,.72); font-size: .87rem; line-height: 1.6; margin-bottom: .6rem;
  }
  .cf-etapes li::before {
    content: counter(e); position: absolute; left: 0; top: 0;
    width: 1.35rem; height: 1.35rem; border-radius: 50%;
    background: rgba(220,38,38,.25); color: #f87171;
    font-size: .72rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
  }

  .cf-actions { display: flex; flex-direction: column; gap: .55rem; margin-top: 1.5rem; }
  .cf-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .55rem;
    background: linear-gradient(135deg, #DC2626, #991b1b); color: #fff;
    font-family: 'Anton', sans-serif; letter-spacing: .045em; font-size: 1rem;
    padding: .88rem 1.5rem; border-radius: 50px; text-decoration: none;
  }
  .cf-btn:hover { opacity: .93; color: #fff; }
  .cf-wa {
    display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
    background: #25D366; color: #062e16; font-family: 'Anton', sans-serif;
    letter-spacing: .04em; font-size: .98rem; padding: .82rem 1.5rem;
    border-radius: 50px; text-decoration: none;
  }
  .cf-wa:hover { background: #1eb855; color: #062e16; }
  .cf-ghost {
    display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
    border: 1px solid rgba(255,255,255,.22); color: rgba(255,255,255,.8);
    font-size: .88rem; padding: .7rem 1.4rem; border-radius: 50px; text-decoration: none;
  }
  .cf-ghost:hover { background: rgba(255,255,255,.08); color: #fff; }
</style>
@endsection

@section('content')

@php
  $wa = 'https://wa.me/50933988754?text='.rawurlencode(
      "Bonjour, je viens de demander un Agent IA sur govibeht.com.\n"
      ."Référence : {$demande->reference}\n"
      ."Agent : {$demande->agent_nom}\n"
      ."Entreprise : {$demande->entreprise}"
  );
@endphp

<section class="cf-sect">
  <div class="cf-card">
    <div class="cf-check"><i class="fas fa-check"></i></div>

    <h1>Votre demande a été reçue !</h1>
    <span class="cf-ref">{{ $demande->reference }}</span>

    <p class="cf-intro">
      L'équipe GOVIBE va examiner votre demande et vous contacter pour finaliser
      la configuration de votre Agent IA.
    </p>

    <div class="cf-bloc">
      <h2>Votre demande</h2>
      <div class="cf-ligne"><span>Agent</span><strong>{{ $demande->agent_nom }}</strong></div>
      <div class="cf-ligne"><span>Entreprise</span><strong>{{ $demande->entreprise }}</strong></div>
      @if ($demande->canal_lisible)
        <div class="cf-ligne"><span>Canal</span><strong>{{ $demande->canal_lisible }}</strong></div>
      @endif
      @if ($demande->sur_devis)
        <div class="cf-ligne"><span>Tarification</span><strong>Sur devis</strong></div>
      @else
        <div class="cf-ligne">
          <span>Installation</span>
          <strong>{{ $demande->montantAffiche((float) $demande->prix_installation) ?? '—' }}</strong>
        </div>
        @if ($demande->prix_mensuel !== null)
          <div class="cf-ligne">
            <span>Service mensuel</span>
            <strong>{{ $demande->montantAffiche((float) $demande->prix_mensuel) }} / mois</strong>
          </div>
        @endif
      @endif
      <div class="cf-ligne">
        <span>Paiement</span>
        <strong>
          <span class="cf-badge {{ $demande->statut_paiement === 'recu' ? 'cf-badge-ok' : '' }}">
            {{ $demande->statut_paiement_libelle }}
          </span>
        </strong>
      </div>
    </div>

    @if ($passerelle && ! $demande->sur_devis)
      {{-- Aucune passerelle n'encaisse en ligne : on donne les coordonnées
           exactes du moyen choisi plutôt qu'un bouton « Payer » inopérant. --}}
      <div class="cf-bloc cf-paiement">
        <h2>Payer par {{ $passerelle->nom }}</h2>
        @if ($passerelle->titulaire)
          <div class="cf-ligne"><span>Au nom de</span><strong>{{ $passerelle->titulaire }}</strong></div>
        @endif
        @if ($passerelle->valeur_a_copier)
          <span style="color:rgba(255,255,255,.5);font-size:.8rem">Numéro / adresse</span>
          <span class="cf-valeur">{{ $passerelle->valeur_a_copier }}</span>
        @endif
        @if ($passerelle->lien_paiement)
          <a href="{{ $passerelle->lien_paiement }}" target="_blank" rel="noopener" class="cf-btn" style="margin-top:.9rem">
            Payer avec {{ $passerelle->nom }}
          </a>
        @endif
        @if ($passerelle->instructions)
          <p style="color:rgba(255,255,255,.65);font-size:.84rem;line-height:1.65;margin:.8rem 0 0">{{ $passerelle->instructions }}</p>
        @endif
        @if ($passerelle->qr_code_url)
          <figure class="cf-qr">
            <img src="{{ $passerelle->qr_code_url }}" alt="QR code {{ $passerelle->nom }}" loading="lazy">
          </figure>
        @endif
        <p style="color:rgba(255,255,255,.6);font-size:.82rem;line-height:1.6;margin:.9rem 0 0">
          Indiquez la référence <strong style="color:#fff">{{ $demande->reference }}</strong> dans le motif du paiement.
        </p>
      </div>
    @endif

    <div class="cf-bloc">
      <h2>Prochaines étapes</h2>
      <ul class="cf-etapes">
        @if ($demande->sur_devis)
          <li>GOVIBE étudie votre besoin et prépare un devis détaillé.</li>
          <li>Nous vous contactons sous 48 heures ouvrables pour en discuter.</li>
          <li>Après validation du devis, la configuration démarre.</li>
        @else
          <li>Effectuez le paiement de l'installation par le moyen choisi.</li>
          <li>Envoyez votre preuve de paiement pour que la demande avance.</li>
          <li>GOVIBE vous contacte sous 48 heures ouvrables pour la configuration.</li>
          <li>Vous testez l'agent avant sa mise en ligne.</li>
        @endif
      </ul>
    </div>

    <div class="cf-bloc">
      <h2>Nous contacter</h2>
      <div class="cf-ligne"><span>WhatsApp</span><strong>+509 3398-8754</strong></div>
      <div class="cf-ligne"><span>Email</span><strong>contact@govibeht.com</strong></div>
    </div>

    <div class="cf-actions">
      @if (! $demande->sur_devis)
        <a href="{{ route('paiement.preuve', ['montant' => $demande->prix_installation, 'motif' => $demande->reference, 'moyen' => $demande->moyen_paiement]) }}" class="cf-btn">
          <i class="fas fa-paper-plane"></i> Envoyer ma preuve de paiement
        </a>
      @endif
      <a href="{{ $wa }}" target="_blank" rel="noopener" class="cf-wa">
        <i class="fab fa-whatsapp"></i> Contacter GOVIBE
      </a>
      <a href="{{ route('home') }}" class="cf-ghost">
        <i class="fas fa-arrow-left"></i> Retour à GOVIBE
      </a>
    </div>
  </div>
</section>

@endsection

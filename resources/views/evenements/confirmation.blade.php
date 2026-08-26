@extends('layouts.public')

@section('title', 'Inscription confirmée — ' . $evenement->titre)
@section('description', 'Votre inscription à ' . $evenement->titre . ' est enregistrée.')

@section('head')
<style>
  .cf-section {
    background:linear-gradient(135deg,#0a0000 0%,#1a0004 55%,#050505 100%);
    min-height:calc(100vh - 140px);
    display:flex; align-items:center; justify-content:center;
    padding:80px 1.5rem; position:relative; overflow:hidden;
  }
  .cf-section::before {
    content:''; position:absolute; inset:0;
    background:radial-gradient(ellipse 55% 60% at 50% 35%,rgba(220,38,38,.15) 0%,transparent 70%);
  }
  .cf-card {
    position:relative; z-index:1; max-width:620px; width:100%; text-align:center;
    background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1);
    border-radius:24px; padding:3rem 2.5rem; backdrop-filter:blur(8px);
  }
  .cf-check {
    width:76px; height:76px; border-radius:50%; margin:0 auto 1.6rem;
    background:linear-gradient(135deg,#DC2626,#991b1b);
    display:flex; align-items:center; justify-content:center;
    font-size:2rem; color:#fff; box-shadow:0 0 40px rgba(220,38,38,.45);
  }
  .cf-card h1 {
    font-family:'Anton',sans-serif; font-size:clamp(1.6rem,4.5vw,2.4rem);
    color:#fff; margin-bottom:.7rem; letter-spacing:.02em;
  }
  .cf-event {
    display:inline-block; color:#DC2626; font-weight:700;
    font-size:1.05rem; margin-bottom:1.2rem;
  }
  .cf-card p.lead { color:rgba(255,255,255,.7); line-height:1.8; font-size:.96rem; }

  .cf-recap {
    text-align:left; background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08);
    border-radius:14px; padding:1.3rem 1.5rem; margin:1.8rem 0;
  }
  .cf-recap-row {
    display:flex; justify-content:space-between; gap:1rem;
    padding:.5rem 0; font-size:.88rem; border-bottom:1px solid rgba(255,255,255,.06);
  }
  .cf-recap-row:last-child { border-bottom:none; }
  .cf-recap-row .k { color:rgba(255,255,255,.45); }
  .cf-recap-row .v { color:#fff; font-weight:600; text-align:right; }

  .cf-actions { display:flex; flex-direction:column; gap:.8rem; margin-top:1.8rem; }
  .cf-btn-wa {
    display:inline-flex; align-items:center; justify-content:center; gap:.6rem;
    background:linear-gradient(135deg,#DC2626,#991b1b); color:#fff;
    font-weight:700; font-size:1rem; padding:.95rem 1.6rem;
    border-radius:50px; transition:opacity .2s, transform .2s;
  }
  .cf-btn-wa:hover { opacity:.92; transform:translateY(-2px); color:#fff; }
  .cf-btn-ghost {
    display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
    border:1px solid rgba(255,255,255,.22); color:rgba(255,255,255,.8);
    font-weight:600; font-size:.9rem; padding:.75rem 1.5rem;
    border-radius:50px; transition:background .2s, border-color .2s;
  }
  .cf-btn-ghost:hover { background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.4); color:#fff; }
  .cf-note { color:rgba(255,255,255,.45); font-size:.82rem; margin-top:1.4rem; line-height:1.6; }

  @media (max-width:576px) {
    .cf-section { padding:56px 1rem; }
    .cf-card { padding:2rem 1.3rem; }
    .cf-recap-row { flex-direction:column; gap:.2rem; }
    .cf-recap-row .v { text-align:left; }
  }
</style>
@endsection

@section('content')

<section class="cf-section">
  <div class="cf-card">
    <div class="cf-check"><i class="fas fa-check"></i></div>

    <h1>Inscription confirmée</h1>
    <span class="cf-event">{{ $evenement->titre }}</span>

    <p class="lead">
      Merci {{ $reservation->prenom }}. Votre place est enregistrée.
      Les informations pratiques vous seront communiquées par WhatsApp et par email.
    </p>

    <div class="cf-recap">
      <div class="cf-recap-row">
        <span class="k">Participante</span>
        <span class="v">{{ $reservation->nom_complet }}</span>
      </div>
      <div class="cf-recap-row">
        <span class="k">Email</span>
        <span class="v">{{ $reservation->email }}</span>
      </div>
      <div class="cf-recap-row">
        <span class="k">WhatsApp</span>
        <span class="v">{{ $reservation->whatsapp }}</span>
      </div>
      @if ($evenement->dates_libelle)
        <div class="cf-recap-row">
          <span class="k">Date</span>
          <span class="v">{{ $evenement->dates_libelle }}</span>
        </div>
      @endif
      @if ($evenement->lieu)
        <div class="cf-recap-row">
          <span class="k">Lieu</span>
          <span class="v">{{ $evenement->lieu }}</span>
        </div>
      @endif
    </div>

    <div class="cf-actions">
      @if ($evenement->whatsapp_group_url)
        <a href="{{ $evenement->whatsapp_group_url }}" target="_blank" rel="noopener" class="cf-btn-wa">
          <i class="fab fa-whatsapp"></i> Rejoindre le groupe
        </a>
      @endif
      <a href="{{ route('home') }}" class="cf-btn-ghost">
        <i class="fas fa-arrow-left"></i> Retour à l'accueil
      </a>
    </div>

    @if ($evenement->whatsapp_group_url)
      <p class="cf-note">
        Le groupe WhatsApp est le canal officiel de l'événement : programme, horaires et rappels y sont publiés.
      </p>
    @endif
  </div>
</section>

@endsection

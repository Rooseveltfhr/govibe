@extends('layouts.public')

@section('title', 'Fiche enregistrée — GOVIBE')

@section('head')
<style>
  .fm-sect {
    background: linear-gradient(135deg, #0a0000 0%, #1a0004 55%, #050505 100%);
    min-height: calc(100vh - 140px); display: flex; align-items: center;
    justify-content: center; padding: 70px 1.2rem;
  }
  .fm-card {
    max-width: 520px; width: 100%; text-align: center;
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
    border-radius: 22px; padding: 2.6rem 2rem;
  }
  .fm-check {
    width: 68px; height: 68px; border-radius: 50%; margin: 0 auto 1.4rem;
    background: linear-gradient(135deg, #DC2626, #991b1b);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; color: #fff; box-shadow: 0 0 36px rgba(220,38,38,.4);
  }
  .fm-card h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.5rem, 4.5vw, 2.1rem);
    color: #fff; margin: 0 0 .6rem; letter-spacing: .02em;
  }
  .fm-ref {
    display: inline-block; font-family: 'Anton', sans-serif; letter-spacing: .08em;
    color: #DC2626; font-size: 1.05rem; margin-bottom: 1.2rem;
  }
  .fm-card p { color: rgba(255,255,255,.7); line-height: 1.75; font-size: .94rem; margin: 0 0 1.6rem; }
  .fm-org {
    background: rgba(0,0,0,.25); border: 1px solid rgba(255,255,255,.08);
    border-radius: 12px; padding: .9rem 1.1rem; margin-bottom: 1.6rem;
    color: #fff; font-weight: 600; font-size: .95rem;
  }
  .fm-org span { display: block; color: rgba(255,255,255,.45); font-size: .74rem;
    letter-spacing: .1em; text-transform: uppercase; font-weight: 400; margin-bottom: .2rem; }
  .fm-actions { display: flex; flex-direction: column; gap: .7rem; }
  .fm-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
    background: linear-gradient(135deg, #DC2626, #991b1b); color: #fff;
    font-family: 'Anton', sans-serif; letter-spacing: .05em; font-size: 1rem;
    padding: .85rem 1.5rem; border-radius: 50px; text-decoration: none;
  }
  .fm-btn:hover { opacity: .93; color: #fff; }
  .fm-ghost {
    display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
    border: 1px solid rgba(255,255,255,.22); color: rgba(255,255,255,.8);
    font-size: .88rem; padding: .7rem 1.4rem; border-radius: 50px; text-decoration: none;
  }
  .fm-ghost:hover { background: rgba(255,255,255,.08); color: #fff; }
</style>
@endsection

@section('content')

<section class="fm-sect">
  <div class="fm-card">
    <div class="fm-check"><i class="fas fa-check"></i></div>

    <h1>Fiche enregistrée</h1>
    <span class="fm-ref">{{ $fiche->reference }}</span>

    <div class="fm-org">
      <span>Organisation</span>
      {{ $fiche->nom_organisation }}
    </div>

    <p>
      Elle est visible dans l'ERP, où l'équipe peut la qualifier et enregistrer le suivi.
      Notez la référence si vous devez la retrouver.
    </p>

    <div class="fm-actions">
      <a href="{{ route('fiche-technique.create') }}" class="fm-btn">
        <i class="fas fa-plus"></i> Remplir une autre fiche
      </a>
      <a href="{{ route('home') }}" class="fm-ghost">
        <i class="fas fa-arrow-left"></i> Retour à l'accueil
      </a>
    </div>
  </div>
</section>

@endsection

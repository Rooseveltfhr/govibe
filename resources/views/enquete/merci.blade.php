@extends('layouts.public')

@section('title', $textes['merci_titre'].' | GOVIBE Innovation Hub')
@section('description', $textes['meta'])

@section('head')
<style>
  .mq { --mq-bleu:#1B3A8C; --mq-encre:#111827; --mq-gris:#5b6472;
        --mq-fond:#f6f8fb; --mq-trait:#dfe5ee; }

  .mq-sect { background:var(--mq-fond); padding:46px 0 58px; }
  .mq-wrap { max-width:640px; margin:auto; padding:0 16px; }

  .mq-carte { background:#fff; border:1px solid var(--mq-trait); border-radius:16px; padding:32px 24px; text-align:center; }
  .mq-coche {
    width:64px; height:64px; border-radius:50%; margin:0 auto 18px;
    background:#ecfdf5; color:#047857; display:flex; align-items:center; justify-content:center; font-size:28px;
  }
  .mq-carte h1 {
    font-family:'Anton',sans-serif; font-weight:400; letter-spacing:.5px; margin:0;
    font-size:clamp(24px,5vw,33px); color:var(--mq-encre); text-wrap:balance;
  }
  .mq-carte > p { margin:14px auto 0; max-width:48ch; font-size:15px; line-height:1.72; color:var(--mq-gris); }

  .mq-ref {
    display:inline-block; margin-top:20px; background:var(--mq-fond);
    border:1px dashed var(--mq-trait); border-radius:11px; padding:11px 18px;
  }
  .mq-ref span { display:block; font-size:11.5px; letter-spacing:.12em; text-transform:uppercase; color:var(--mq-gris); }
  .mq-ref strong { font-size:20px; font-weight:800; color:var(--mq-encre); letter-spacing:1px; }

  .mq-contact {
    margin-top:18px; background:#eef3ff; border:1px solid #c7d7ff; color:#1e3a8c;
    border-radius:12px; padding:13px 16px; font-size:14px; line-height:1.65;
  }

  .mq-liens { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin-top:18px; }
  .mq-liens a {
    font-size:14px; font-weight:700; text-decoration:none; color:var(--mq-bleu);
    background:#fff; border:1px solid var(--mq-trait); border-radius:11px; padding:12px 18px;
  }
  .mq-conclusion { text-align:center; font-size:13.5px; line-height:1.7; color:var(--mq-gris); margin:20px 0 0; }
</style>
@endsection

@section('content')
<div class="mq">
  <section class="mq-sect">
    <div class="mq-wrap">

      <div class="mq-carte">
        <div class="mq-coche"><i class="fas fa-check"></i></div>
        <h1>{{ $textes['merci_titre'] }}</h1>
        <p>{{ $textes['merci_texte'] }}</p>

        <div class="mq-ref">
          <span>{{ $textes['merci_reference'] }}</span>
          <strong>{{ $reponse->reference }}</strong>
        </div>

        @if ($reponse->souhaite_contact)
          <p class="mq-contact"><i class="fas fa-comment-dots"></i> {{ $textes['merci_contact'] }}</p>
        @endif
      </div>

      <div class="mq-liens">
        <a href="{{ route('home') }}">{{ $textes['merci_retour'] }}</a>
        <a href="{{ route('services') }}">{{ $textes['merci_services'] }}</a>
      </div>

      <p class="mq-conclusion">{{ $textes['conclusion'] }}</p>

    </div>
  </section>
</div>
@endsection

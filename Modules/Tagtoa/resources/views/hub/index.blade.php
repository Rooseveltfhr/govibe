@extends('tagtoa::layouts.dashboard')
@section('title', 'TAGTOA')
@section('page', __('Bonjour 👋'))

@section('content')
<div class="grid g4">
    <div class="stat"><div class="ic"><i class="fa-solid fa-money-bill-transfer"></i></div><div class="v">{{ $stats['pay_pages'] }}</div><div class="k">{{ __('Pages de paiement') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-bell"></i></div><div class="v">{{ $stats['pay_pending'] }}</div><div class="k">{{ __('Preuves à vérifier') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-id-card"></i></div><div class="v">{{ $stats['loyalty_cards'] }}</div><div class="k">{{ __('Cartes fidélité') }}</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-ticket"></i></div><div class="v">{{ $stats['events'] }}</div><div class="k">{{ __('Événements') }}</div></div>
</div>

<div class="h-row" style="margin-top:26px"><h2>{{ __('Vos outils TAGTOA') }}</h2></div>
<div class="grid g3">
    @foreach([
        ['site','Site web','fa-globe','Site web professionnel par abonnement : vitrine, services, contact, galerie.'],
        ['menu','Menu','fa-utensils','Menu digital NFC/QR : restaurant, club, lounge, hôtel… vendez produits & services.'],
        ['pay','Paiements','fa-money-bill-transfer','Page de paiement (MonCash, NatCash, Zelle, PayPal…) + preuves.'],
        ['loyalty','Fidélité','fa-id-card','Cartes NFC de fidélité : solde, points, récompenses.'],
        ['links','Liens','fa-link','Page de liens style Linktree + don.'],
        ['event','Événements','fa-ticket','Billetterie + check-in NFC/QR.'],
        ['booking','Réservations','fa-calendar-check','Prise de rendez-vous en ligne : prestations, créneaux, confirmation.'],
        ['pos','Caisse (POS)','fa-cash-register','Caisse tactile offline-first, multi-paiement.'],
        ['billing','Revenu & forfait','fa-wallet','Abonnement ou commission : votre choix.'],
    ] as $m)
        <a class="card" href="{{ url('/tagtoa/'.$m[0]) }}" style="display:block;transition:transform .12s,box-shadow .15s" onmouseover="this.style.boxShadow='0 8px 26px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
            <div class="ic" style="width:46px;height:46px;border-radius:12px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:20px"><i class="fa-solid {{ $m[2] }}"></i></div>
            <b style="font-family:var(--fh);font-size:16px;display:block;margin-top:12px">{{ __($m[1]) }}</b>
            <p style="font-size:13.5px;color:var(--muted);margin-top:4px">{{ __($m[3]) }}</p>
        </a>
    @endforeach
</div>
@endsection

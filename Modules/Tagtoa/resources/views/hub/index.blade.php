@extends('tagtoa::layouts.dashboard')
@section('title', 'TAGTOA')
@section('page', __('Bonjour 👋'))

@section('content')
@if(!empty($isSuperAdmin))
<a href="{{ route('tagtoa.superadmin.index') }}" class="card" style="display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,#0d140c,#1D9E75);color:#fff;border:0;text-decoration:none;margin-bottom:16px">
    <i class="fa-solid fa-shield-halved" style="font-size:22px"></i>
    <div style="flex:1"><b style="font-family:var(--ft,sans-serif)">{{ __('Super-admin — vue plateforme') }}</b><div style="opacity:.85;font-size:13px">{{ __('Revenu global, commissions, abonnements, top marchands.') }}</div></div>
    <i class="fa-solid fa-arrow-right"></i>
</a>
@endif
@if(!empty($isNew))
{{-- Hero onboarding : marchand sans aucune ressource --}}
<div class="card" style="background:var(--blk);color:#fff;border:0;display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin-bottom:20px">
    <div style="flex:1;min-width:240px">
        <b style="font-family:var(--ft);font-weight:400;font-size:22px;letter-spacing:.01em">{{ __('Bienvenue sur TAGTOA !') }}</b>
        <div style="opacity:.8;font-size:14px;margin-top:6px">{{ __('Créez votre première page en 30 secondes : menu, paiement ou liens — puis partagez votre QR.') }}</div>
    </div>
    <a class="btn btn-p" href="{{ route('tagtoa.start') }}" style="flex:0"><i class="fa-solid fa-bolt"></i> {{ __('Commencer') }}</a>
</div>
@else
<div style="margin-bottom:14px;text-align:right"><a href="{{ route('tagtoa.start') }}" style="color:var(--blue-deep);font-weight:700;font-size:13.5px"><i class="fa-solid fa-bolt"></i> {{ __('Guide de démarrage') }}</a></div>
@endif
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
        ['store','Boutique','fa-bag-shopping','Boutique en ligne : catalogue, panier, commande WhatsApp + paiement.'],
        ['pay','Paiements','fa-money-bill-transfer','Page de paiement (MonCash, NatCash, Zelle, PayPal…) + preuves.'],
        ['loyalty','Fidélité','fa-id-card','Cartes NFC de fidélité : solde, points, récompenses.'],
        ['links','Liens','fa-link','Page de liens style Linktree + don.'],
        ['event','Événements','fa-ticket','Billetterie + check-in NFC/QR.'],
        ['booking','Réservations','fa-calendar-check','Prise de rendez-vous en ligne : prestations, créneaux, confirmation.'],
        ['pos','Caisse (POS)','fa-cash-register','Caisse tactile offline-first, multi-paiement.'],
        ['reviews','Avis clients','fa-star','Collectez et modérez les avis clients sur vos pages publiques.'],
        ['billing','Revenu & forfait','fa-wallet','Abonnement ou commission : votre choix.'],
        ['audit','Journal d\'audit','fa-clipboard-list','Traçabilité des actions sensibles : modération, finances, statuts.'],
    ] as $m)
        <a class="card" href="{{ url('/tagtoa/'.$m[0]) }}" style="display:block;transition:transform .12s,box-shadow .15s" onmouseover="this.style.boxShadow='0 8px 26px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
            <div class="ic" style="width:46px;height:46px;border-radius:12px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:20px"><i class="fa-solid {{ $m[2] }}"></i></div>
            <b style="font-family:var(--fh);font-size:16px;display:block;margin-top:12px">{{ __($m[1]) }}</b>
            <p style="font-size:13.5px;color:var(--muted);margin-top:4px">{{ __($m[3]) }}</p>
        </a>
    @endforeach
</div>
@endsection

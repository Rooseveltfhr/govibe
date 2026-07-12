@extends('tagtoa::layouts.dashboard')
@section('title', __('Commencer'))
@section('page', __('Commencer'))

@section('content')

{{-- Étapes --}}
<div class="card" style="display:flex;gap:18px;flex-wrap:wrap;text-align:center">
    @foreach([
        ['1', __('Choisissez votre activité'), 'fa-hand-pointer'],
        ['2', __('Créez votre première page (30 secondes)'), 'fa-bolt'],
        ['3', __('Partagez votre QR / lien et recevez vos clients'), 'fa-qrcode'],
    ] as $s)
    <div style="flex:1;min-width:180px">
        <div style="width:44px;height:44px;border-radius:50%;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font:700 18px var(--fh);margin:0 auto 8px">{{ $s[0] }}</div>
        <i class="fa-solid {{ $s[2] }}" style="color:var(--blue-deep)"></i>
        <div style="font-size:13.5px;margin-top:6px;color:var(--muted)">{{ $s[1] }}</div>
    </div>
    @endforeach
</div>

{{-- Démarrages rapides : création en 1 formulaire --}}
<div class="h-row" style="margin-top:24px"><h2>{{ __('Démarrage rapide — créez en 30 secondes') }}</h2></div>
<div class="grid g3">
    @foreach([
        ['menu',  __('Restaurant / Bar / Hôtel'),  'fa-utensils',            __('Menu digital NFC/QR — vos clients scannent, commandent, paient.'),        __('Nom du restaurant')],
        ['pay',   __('Boutique / Services'),        'fa-money-bill-transfer', __('Page de paiement — recevez MonCash, NatCash, Zelle et plus.'),            __('Nom du business')],
        ['links', __('Créateur / Influenceur'),     'fa-link',                __('Page de liens style Linktree — tous vos réseaux en un seul lien.'),       __('Votre nom / marque')],
    ] as $q)
    <div class="card">
        <div class="ic" style="width:46px;height:46px;border-radius:12px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:20px"><i class="fa-solid {{ $q[2] }}"></i></div>
        <b style="font-family:var(--fh);font-size:16px;display:block;margin-top:12px">{{ $q[1] }}</b>
        <p style="color:var(--muted);font-size:13.5px;margin-top:6px">{{ $q[3] }}</p>
        <form method="POST" action="{{ route('tagtoa.start.store') }}">@csrf
            <input type="hidden" name="kind" value="{{ $q[0] }}">
            <label class="lbl">{{ $q[4] }} *</label>
            <input class="inp" name="name" required maxlength="120">
            <label class="lbl">{{ __('WhatsApp (optionnel)') }}</label>
            <input class="inp" name="whatsapp" inputmode="tel" placeholder="+509…">
            <button class="btn btn-p" style="margin-top:12px;width:100%"><i class="fa-solid fa-bolt"></i> {{ __('Créer maintenant') }}</button>
        </form>
    </div>
    @endforeach
</div>

{{-- Autres modules (formulaires complets) --}}
<div class="h-row" style="margin-top:24px"><h2>{{ __('Ou démarrez avec') }}</h2></div>
<div class="grid g3">
    @foreach([
        ['site',    __('Site web vitrine'),        'fa-globe',          route('tagtoa.site.dashboard.create')],
        ['event',   __('Événement + billetterie'), 'fa-ticket',         route('tagtoa.event.dashboard.create')],
        ['booking', __('Rendez-vous'),             'fa-calendar-check', route('tagtoa.booking.dashboard.create')],
    ] as $m)
    <a class="card" href="{{ $m[3] }}" style="display:flex;align-items:center;gap:14px">
        <div class="ic" style="width:42px;height:42px;border-radius:11px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:18px"><i class="fa-solid {{ $m[2] }}"></i></div>
        <b style="font-family:var(--fh)">{{ $m[1] }}</b>
        <i class="fa-solid fa-arrow-right" style="margin-left:auto;color:var(--muted)"></i>
    </a>
    @endforeach
</div>

{{-- Étape 3 : après création --}}
<div class="card" style="margin-top:24px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <i class="fa-solid fa-qrcode" style="font-size:28px;color:var(--blue-deep)"></i>
    <div style="flex:1;min-width:220px">
        <b style="font-family:var(--fh)">{{ __('Après la création') }}</b>
        <div style="color:var(--muted);font-size:13.5px">{{ __('Téléchargez votre QR code et votre affiche imprimable dans « QR & Partage » — collez-la sur votre comptoir, vos tables, vos cartes NFC.') }}</div>
    </div>
    <a class="btn btn-o" href="{{ url('/tagtoa/qr') }}"><i class="fa-solid fa-qrcode"></i> {{ __('QR & Partage') }}</a>
</div>
@endsection

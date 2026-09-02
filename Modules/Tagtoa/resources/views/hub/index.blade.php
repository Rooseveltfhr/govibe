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
    <div class="stat"><div class="ic"><i class="fa-solid fa-utensils"></i></div><div class="v">{{ $stats['menus'] }}</div><div class="k">{{ __('Menus') }}</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-money-bill-transfer"></i></div><div class="v">{{ $stats['pay_pages'] }}</div><div class="k">{{ __('Liens de paiement') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-bell"></i></div><div class="v">{{ $stats['pay_pending'] }}</div><div class="k">{{ __('Preuves à vérifier') }}</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-ticket"></i></div><div class="v">{{ $stats['events'] }}</div><div class="k">{{ __('Événements') }}</div></div>
</div>

<div class="h-row" style="margin-top:26px"><h2>{{ __('Vos outils TAGTOA') }}</h2></div>
{{-- Même source que la barre latérale (DashboardModules) : les deux listes ne
     peuvent plus diverger quand un module est activé ou masqué. --}}
<div class="grid g3">
    @foreach(\Modules\Tagtoa\App\Support\DashboardModules::enabled('module') as $m)
        <a class="card" href="{{ url($m['url']) }}" style="display:block;transition:transform .12s,box-shadow .15s" onmouseover="this.style.boxShadow='0 8px 26px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
            <div class="ic" style="width:46px;height:46px;border-radius:12px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:20px"><i class="fa-solid {{ $m['icon'] }}"></i></div>
            <b style="font-family:var(--fh);font-size:16px;display:block;margin-top:12px">{{ __($m['label']) }}</b>
            <p style="font-size:13.5px;color:var(--muted);margin-top:4px">{{ __($m['desc']) }}</p>
        </a>
    @endforeach
</div>

<div class="h-row" style="margin-top:26px"><h2>{{ __('Suivi & compte') }}</h2></div>
<div class="grid g3">
    @foreach(\Modules\Tagtoa\App\Support\DashboardModules::enabled('account') as $m)
        <a class="card" href="{{ url($m['url']) }}" style="display:block;transition:transform .12s,box-shadow .15s" onmouseover="this.style.boxShadow='0 8px 26px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
            <div class="ic" style="width:46px;height:46px;border-radius:12px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:20px"><i class="fa-solid {{ $m['icon'] }}"></i></div>
            <b style="font-family:var(--fh);font-size:16px;display:block;margin-top:12px">{{ __($m['label']) }}</b>
            <p style="font-size:13.5px;color:var(--muted);margin-top:4px">{{ __($m['desc']) }}</p>
        </a>
    @endforeach
</div>
@endsection

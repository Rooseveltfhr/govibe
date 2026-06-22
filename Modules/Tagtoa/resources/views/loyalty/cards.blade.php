@extends('tagtoa::layouts.dashboard')
@section('title', __('Cartes'))
@section('page', __('Cartes').' — '.$program->name)

@section('content')
<a href="{{ route('tagtoa.loyalty.dashboard.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>

@if(session('new_card'))
    @php $nc = session('new_card'); @endphp
    <div class="card" style="margin-top:14px;border-color:var(--amber);background:#fff9ef">
        <b style="font-family:var(--fh)">{{ __('Carte émise — notez le CVC (affiché une seule fois) :') }}</b>
        <div style="font-family:var(--fh);margin-top:8px;line-height:1.9">
            <div><i class="fa-solid fa-credit-card"></i> {{ $nc['number'] }}</div>
            <div><i class="fa-solid fa-lock"></i> CVC : <b>{{ $nc['cvc'] }}</b></div>
            <div><i class="fa-solid fa-link"></i> <a href="{{ $nc['url'] }}" target="_blank" style="color:var(--blue)">{{ $nc['url'] }}</a></div>
        </div>
    </div>
@endif

<div class="card" style="margin-top:14px">
    <div class="h-row"><h2><i class="fa-solid fa-plus"></i> {{ __('Émettre une carte') }}</h2></div>
    <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.cards.issue',$program->id) }}" class="row" style="align-items:flex-end">
        @csrf
        <div style="flex:2"><label class="lbl">{{ __('Nom du titulaire') }}</label><input class="inp" name="cardholder_name" required></div>
        <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="cardholder_phone"></div>
        <div><label class="lbl">{{ __('Solde initial') }}</label><input class="inp" type="number" step="0.01" name="balance"></div>
        <button class="btn btn-p" style="flex:0">{{ __('Émettre') }}</button>
    </form>
</div>

@if($cards->isEmpty())
    <div class="card" style="margin-top:14px"><div class="empty"><i class="fa-regular fa-credit-card"></i>{{ __('Aucune carte émise.') }}</div></div>
@else
    <div style="margin-top:14px;display:flex;flex-direction:column;gap:12px">
    @foreach($cards as $c)
        <div class="card">
            <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
                <div>
                    <b style="font-family:var(--fh)">{{ $c->cardholder_name }}</b>
                    <span class="pill {{ $c->status===1?'g':($c->status===2?'r':'n') }}">{{ $c->status_label }}</span>
                    <div style="font-family:var(--fh);color:var(--muted);font-size:13px;margin-top:4px">{{ $c->masked_number }} · exp {{ optional($c->expiry_date)->format('m/y') }}</div>
                    <div style="font-size:14px;margin-top:4px"><b>{{ number_format($c->balance,2) }} {{ $program->currency }}</b> · <i class="fa-solid fa-star" style="color:#E0A800"></i> {{ number_format($c->points) }} pts</div>
                    <a href="{{ $c->public_url }}" target="_blank" style="color:var(--blue);font-size:13px">{{ __('Voir la carte') }} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;min-width:230px">
                    <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.cards.topup',$c->id) }}" style="display:flex;gap:6px">@csrf<input class="inp" type="number" step="0.01" name="amount" placeholder="{{ __('Recharger') }}" required><button class="btn btn-sm" style="background:var(--green);color:#fff">+</button></form>
                    <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.cards.redeem',$c->id) }}" style="display:flex;gap:6px">@csrf<input class="inp" type="number" step="0.01" name="amount" placeholder="{{ __('Débiter') }}" required><button class="btn btn-o btn-sm" style="color:var(--red)">−</button></form>
                </div>
            </div>
        </div>
    @endforeach
    </div>
    <div style="margin-top:16px">{{ $cards->links() }}</div>
@endif
@endsection

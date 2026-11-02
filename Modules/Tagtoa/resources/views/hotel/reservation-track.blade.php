{{-- HM-3 — le suivi d'une demande de réservation, par référence + secret. Rien d'interne, rien d'un autre invité.
     Tout est échappé ({{ }}). Pas de script, pas de ressource externe. Aucun paiement : on règle sur place. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $hotel?->name }} — {{ $resa->reference }}</title>
    <style>
        :root{--blk:#0A0A0A;--green:#2cb809;--muted:#6B7280;--bd:rgba(0,0,0,.09);--bg:#F7F8F6}
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,sans-serif;background:var(--bg);color:var(--blk);padding:18px}
        .w{max-width:520px;margin:0 auto}
        .card{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:20px;margin-bottom:14px}
        h1{font-size:20px;margin:0 0 4px}.m{color:var(--muted);font-size:14px;line-height:1.55}
        .st{display:inline-block;border-radius:999px;padding:5px 14px;font-weight:700;font-size:14px;background:#EEF2EE}
        .st.confirmed{background:#DDF5D3;color:#1b7a05}.st.refused,.st.cancelled{background:#FDECEA;color:#C0392B}
        .ok{background:#DDF5D3;color:#1b7a05;border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:14px}
        .err{background:#FDECEA;color:#C0392B;border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:14px}
        .btn{border:1.5px solid var(--bd);border-radius:10px;background:#fff;padding:9px 16px;font-weight:700;font-size:14px;cursor:pointer}
        ul{list-style:none;margin:6px 0 0;padding:0;font-size:14px}li{margin:2px 0}.c{color:var(--green);margin-right:6px}
        a{color:inherit}
    </style>
</head>
<body>
<div class="w">
    @if(session('guest_ok'))<div class="ok">{{ session('guest_ok') }}</div>@endif
    @if(session('guest_error'))<div class="err">{{ session('guest_error') }}</div>@endif
    <div class="card">
        <h1>{{ $resa->room_type_name }} — {{ $resa->plan_label }}</h1>
        <p class="m">{{ $hotel?->name }}</p>
        <p style="margin:14px 0 6px"><span class="st {{ $resa->status }}">{{ __($resa->status_label) }}</span></p>
        <p class="m">
            {{ __('Référence') }} : <b>{{ $resa->reference }}</b><br>
            {{ __('Arrivée') }} : <b>{{ $resa->starts_on?->format('d/m/Y') }}@if($resa->start_time) {{ substr((string) $resa->start_time, 0, 5) }}@endif</b>
            @if($resa->plan_kind === 'night') · {{ trans_choice(':n nuit|:n nuits', $resa->nights, ['n' => $resa->nights]) }}@endif
            @if($resa->guests) · {{ trans_choice(':n personne|:n personnes', $resa->guests, ['n' => $resa->guests]) }}@endif<br>
            {{ __('Total') }} : <b>{{ \Modules\Tagtoa\App\Support\Money::format((float) $resa->total, $resa->currency) }}</b>
            <span>({{ __('à régler sur place') }})</span>
        </p>
        @php $incl = $resa->inclusionItems(); @endphp
        @if($incl)
            <p class="m" style="margin:10px 0 0"><b>{{ __('Inclus dans ce prix') }}</b></p>
            <ul>@foreach($incl as $i)<li><span class="c">✓</span>{{ $i['label'] }}</li>@endforeach</ul>
        @endif
        @if($resa->note)<p class="m" style="white-space:pre-line">{{ __('Votre précision') }} : {{ $resa->note }}</p>@endif
        @if($resa->status === 'refused' && $resa->reason)<p class="m">{{ __('Motif') }} : {{ $resa->reason }}</p>@endif
        @if($resa->status === 'new')<p class="m">{{ __('Votre demande a été transmise. L\'hôtel vous confirmera ici (et peut vous appeler).') }}</p>@endif
        @if($resa->status === 'confirmed')<p class="m">{{ __('Votre réservation est confirmée. Le prix et ce qui est inclus sont ceux ci-dessus.') }}</p>@endif
        <p class="m" style="margin-top:12px">{{ __('Conservez cette page : c\'est votre lien de suivi.') }}</p>
        @if($resa->isOpen())
            <form method="POST" action="{{ route('tagtoa.hotel.reservation.cancel', [$resa->reference, $secret]) }}" style="margin-top:10px">
                @csrf
                <button class="btn">{{ __('Annuler ma demande') }}</button>
            </form>
        @endif
    </div>
    @php $tel = $profile?->reception_phone ?: $hotel?->phone; @endphp
    @if($tel)
        <div class="card"><p class="m">{{ __('Réception') }} : <a href="tel:{{ preg_replace('/[^0-9+]/', '', $tel) }}">{{ $tel }}</a></p></div>
    @endif
</div>
</body>
</html>

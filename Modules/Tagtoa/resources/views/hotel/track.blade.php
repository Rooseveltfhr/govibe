{{-- H5 — le suivi d'une demande, par référence + secret. Rien d'interne, rien d'un autre invité,
     aucune identité du personnel. Tout est échappé ({{ }}). Pas de script, pas de ressource externe. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $hotel?->name }} — {{ $req->reference }}</title>
    <style>
        :root{--blk:#0A0A0A;--green:#2cb809;--muted:#6B7280;--bd:rgba(0,0,0,.09);--bg:#F7F8F6}
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,sans-serif;background:var(--bg);color:var(--blk);padding:18px}
        .w{max-width:520px;margin:0 auto}
        .card{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:20px;margin-bottom:14px}
        h1{font-size:20px;margin:0 0 4px}.m{color:var(--muted);font-size:14px;line-height:1.55}
        .st{display:inline-block;border-radius:999px;padding:5px 14px;font-weight:700;font-size:14px;background:#EEF2EE}
        .st.done{background:#DDF5D3;color:#1b7a05}.st.rejected,.st.cancelled{background:#FDECEA;color:#C0392B}
        .ok{background:#DDF5D3;color:#1b7a05;border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:14px}
        .err{background:#FDECEA;color:#C0392B;border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:14px}
        .btn{border:1.5px solid var(--bd);border-radius:10px;background:#fff;padding:9px 16px;font-weight:700;font-size:14px;cursor:pointer}
        a{color:inherit}
    </style>
</head>
<body>
<div class="w">
    @if(session('guest_ok'))<div class="ok">{{ session('guest_ok') }}</div>@endif
    @if(session('guest_error'))<div class="err">{{ session('guest_error') }}</div>@endif
    <div class="card">
        <h1>{{ $req->service_name }}</h1>
        <p class="m">{{ $hotel?->name }}@if($room) · {{ $room->label }}@endif</p>
        <p style="margin:14px 0 6px"><span class="st {{ $req->status }}">{{ __($req->status_label) }}</span></p>
        <p class="m">{{ __('Référence') }} : <b>{{ $req->reference }}</b><br>{{ __('Demandée le') }} {{ $req->created_at?->format('d/m/Y H:i') }}</p>
        @if($req->note)<p class="m" style="white-space:pre-line">{{ __('Votre précision') }} : {{ $req->note }}</p>@endif
        @if($req->status === 'rejected' && $req->reason)<p class="m">{{ __('Motif') }} : {{ $req->reason }}</p>@endif
        <p class="m" style="margin-top:12px">{{ __('Conservez cette page : c\'est votre lien de suivi.') }}</p>
        @if($req->status === 'new')
            <form method="POST" action="{{ route('tagtoa.hotel.track.cancel', [$req->reference, $secret]) }}" style="margin-top:10px">
                @csrf
                <button class="btn">{{ __('Annuler ma demande') }}</button>
            </form>
        @endif
    </div>
    @if($profile && ($profile->reception_phone || $profile->reception_24h))
        <div class="card"><p class="m">{{ __('Réception') }} :
            @if($profile->reception_phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $profile->reception_phone) }}">{{ $profile->reception_phone }}</a>@endif</p></div>
    @endif
</div>
</body>
</html>

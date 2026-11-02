{{-- H4 — la page d'une chambre : ce qu'un client voit déjà à la réception, rien de plus.
     Tout est échappé ({{ }}). Pas de script, pas de ressource externe. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $hotel->name }} — {{ $room->label }}</title>
    <style>
        :root{--blk:#0A0A0A;--green:#2cb809;--muted:#6B7280;--bd:rgba(0,0,0,.09);--bg:#F7F8F6}
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,sans-serif;background:var(--bg);color:var(--blk);padding:18px}
        .w{max-width:520px;margin:0 auto}
        .card{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:20px;margin-bottom:14px}
        h1{font-size:22px;margin:0 0 4px}h2{font-size:16px;margin:0 0 10px}
        .room{display:inline-block;background:var(--blk);color:#fff;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:700}
        .m{color:var(--muted);font-size:14px;line-height:1.55}
        .svc{padding:10px 0;border-top:1px solid var(--bd)}.svc:first-of-type{border-top:0}
        .svc b{display:block}.pr{color:var(--green);font-weight:700;font-size:14px}
        .err{background:#FDECEA;color:#C0392B;border-radius:12px;padding:12px 14px;margin-bottom:14px;font-size:14px}
        .btn{display:inline-block;margin-top:8px;border:0;border-radius:10px;background:var(--blk);color:#fff;padding:9px 16px;font-weight:700;font-size:14px;cursor:pointer}
        textarea{width:100%;margin-top:8px;border:1.5px solid var(--bd);border-radius:10px;padding:10px;font:inherit;font-size:14px}
        a{color:inherit}
    </style>
</head>
<body>
<div class="w">
    @if(session('guest_error'))<div class="err">{{ session('guest_error') }}</div>@elseif($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <div class="card">
        <h1>{{ $hotel->name }}</h1>
        <span class="room">{{ $room->label }}</span>
        @if($profile?->description)<p class="m" style="margin-top:12px">{{ $profile->description }}</p>@endif
    </div>

    @if($profile && ($profile->reception_phone || $profile->reception_email || $profile->reception_24h || $profile->reception_hours))
        <div class="card">
            <h2>{{ __('Réception') }}</h2>
            <p class="m">
                @if($profile->reception_24h){{ __('Ouverte 24 h/24') }}<br>@elseif($profile->reception_hours){{ $profile->reception_hours }}<br>@endif
                @if($profile->reception_phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $profile->reception_phone) }}">{{ $profile->reception_phone }}</a><br>@endif
                @if($profile->reception_email){{ $profile->reception_email }}@endif
            </p>
        </div>
    @endif

    @if($services->isNotEmpty())
        <div class="card">
            <h2>{{ __('Services') }}</h2>
            @foreach($services as $s)
                <div class="svc">
                    <b>{{ $s->nameFor(app()->getLocale()) }}</b>
                    @if($s->description)<span class="m">{{ $s->description }}</span>@endif
                    @if($s->price !== null && (float) $s->price > 0)<div class="pr">{{ number_format((float) $s->price, 2) }} {{ $hotel->currency }}</div>@endif
                    @if(in_array($s->kind, $requestable, true))
                        <form method="POST" action="{{ route('tagtoa.hotel.request', $standId) }}">
                            @csrf
                            <input type="hidden" name="service_id" value="{{ $s->id }}">
                            <input type="hidden" name="tk" value="{{ $token }}">
                            <textarea name="note" rows="2" maxlength="300" placeholder="{{ __('Précision (facultatif)') }}"></textarea>
                            <button class="btn">{{ __('Demander') }}</button>
                        </form>
                    @endif
                </div>
            @endforeach
            <p class="m" style="margin-top:10px">{{ __('Aucun compte ni numéro à donner : vous recevrez un lien de suivi.') }}</p>
        </div>
    @endif

    @if($profile?->guest_info)
        <div class="card"><h2>{{ __('Infos utiles') }}</h2><p class="m" style="white-space:pre-line">{{ $profile->guest_info }}</p></div>
    @endif
</div>
</body>
</html>

{{-- TAGTOA Event — billet plein écran. Variables : $ticket, $event --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('Billet') }} — {{ $event->title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blk:#0A0A0A;--blue:#2cb809;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}body{font-family:var(--fb);background:#0A0A0A;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        .tk{max-width:380px;width:100%;background:#fff;color:var(--blk);border-radius:22px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.5)}
        .top{background:linear-gradient(135deg,#0A0A0A,#239406);color:#fff;padding:22px;text-align:center}.top .b{font:600 11px var(--fh);letter-spacing:.14em;text-transform:uppercase;opacity:.8}.top h1{font:700 19px var(--fh);margin-top:6px}
        .qr{padding:26px;text-align:center}.qr svg,.qr img{width:220px;height:220px}
        .dash{border:0;border-top:2px dashed rgba(0,0,0,.12);margin:0 20px}
        .info{padding:18px 22px}.kv{display:flex;justify-content:space-between;padding:8px 0;font-size:14px;border-bottom:1px solid rgba(0,0,0,.06)}.kv:last-child{border:0}.kv span{color:#888}.kv b{font-family:var(--fh)}
        .st{display:inline-flex;align-items:center;gap:6px;font:600 12px var(--fh);padding:5px 12px;border-radius:999px;margin-top:6px}.ok{background:#eafaf3;color:#0e5f44}.used{background:#fdecea;color:#9a2820}
        .code{font-family:var(--fh);letter-spacing:.2em;text-align:center;color:#888;font-size:13px;padding:0 0 18px}
        .foot{text-align:center;color:#aaa;font-size:11px;padding:0 0 18px}
    </style>
</head>
<body>
<div class="tk">
    <div class="top"><div class="b"><i class="fa-solid fa-wifi"></i> TAGTOA EVENT · NFC / QR</div><h1>{{ $event->title }}</h1></div>
    <div class="qr">
        @php try { $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->margin(0)->generate($ticket->code); } catch (\Throwable $e) { $qr = null; } @endphp
        @if($qr){!! $qr !!}@else<img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($ticket->code) }}" alt="QR">@endif
    </div>
    <hr class="dash">
    <div class="info">
        <div class="kv"><span>{{ __('Titulaire') }}</span><b>{{ $ticket->holder_name }}</b></div>
        <div class="kv"><span>{{ __('Type') }}</span><b>{{ optional($ticket->ticketType)->name }}</b></div>
        @if($event->starts_at)<div class="kv"><span>{{ __('Date') }}</span><b>{{ $event->starts_at->format('d/m/Y H:i') }}</b></div>@endif
        @if($event->venue)<div class="kv"><span>{{ __('Lieu') }}</span><b>{{ $event->venue }}</b></div>@endif
        <div style="text-align:center">
            @if($ticket->checked_in)<span class="st used"><i class="fa-solid fa-circle-check"></i> {{ __('Déjà entré') }}</span>
            @elseif($ticket->isValid())<span class="st ok"><i class="fa-solid fa-circle-check"></i> {{ __('Valide') }}</span>
            @else<span class="st used"><i class="fa-solid fa-circle-xmark"></i> {{ __('Annulé') }}</span>@endif
        </div>
    </div>
    <div class="code">{{ $ticket->code }}</div>
    <div class="foot">{{ __('Propulsé par TAGTOA') }}</div>
</div>
</body>
</html>

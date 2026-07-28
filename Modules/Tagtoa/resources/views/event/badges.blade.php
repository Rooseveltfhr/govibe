{{-- TAGTOA EVENT — planche de badges QR imprimables (backup si NFC échoue). Variables: $event, $tickets --}}
@php use Modules\Tagtoa\App\Support\Qr; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Badges') }} — {{ $event->title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root{--green:#2cb809;--ink:#111;--bd:rgba(0,0,0,.14);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        .bar h1,.badge .nm{font-family:'Anton',sans-serif!important;font-weight:400!important;letter-spacing:.01em}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);color:var(--ink);background:#f4f4f5;padding:16px}
        .bar{max-width:900px;margin:0 auto 14px;display:flex;gap:10px;align-items:center}
        .bar h1{font:700 18px var(--fh);flex:1}
        .btn{border:0;border-radius:10px;padding:11px 18px;font:700 14px var(--fh);cursor:pointer;background:var(--green);color:#fff;text-decoration:none}
        .sheet{max-width:900px;margin:0 auto;display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
        .badge{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:14px;text-align:center;break-inside:avoid}
        .badge .ev{font:700 11px var(--fh);text-transform:uppercase;letter-spacing:.06em;color:var(--green)}
        .badge .nm{font:700 15px var(--fh);margin:4px 0 2px}
        .badge .tt{font-size:12px;color:#666;margin-bottom:8px}
        .badge .qr{width:150px;height:150px;margin:0 auto}
        .badge .qr svg{width:100%;height:100%}
        .badge .code{font:600 11px var(--fh);letter-spacing:.08em;color:#888;margin-top:6px}
        .empty{grid-column:1/-1;text-align:center;color:#888;padding:40px}
        @media print{
            body{background:#fff;padding:0}
            .bar{display:none}
            .sheet{gap:0}
            .badge{border:1px dashed #bbb;border-radius:0}
            @page{margin:10mm}
        }
    </style>
</head>
<body>
    <div class="bar">
        <h1>{{ __('Badges') }} — {{ $event->title }} <span style="color:#888;font-weight:400">({{ $tickets->count() }})</span></h1>
        <a href="javascript:window.print()" class="btn"><i></i> {{ __('Imprimer') }}</a>
    </div>
    <div class="sheet">
        @forelse($tickets as $t)
            <div class="badge">
                <div class="ev">{{ $event->title }}</div>
                <div class="nm">{{ $t->holder_name ?: __('Participant') }}</div>
                <div class="tt">{{ optional($t->ticketType)->name ?: __('Billet') }}</div>
                <div class="qr">{!! Qr::svg($t->code, 150) !!}</div>
                <div class="code">{{ $t->code }}</div>
            </div>
        @empty
            <div class="empty">{{ __('Aucun billet à imprimer. Encodez des cartes d\'abord.') }}</div>
        @endforelse
    </div>
</body>
</html>

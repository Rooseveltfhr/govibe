{{-- TAGTOA — affiche QR imprimable. Variables : $name, $label, $url --}}
@php use Modules\Tagtoa\App\Support\Qr; $svg = Qr::svg($url, 360); @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $name }} — QR</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Nunito:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--green:#16A34A;--ink:#0A0A0A;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:#eef0ee;color:var(--ink);display:flex;flex-direction:column;align-items:center;padding:24px}
        .bar{display:flex;gap:10px;margin-bottom:18px}
        .btn{border:0;border-radius:11px;padding:12px 22px;font:700 14px var(--fh);cursor:pointer;display:inline-flex;align-items:center;gap:8px}
        .btn-p{background:var(--green);color:#fff}.btn-o{background:#fff;border:1px solid rgba(0,0,0,.12);color:var(--ink)}
        .poster{width:420px;max-width:100%;background:#fff;border-radius:24px;padding:44px 36px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.12)}
        .brand{display:inline-flex;align-items:center;gap:9px;font:700 18px var(--fh);color:var(--green);margin-bottom:6px}
        .brand .lg{width:30px;height:30px;border-radius:8px;background:var(--green);color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px}
        .lbl{font:700 11px var(--fh);letter-spacing:.14em;text-transform:uppercase;color:#999;margin-top:14px}
        h1{font:700 26px var(--fh);margin:4px 0 20px}
        .qr{background:#fff;border:2px solid #f0f0f0;border-radius:18px;padding:18px;display:inline-block;line-height:0}
        .scan{margin-top:20px;font:700 17px var(--fh);display:flex;align-items:center;justify-content:center;gap:9px}
        .scan i{color:var(--green)}
        .url{color:var(--green);font-size:13px;margin-top:8px;word-break:break-all}
        .foot{margin-top:22px;color:#aaa;font-size:12px}.foot b{font-family:var(--fh);color:#555}
        @media print{body{background:#fff;padding:0}.bar{display:none}.poster{box-shadow:none;border-radius:0;width:100%}}
    </style>
</head>
<body>
    <div class="bar">
        <button class="btn btn-p" onclick="window.print()"><i class="fa-solid fa-print"></i> {{ __('Imprimer') }}</button>
        <a class="btn btn-o" href="{{ url()->previous() }}"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    </div>
    <div class="poster">
        <span class="brand"><span class="lg"><i class="fa-solid fa-bolt"></i></span>TAGTOA</span>
        <div class="lbl">{{ __($label) }}</div>
        <h1>{{ $name }}</h1>
        <div class="qr">
            @if($svg){!! $svg !!}@else<img src="{{ Qr::imgUrl($url, 360) }}" alt="QR" width="360" height="360">@endif
        </div>
        <div class="scan"><i class="fa-solid fa-mobile-screen-button"></i> {{ __('Scannez avec votre téléphone') }}</div>
        <div class="url">{{ $url }}</div>
        <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
    </div>
</body>
</html>

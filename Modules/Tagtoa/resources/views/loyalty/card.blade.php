{{-- TAGTOA Loyalty — carte publique (NFC/QR). Variables : $card, $program --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ $program->name }} — TAGTOA</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blk:#0A0A0A;--bg:#F5F5F3;--sf:#fff;--blue:#2cb809;--blue-deep:#239406;--blue-pale:rgba(44,184,9,.08);--green:#1D9E75;--red:#E0473E;--bd:rgba(0,0,0,.08);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--blk);line-height:1.5}
        .wrap{max-width:480px;margin:0 auto;min-height:100vh;padding:22px 16px 40px}
        .brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
        .brand img,.brand .ph{width:38px;height:38px;border-radius:9px;object-fit:cover}
        .brand .ph{background:var(--blk);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px}
        .brand b{font-family:var(--fh);font-weight:700;font-size:16px}.brand span{font-size:12px;color:#888;display:block}
        .card{position:relative;border-radius:20px;padding:22px;color:#fff;overflow:hidden;background:linear-gradient(135deg,#0A0A0A 0%,#239406 100%);box-shadow:0 14px 40px rgba(35,148,6,.28)}
        .card::after{content:"";position:absolute;right:-50px;top:-50px;width:180px;height:180px;background:radial-gradient(circle,rgba(44,184,9,.6),transparent 70%)}
        .card .nfc{position:absolute;top:20px;right:20px;font-size:20px;opacity:.85;transform:rotate(90deg)}
        .chip{width:38px;height:28px;border-radius:6px;background:linear-gradient(135deg,#e9c46a,#d4a017);position:absolute;top:54px;left:22px;opacity:.9}
        .lbl{font:600 11px var(--fh);letter-spacing:.14em;text-transform:uppercase;opacity:.7}
        .num{font-family:var(--fh);font-size:21px;letter-spacing:.13em;margin:34px 0 4px;position:relative}
        .rw{display:flex;justify-content:space-between;align-items:flex-end;margin-top:14px;position:relative}
        .rw small{font-size:10px;letter-spacing:.1em;text-transform:uppercase;opacity:.6;display:block}.rw b{font:600 14px var(--fh)}
        .pill{display:inline-flex;align-items:center;gap:6px;font:600 12px var(--fh);padding:5px 12px;border-radius:999px;margin-top:14px}
        .pill.ok{background:#eafaf3;color:#0e5f44}.pill.no{background:#fdecea;color:#9a2820}
        .stats{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}
        .stat{background:var(--sf);border:1px solid var(--bd);border-radius:16px;padding:16px}
        .stat i{color:var(--blue);font-size:18px}.stat .v{font:700 22px var(--fh);margin-top:6px}.stat .k{font-size:12px;color:#888}
        .sec{font:600 13px var(--fh);letter-spacing:.05em;text-transform:uppercase;color:#777;margin:26px 0 12px}
        .qrbox{background:var(--sf);border:1px solid var(--bd);border-radius:18px;padding:20px;text-align:center}
        .qrbox svg,.qrbox img{width:180px;height:180px}.qrbox p{font-size:12.5px;color:#888;margin-top:10px}
        .rew{display:flex;align-items:center;gap:12px;background:var(--sf);border:1px solid var(--bd);border-radius:14px;padding:13px 15px;margin-bottom:9px}
        .rew .ic{width:42px;height:42px;border-radius:11px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:17px}
        .rew .tx{flex:1}.rew .tx b{font:600 14.5px var(--fh);display:block}.rew .tx span{font-size:12.5px;color:#888}
        .rew .pts{font:700 13px var(--fh);color:var(--blue-deep)}.rew.lock{opacity:.55}
        .tx{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid var(--bd);font-size:14px}.tx:last-child{border:0}
        .tx b{font-family:var(--fh);display:block}.tx span{font-size:12px;color:#999}.tx .a{font:700 14px var(--fh)}.cr{color:var(--green)}.dr{color:var(--red)}
        .foot{text-align:center;padding:26px 0 0;color:#aaa;font-size:12px}.foot b{font-family:var(--fh);color:#777}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        @if($program->logo_url)<img src="{{ $program->logo_url }}" alt="">@else<span class="ph"><i class="fa-solid fa-id-card"></i></span>@endif
        <div><b>{{ $program->name }}</b><span>TAGTOA LOYALTY · NFC / QR</span></div>
    </div>

    <div class="card">
        <i class="fa-solid fa-wifi nfc"></i><div class="chip"></div>
        <div class="lbl">{{ __('Carte de fidélité') }}</div>
        <div class="num">{{ $card->masked_number }}</div>
        <div class="rw">
            <div><small>{{ __('Titulaire') }}</small><b>{{ \Illuminate\Support\Str::upper($card->cardholder_name) }}</b></div>
            <div style="text-align:right"><small>{{ __('Expire') }}</small><b>{{ optional($card->expiry_date)->format('m/y') }}</b></div>
        </div>
    </div>

    @if($card->isActive())<span class="pill ok"><i class="fa-solid fa-circle-check"></i> {{ __('Active') }}</span>
    @else<span class="pill no"><i class="fa-solid fa-circle-xmark"></i> {{ $card->status_label }}</span>@endif

    <div class="stats">
        <div class="stat"><i class="fa-solid fa-wallet"></i><div class="v">{{ number_format($card->balance,2) }}</div><div class="k">{{ __('Solde') }} ({{ $program->currency }})</div></div>
        <div class="stat"><i class="fa-solid fa-star"></i><div class="v">{{ number_format($card->points) }}</div><div class="k">{{ __('Points') }}</div></div>
    </div>

    <p class="sec">{{ __('Présenter en caisse') }}</p>
    <div class="qrbox">
        @php try { $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(180)->margin(0)->generate($card->public_url); } catch (\Throwable $e) { $qr = null; } @endphp
        @if($qr){!! $qr !!}@else<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($card->public_url) }}" alt="QR" loading="lazy">@endif
        <p>{{ __('Scannez ou tapez la carte NFC') }}</p>
    </div>

    @if($program->activeRewards->isNotEmpty())
        <p class="sec">{{ __('Récompenses') }}</p>
        @foreach($program->activeRewards as $rw)
            <div class="rew {{ $card->points < $rw->points_required ? 'lock' : '' }}">
                <span class="ic"><i class="fa-solid fa-gift"></i></span>
                <div class="tx" style="border:0;padding:0;display:block"><b>{{ $rw->name }}</b><span>{{ $rw->description ?: $rw->discount_label }}</span></div>
                <span class="pts">{{ number_format($rw->points_required) }} pts</span>
            </div>
        @endforeach
    @endif

    @if($card->transactions->isNotEmpty())
        <p class="sec">{{ __('Historique') }}</p>
        @foreach($card->transactions as $t)
            <div class="tx"><div><b>{{ $t->type_label }}</b><span>{{ $t->created_at->format('d/m/Y H:i') }}</span></div>
                <div class="a {{ $t->isCredit() ? 'cr' : 'dr' }}">{{ $t->isCredit() ? '+' : '−' }}{{ number_format(abs($t->amount),2) }}@if($t->points_delta)<small style="color:#999"> ({{ $t->points_delta>0?'+':'' }}{{ $t->points_delta }} pts)</small>@endif</div>
            </div>
        @endforeach
    @endif

    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
</div>
</body>
</html>

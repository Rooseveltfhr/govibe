{{-- ============================================================
     TAGTOA LOYALTY — Carte de fidélité publique (NFC / QR)
     Standalone HTML · mobile-first · vanilla JS · optimisé 3G
     Variables : $card (TaGtoaLoyaltyCard), $program (TaGtoaLoyaltyProgram)
     ============================================================ --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ $program->name }} — TAGTOA LOYALTY</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root{
            --tagtoa-black:#0A0A0A; --tagtoa-white:#FFF; --tagtoa-bg:#F5F5F3; --tagtoa-surface:#FFF;
            --tagtoa-blue:#0055FF; --tagtoa-blue-deep:#0040CC; --tagtoa-blue-pale:rgba(0,85,255,.08);
            --tagtoa-green:#1D9E75; --tagtoa-red:#E0473E; --tagtoa-border:rgba(0,0,0,.08);
            --fh:'Space Grotesk',sans-serif; --fb:'Nunito',-apple-system,sans-serif;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--tagtoa-bg);color:var(--tagtoa-black);line-height:1.5;-webkit-font-smoothing:antialiased}
        .wrap{max-width:480px;margin:0 auto;min-height:100vh;padding:22px 16px 40px}
        .brand{display:flex;align-items:center;gap:10px;margin-bottom:18px}
        .brand img{width:38px;height:38px;border-radius:9px;object-fit:cover}
        .brand .ph{width:38px;height:38px;border-radius:9px;background:var(--tagtoa-black);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px}
        .brand b{font-family:var(--fh);font-weight:700;font-size:16px}
        .brand span{font-size:12px;color:#888;display:block}

        /* La carte (look carte bancaire premium TAGTOA) */
        .card{position:relative;border-radius:20px;padding:22px;color:#fff;overflow:hidden;
            background:linear-gradient(135deg,#0A0A0A 0%,#0040CC 100%);
            box-shadow:0 14px 40px rgba(0,64,204,.28)}
        .card::after{content:"";position:absolute;right:-50px;top:-50px;width:180px;height:180px;
            background:radial-gradient(circle,rgba(0,85,255,.6) 0%,transparent 70%)}
        .card .nfc{position:absolute;top:20px;right:20px;font-size:20px;opacity:.85;transform:rotate(90deg)}
        .card .lbl{font-family:var(--fh);font-size:11px;letter-spacing:.14em;text-transform:uppercase;opacity:.7}
        .card .num{font-family:var(--fh);font-size:21px;letter-spacing:.13em;margin:34px 0 4px;position:relative}
        .card .row{display:flex;justify-content:space-between;align-items:flex-end;margin-top:14px;position:relative}
        .card .row small{font-size:10px;letter-spacing:.1em;text-transform:uppercase;opacity:.6;display:block}
        .card .row b{font-family:var(--fh);font-weight:600;font-size:14px}
        .chip{width:38px;height:28px;border-radius:6px;background:linear-gradient(135deg,#e9c46a,#d4a017);position:absolute;top:54px;left:22px;opacity:.9}

        /* Solde + points */
        .stats{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}
        .stat{background:var(--tagtoa-surface);border:1px solid var(--tagtoa-border);border-radius:16px;padding:16px}
        .stat i{color:var(--tagtoa-blue);font-size:18px}
        .stat .v{font-family:var(--fh);font-weight:700;font-size:22px;margin-top:6px}
        .stat .k{font-size:12px;color:#888}

        .pill{display:inline-flex;align-items:center;gap:6px;font:600 12px var(--fh);padding:5px 12px;border-radius:999px;margin-top:14px}
        .pill.ok{background:#eafaf3;color:#0e5f44}
        .pill.no{background:#fdecea;color:#9a2820}

        .sec-t{font-family:var(--fh);font-weight:600;font-size:13px;letter-spacing:.05em;text-transform:uppercase;color:#777;margin:26px 0 12px}
        .qrbox{background:var(--tagtoa-surface);border:1px solid var(--tagtoa-border);border-radius:18px;padding:20px;text-align:center}
        .qrbox svg,.qrbox img{width:180px;height:180px}
        .qrbox p{font-size:12.5px;color:#888;margin-top:10px}

        .rew{display:flex;align-items:center;gap:12px;background:var(--tagtoa-surface);border:1px solid var(--tagtoa-border);border-radius:14px;padding:13px 15px;margin-bottom:9px}
        .rew .ic{width:42px;height:42px;border-radius:11px;background:var(--tagtoa-blue-pale);color:var(--tagtoa-blue-deep);display:flex;align-items:center;justify-content:center;font-size:17px}
        .rew .tx{flex:1}.rew .tx b{font-family:var(--fh);font-size:14.5px;display:block}.rew .tx span{font-size:12.5px;color:#888}
        .rew .pts{font:700 13px var(--fh);color:var(--tagtoa-blue-deep);white-space:nowrap}
        .rew.locked{opacity:.55}

        .tx-row{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid var(--tagtoa-border);font-size:14px}
        .tx-row:last-child{border:0}
        .tx-row .t b{font-family:var(--fh);display:block}.tx-row .t span{font-size:12px;color:#999}
        .tx-row .a{font-family:var(--fh);font-weight:700}
        .tx-row .a.cr{color:var(--tagtoa-green)}.tx-row .a.dr{color:var(--tagtoa-red)}
        .foot{text-align:center;padding:26px 0 0;color:#aaa;font-size:12px}.foot b{font-family:var(--fh);color:#777}
        @media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        @if($program->logo_url)
            <img src="{{ $program->logo_url }}" alt="{{ $program->name }}" loading="lazy">
        @else
            <span class="ph"><i class="fa-solid fa-id-card"></i></span>
        @endif
        <div><b>{{ $program->name }}</b><span>TAGTOA LOYALTY · NFC / QR</span></div>
    </div>

    {{-- La carte --}}
    <div class="card">
        <i class="fa-solid fa-wifi nfc"></i>
        <div class="chip"></div>
        <div class="lbl">{{ __('Carte de fidélité') }}</div>
        <div class="num">{{ $card->masked_number }}</div>
        <div class="row">
            <div>
                <small>{{ __('Titulaire') }}</small>
                <b>{{ \Illuminate\Support\Str::upper($card->cardholder_name) }}</b>
            </div>
            <div style="text-align:right">
                <small>{{ __('Expire') }}</small>
                <b>{{ optional($card->expiry_date)->format('m/y') }}</b>
            </div>
        </div>
    </div>

    @if($card->isActive())
        <span class="pill ok"><i class="fa-solid fa-circle-check"></i> {{ __('Carte active') }}</span>
    @else
        <span class="pill no"><i class="fa-solid fa-circle-xmark"></i> {{ $card->status_label }}</span>
    @endif

    {{-- Solde + points --}}
    <div class="stats">
        <div class="stat">
            <i class="fa-solid fa-wallet"></i>
            <div class="v">{{ number_format($card->balance, 2) }}</div>
            <div class="k">{{ __('Solde') }} ({{ $program->currency }})</div>
        </div>
        <div class="stat">
            <i class="fa-solid fa-star"></i>
            <div class="v">{{ number_format($card->points) }}</div>
            <div class="k">{{ __('Points') }}</div>
        </div>
    </div>

    {{-- QR --}}
    <p class="sec-t">{{ __('Présenter en caisse') }}</p>
    <div class="qrbox">
        @php
            $qr = null;
            try { $qr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(180)->margin(0)->generate($card->public_url); }
            catch (\Throwable $e) { $qr = null; }
        @endphp
        @if($qr) {!! $qr !!} @else
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($card->public_url) }}" alt="QR" loading="lazy">
        @endif
        <p>{{ __('Scannez ou tapez la carte NFC') }}</p>
    </div>

    {{-- Récompenses --}}
    @if($program->activeRewards->isNotEmpty())
        <p class="sec-t">{{ __('Récompenses') }}</p>
        @foreach($program->activeRewards as $rw)
            <div class="rew {{ $card->points < $rw->points_required ? 'locked' : '' }}">
                <span class="ic"><i class="fa-solid fa-gift"></i></span>
                <div class="tx">
                    <b>{{ $rw->name }}</b>
                    <span>{{ $rw->description ?: $rw->discount_label }}</span>
                </div>
                <span class="pts">{{ number_format($rw->points_required) }} pts</span>
            </div>
        @endforeach
    @endif

    {{-- Dernières transactions --}}
    @if($card->transactions->isNotEmpty())
        <p class="sec-t">{{ __('Historique') }}</p>
        @foreach($card->transactions as $t)
            <div class="tx-row">
                <div class="t">
                    <b>{{ $t->type_label }}</b>
                    <span>{{ $t->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="a {{ $t->isCredit() ? 'cr' : 'dr' }}">
                    {{ $t->isCredit() ? '+' : '−' }}{{ number_format(abs($t->amount), 2) }}
                    @if($t->points_delta) <small style="color:#999">({{ $t->points_delta > 0 ? '+' : '' }}{{ $t->points_delta }} pts)</small> @endif
                </div>
            </div>
        @endforeach
    @endif

    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
</div>
</body>
</html>

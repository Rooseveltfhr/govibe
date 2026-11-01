{{-- TAGTOA Pay — « Payer » une commande (menu, boutique). Public, mobile-first.
     Variables : $order, $type, $merchant, $backUrl, $trackUrl, $online, $methods --}}
@php
    use Modules\Tagtoa\App\Support\Money;
    $claimed = session('claimed') || $order->payment_claimed_at;
    $nothing = empty($online) && $methods->isEmpty();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('Payer') }} {{ $order->reference }} — TAGTOA</title>
    <link rel="stylesheet" href="{{ route('tagtoa.asset', 'tagtoa-fonts.css') }}">
    <link rel="stylesheet" href="/tagtoa-asset/fontawesome-6.5.1.css">
    <style>
        :root{--acc:#2cb809;--bg:#F5F5F3;--sf:#fff;--fg:#0A0A0A;--mut:#777;--bd:rgba(0,0,0,.09);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--fg);min-height:100vh;line-height:1.5}
        .wrap{max-width:480px;margin:0 auto;padding:22px 16px 60px}
        .card{background:var(--sf);border:1px solid var(--bd);border-radius:16px;padding:16px;margin-bottom:14px}
        h1{font:700 20px var(--fh)}.ref{color:var(--mut);font-size:14px;margin-top:2px}
        .tot{font:700 30px var(--fh);color:var(--acc);margin-top:10px}
        h2{font:700 13px var(--fh);letter-spacing:.05em;text-transform:uppercase;color:var(--mut);margin:20px 0 10px}
        .btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;border:0;border-radius:14px;padding:15px;font:700 15px var(--fh);color:#fff;text-decoration:none;cursor:pointer;margin-bottom:10px}
        .kv{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px dashed var(--bd);font-size:14px}.kv:last-of-type{border-bottom:0}
        .kv span{color:var(--mut)}.kv b{font-family:var(--fh);text-align:right;word-break:break-all}
        .instr{background:var(--bg);border-radius:12px;padding:11px 13px;font-size:13.5px;color:#555;margin-top:10px}
        .qr{text-align:center;margin-bottom:10px}.qr img{width:150px;height:150px;border-radius:12px;border:1px solid var(--bd)}
        .mh{display:flex;align-items:center;gap:10px;font:700 15px var(--fh);margin-bottom:8px}
        .lbl{font:600 12.5px var(--fh);color:#666;display:block;margin:12px 0 6px}
        .inp{width:100%;padding:12px 13px;border:1.5px solid var(--bd);border-radius:12px;font:15px var(--fb);background:#fff}
        .ok{background:#eafaf3;color:#0e5f44;border-radius:14px;padding:14px 16px;font-weight:700;margin-bottom:14px}
        .info{background:#fff5e6;color:#7a5200;border-radius:14px;padding:14px 16px;margin-bottom:14px;font-size:14px}
        .err{color:#c0392b;font-size:13px;margin-top:6px}
        .lnk{display:block;text-align:center;color:var(--mut);font-size:14px;margin-top:10px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>{{ $merchant ?: 'TAGTOA' }}</h1>
        <div class="ref">{{ __('Commande') }} <b>{{ $order->reference }}</b></div>
        <div class="tot">{{ Money::format($order->total, $order->currency) }}</div>
    </div>

    @if($order->isPaid())
        <div class="ok"><i class="fa-solid fa-circle-check"></i> {{ __('Cette commande est payée. Merci !') }}</div>
    @else
        @if($claimed)
            <div class="ok"><i class="fa-solid fa-hourglass-half"></i> {{ __('Merci ! Le commerce vérifie votre paiement et confirmera votre commande.') }}</div>
        @endif

        @if($nothing)
            <div class="info"><i class="fa-solid fa-circle-info"></i> {{ __('Aucun moyen de paiement en ligne n\'est disponible. Payez directement auprès du commerce.') }}</div>
        @endif

        @if(! empty($online))
            <h2>{{ __('Payer en ligne') }}</h2>
            @foreach($online as $o)
                <a class="btn" style="background:{{ $o['color'] }}" href="{{ route('tagtoa.pay.online.start', ['gateway' => $o['driver'], 'type' => $type, 'orderId' => $order->id]) }}">
                    <i class="{{ $o['icon'] }}"></i> {{ __('Payer avec') }} {{ __($o['label']) }}
                </a>
            @endforeach
        @endif

        @if($methods->isNotEmpty())
            <h2>{{ __('Payer par un autre moyen') }}</h2>
            @foreach($methods as $m)
                <div class="card">
                    <div class="mh"><i class="{{ $m->icon }}" style="color:{{ $m->brand_color }}"></i> {{ $m->display_label }}</div>
                    @if($m->qr_url)<div class="qr"><img src="{{ $m->qr_url }}" alt=""></div>@endif
                    @if($m->account_holder)<div class="kv"><span>{{ __('Titulaire') }}</span><b>{{ $m->account_holder }}</b></div>@endif
                    @if($m->institution)<div class="kv"><span>{{ __('Institution') }}</span><b>{{ $m->institution }}</b></div>@endif
                    @if($m->account_number)<div class="kv"><span>{{ __('Numéro / compte') }}</span><b>{{ $m->account_number }}</b></div>@endif
                    <div class="kv"><span>{{ __('Montant') }}</span><b>{{ Money::format($order->total, $order->currency) }}</b></div>
                    @if($m->instructions)<div class="instr">{{ $m->instructions }}</div>@endif
                </div>
            @endforeach

            @unless($claimed)
                <div class="card">
                    <div class="mh"><i class="fa-solid fa-hand-holding-dollar" style="color:var(--acc)"></i> {{ __('J\'ai payé') }}</div>
                    @if($errors->any())
                        <div class="info" role="alert" style="margin-top:8px;background:#fdecea;color:#8a1f11">
                            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                        </div>
                    @endif
                    <form id="claim" method="POST" action="{{ route('tagtoa.pay.order.claim', [$type, $order->reference]) }}">
                        @csrf
                        <label class="lbl" for="pm">{{ __('Moyen utilisé') }}</label>
                        <select class="inp" id="pm" name="payment_method_id" required>
                            @foreach($methods as $m)<option value="{{ $m->id }}" @selected(old('payment_method_id') == $m->id)>{{ $m->display_label }}</option>@endforeach
                        </select>
                        <label class="lbl" for="pn">{{ __('Votre nom') }}</label>
                        <input class="inp" id="pn" name="payer_name" maxlength="120" value="{{ old('payer_name') }}">
                        <label class="lbl" for="pp">{{ __('Votre téléphone') }}</label>
                        <input class="inp" id="pp" name="payer_phone" type="tel" maxlength="20" value="{{ old('payer_phone') }}">
                        <label class="lbl" for="pr">{{ __('Référence du paiement (optionnel)') }}</label>
                        <input class="inp" id="pr" name="reference" maxlength="120" value="{{ old('reference') }}">
                        <button class="btn" style="background:var(--acc);margin:16px 0 0"><i class="fa-solid fa-check"></i> {{ __('Envoyer au commerce') }}</button>
                    </form>
                </div>
            @endunless
        @endif
    @endif

    @if($trackUrl)<a class="lnk" href="{{ $trackUrl }}"><i class="fa-solid fa-location-crosshairs"></i> {{ __('Suivre ma commande') }}</a>@endif
    @if($backUrl)<a class="lnk" href="{{ $backUrl }}"><i class="fa-solid fa-utensils"></i> {{ __('Continuer à voir le catalogue') }}</a>@endif
</div>
</body>
</html>

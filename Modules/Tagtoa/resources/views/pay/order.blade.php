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
        /* PAY-2 — grille de moyens : une carte = un moyen, un seul choix (radio natif, accessible au clavier) */
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
        .opt{position:relative;display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;padding:14px 10px;background:var(--sf);border:2px solid var(--bd);border-radius:16px;cursor:pointer;min-height:112px;transition:border-color .15s,box-shadow .15s}
        .opt:hover{border-color:#bbb}
        .opt input{position:absolute;opacity:0;inset:0;width:100%;height:100%;margin:0;cursor:pointer}
        .opt:has(input:checked){border-color:var(--acc);box-shadow:0 0 0 3px rgba(44,184,9,.18)}
        .opt:has(input:focus-visible){outline:3px solid #1a5fd0;outline-offset:2px}
        .opt .lg{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;overflow:hidden;flex:0 0 46px}
        .opt .lg img{width:100%;height:100%;object-fit:contain;background:#fff}
        .opt .nm{font:700 14px var(--fh);line-height:1.2;word-break:break-word}
        .opt .sub{font-size:11.5px;color:#555}
        .opt .tick{position:absolute;top:8px;right:8px;width:22px;height:22px;border-radius:50%;border:2px solid #bbb;background:#fff;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px}
        .opt:has(input:checked) .tick{background:var(--acc);border-color:var(--acc)}
        .panel{margin-top:14px}
        .panel[hidden]{display:none}
        .go{background:var(--acc)}
    </style>
<noscript><style>.panel[hidden]{display:block}.opt{display:none}</style></noscript>
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

        @if($errors->any())
            <div class="info" role="alert" style="background:#fdecea;color:#8a1f11">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        @if(! empty($choices))
            @php
                $pre = old('payment_method_id') ? 'manual-'.old('payment_method_id') : (count($choices) === 1 ? $choices[0]['key'] : null);
            @endphp
            <h2 id="choose">{{ __('Choisissez un moyen de paiement') }}</h2>
            <div class="grid" role="radiogroup" aria-labelledby="choose">
                @foreach($choices as $c)
                    <label class="opt">
                        <input type="radio" name="choice" value="{{ $c['key'] }}" data-panel="p-{{ $c['key'] }}" @checked($pre === $c['key'])>
                        <span class="lg" style="background:{{ $c['color'] }}">
                            @if($c['logo_url'])<img src="{{ $c['logo_url'] }}" alt="">@else<i class="{{ $c['icon'] }}" aria-hidden="true"></i>@endif
                        </span>
                        <span class="nm">{{ $c['label'] }}</span>
                        <span class="sub">{{ __($c['badge']) }}</span>
                        <span class="tick" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                    </label>
                @endforeach
            </div>

            @foreach($choices as $c)
                <div class="panel" id="p-{{ $c['key'] }}" @if($pre !== $c['key']) hidden @endif>
                    @if($c['kind'] === 'online')
                        <a class="btn go" href="{{ $c['url'] }}"><i class="{{ $c['icon'] }}"></i> {{ __('Payer avec') }} {{ __($c['label']) }}</a>
                    @else
                        @php $m = $c['method']; @endphp
                        <div class="card">
                            <div class="mh"><i class="{{ $m->icon }}" style="color:{{ $c['color'] }}"></i> {{ $m->display_label }}</div>
                            @if($m->qr_url)<div class="qr"><img src="{{ $m->qr_url }}" alt="{{ __('QR de paiement') }}"></div>@endif
                            @if($m->account_holder)<div class="kv"><span>{{ __('Titulaire') }}</span><b>{{ $m->account_holder }}</b></div>@endif
                            @if($m->institution)<div class="kv"><span>{{ __('Institution') }}</span><b>{{ $m->institution }}</b></div>@endif
                            @if($m->account_number)<div class="kv"><span>{{ __('Numéro / compte') }}</span><b>{{ $m->account_number }}</b></div>@endif
                            <div class="kv"><span>{{ __('Montant') }}</span><b>{{ Money::format($order->total, $order->currency) }}</b></div>
                            @if($m->instructions)<div class="instr">{{ $m->instructions }}</div>@endif
                        </div>
                        @unless($claimed)
                            <div class="card">
                                <div class="mh"><i class="fa-solid fa-hand-holding-dollar" style="color:var(--acc)"></i> {{ __('J\'ai payé') }}</div>
                                <form id="claim-{{ $m->id }}" method="POST" action="{{ route('tagtoa.pay.order.claim', [$type, $order->reference]) }}">
                                    @csrf
                                    <input type="hidden" name="payment_method_id" value="{{ $m->id }}">
                                    <label class="lbl" for="pn-{{ $m->id }}">{{ __('Votre nom') }}</label>
                                    <input class="inp" id="pn-{{ $m->id }}" name="payer_name" maxlength="120" value="{{ old('payment_method_id') == $m->id ? old('payer_name') : '' }}" autocomplete="name">
                                    <label class="lbl" for="pp-{{ $m->id }}">{{ __('Votre téléphone') }}</label>
                                    <input class="inp" id="pp-{{ $m->id }}" name="payer_phone" type="tel" maxlength="24" value="{{ old('payment_method_id') == $m->id ? old('payer_phone') : '' }}" autocomplete="tel">
                                    <label class="lbl" for="pr-{{ $m->id }}">{{ __('Référence du paiement (optionnel)') }}</label>
                                    <input class="inp" id="pr-{{ $m->id }}" name="reference" maxlength="120" value="{{ old('payment_method_id') == $m->id ? old('reference') : '' }}">
                                    <button class="btn" style="background:var(--acc);margin:16px 0 0"><i class="fa-solid fa-check"></i> {{ __('Envoyer au commerce') }}</button>
                                </form>
                            </div>
                        @endunless
                    @endif
                </div>
            @endforeach

            <script>
                (function(){
                    var radios = document.querySelectorAll('.opt input[type=radio]');
                    function show(){
                        document.querySelectorAll('.panel').forEach(function(p){ p.hidden = true; });
                        radios.forEach(function(r){ if (r.checked) { var p = document.getElementById(r.dataset.panel); if (p) { p.hidden = false; } } });
                    }
                    radios.forEach(function(r){ r.addEventListener('change', show); });
                })();
            </script>
        @endif
    @endif

    @if($trackUrl)<a class="lnk" href="{{ $trackUrl }}"><i class="fa-solid fa-location-crosshairs"></i> {{ __('Suivre ma commande') }}</a>@endif
    @if($backUrl)<a class="lnk" href="{{ $backUrl }}"><i class="fa-solid fa-utensils"></i> {{ __('Continuer à voir le catalogue') }}</a>@endif
</div>
</body>
</html>

{{-- TAGTOA MENU — suivi de commande client (public, sans auth, temps réel). --}}
@php
    $dark = ($menu->theme ?? 'light') === 'dark';
    $accent = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) ($menu->accent_color ?? '')) ? $menu->accent_color : '#2cb809';
    $bg   = $dark ? '#0A0A0A' : '#F5F5F3';
    $fg   = $dark ? '#FFFFFF' : '#0A0A0A';
    $surf = $dark ? '#161616' : '#FFFFFF';
    $mut  = $dark ? 'rgba(255,255,255,.6)' : '#888888';
    $bd   = $dark ? 'rgba(255,255,255,.10)' : 'rgba(0,0,0,.08)';
    $steps = ['pending', 'confirmed', 'preparing', 'ready', 'completed'];
    $stepIdx = array_search($order->status, $steps, true);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('Suivi de commande') }} {{ $order->reference }} — TAGTOA</title>
    <link rel="stylesheet" href="{{ route('tagtoa.asset', 'tagtoa-fonts.css') }}">
    <link rel="stylesheet" href="/tagtoa-asset/fontawesome-6.5.1.css">
    <style>
        :root{--acc:{{ $accent }};--bg:{{ $bg }};--fg:{{ $fg }};--surf:{{ $surf }};--mut:{{ $mut }};--bd:{{ $bd }};--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--fg);min-height:100vh}
        .wrap{max-width:520px;margin:0 auto;padding:24px 18px 60px}
        .card{background:var(--surf);border:1px solid var(--bd);border-radius:16px;padding:18px;margin-bottom:14px}
        h1{font:700 20px var(--fh)}
        .ref{color:var(--mut);font-size:14px;margin-top:4px}
        .cancelled{background:#E0473E;color:#fff;border-radius:12px;padding:12px 14px;font-weight:700;text-align:center;margin-bottom:14px}
        .steps{display:flex;justify-content:space-between;margin:18px 0 6px;position:relative}
        .steps::before{content:"";position:absolute;top:15px;left:15px;right:15px;height:3px;background:var(--bd);z-index:0}
        .step{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:6px;flex:1}
        .dot{width:32px;height:32px;border-radius:50%;background:var(--bg);border:3px solid var(--bd);display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--mut);transition:.2s}
        .step.on .dot{background:var(--acc);border-color:var(--acc);color:#fff}
        .step.done .dot{background:var(--acc);border-color:var(--acc);color:#fff}
        .lbl{font-size:11px;color:var(--mut);text-align:center;max-width:70px}
        .step.on .lbl{color:var(--fg);font-weight:700}
        .row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bd);font-size:14px}
        .row:last-child{border-bottom:0}
        .tot{font:700 16px var(--fh)}
        .foot{text-align:center;color:var(--mut);font-size:12px;margin-top:18px}
        .foot b{font-family:var(--fh);color:var(--fg)}
        .badge{display:inline-block;background:color-mix(in srgb,var(--acc) 16%,transparent);color:var(--acc);border-radius:999px;padding:3px 10px;font-size:12px;font-weight:700;margin-top:6px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>{{ $menu->name ?? 'TAGTOA' }}</h1>
        <div class="ref">{{ __('Référence') }} : <b>{{ $order->reference }}</b></div>
        <div class="badge" id="typeBadge">{{ $order->order_type_label }}</div>
    </div>

    @if($order->status === 'cancelled')
        <div class="cancelled" id="cancelledBanner"><i class="fa-solid fa-circle-xmark"></i> {{ __('Commande annulée') }}</div>
    @else
        <div class="card">
            <div class="steps" id="steps">
                @foreach($steps as $i => $s)
                    <div class="step @if($i < $stepIdx) done @elseif($i === $stepIdx) on @endif" data-step="{{ $s }}">
                        <div class="dot"><i class="fa-solid fa-check"></i></div>
                        <div class="lbl">{{ __(\Modules\Tagtoa\App\Models\Menu\Order::STATUS_META[$s]['label']) }}</div>
                    </div>
                @endforeach
            </div>
            <div style="text-align:center;margin-top:10px;font-weight:700" id="statusLine">{{ __($order->status_meta['label']) }}</div>
        </div>
    @endif

    <div class="card">
        @foreach($order->items as $it)
            <div class="row"><span>{{ $it->qty }}x {{ $it->name }}</span><span>{{ \Modules\Tagtoa\App\Support\Money::format($it->line_total, $order->currency) }}</span></div>
        @endforeach
        @if($order->tip > 0)
            <div class="row"><span>{{ __('Pourboire') }}</span><span>{{ \Modules\Tagtoa\App\Support\Money::format($order->tip, $order->currency) }}</span></div>
        @endif
        <div class="row tot"><span>{{ __('Total') }}</span><span>{{ \Modules\Tagtoa\App\Support\Money::format($order->total, $order->currency) }}</span></div>
        @if($order->table_label)<div class="row"><span>{{ __('Table') }}</span><span>{{ $order->table_label }}</span></div>@endif
        @if($order->delivery_address)<div class="row"><span>{{ __('Adresse') }}</span><span>{{ $order->delivery_address }}</span></div>@endif
    </div>

    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
</div>

@if($order->status !== 'cancelled' && $order->status !== 'completed')
<script>
    var STATUS_URL = @json(route('tagtoa.menu.track.status', $order->reference));
    var STEPS = @json($steps);
    function applyStatus(status, label){
        if (status === 'cancelled'){
            document.getElementById('steps')?.closest('.card')?.remove();
            var b = document.createElement('div'); b.className='cancelled'; b.innerHTML='<i class="fa-solid fa-circle-xmark"></i> '+@json(__('Commande annulée'));
            document.querySelector('.wrap').insertBefore(b, document.querySelector('.wrap').children[1]);
            clearInterval(timer);
            return;
        }
        var idx = STEPS.indexOf(status);
        document.querySelectorAll('#steps .step').forEach(function(el, i){
            el.classList.toggle('done', i < idx);
            el.classList.toggle('on', i === idx);
        });
        var line = document.getElementById('statusLine');
        if (line) line.textContent = label;
        if (status === 'completed') clearInterval(timer);
    }
    function poll(){
        fetch(STATUS_URL, {headers:{'Accept':'application/json'}})
            .then(function(r){ return r.json(); })
            .then(function(j){ applyStatus(j.status, j.status_label); })
            .catch(function(){});
    }
    var timer = setInterval(poll, 6000);
</script>
@endif
</body>
</html>

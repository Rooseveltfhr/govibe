{{-- TAGTOA Event — confirmation commande. Variables : $order, $event --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Commande') }} {{ $order->reference }} — TAGTOA</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blk:#0A0A0A;--blue:#16A34A;--green:#1D9E75;--bg:#F5F5F3;--bd:rgba(0,0,0,.08);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}body{font-family:var(--fb);background:var(--bg);color:var(--blk)}
        .wrap{max-width:480px;margin:0 auto;min-height:100vh;padding:24px 18px}
        .ok{text-align:center;padding:18px}.ok i{font-size:46px;color:var(--green)}.ok h1{font:700 22px var(--fh);margin-top:10px}.ok p{color:#666;font-size:14px;margin-top:4px}
        .pay{background:var(--blue);color:#fff;display:flex;align-items:center;gap:10px;justify-content:center;text-decoration:none;border-radius:14px;padding:15px;font:600 15px var(--fh);margin:18px 0}
        .summary{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:18px}.kv{display:flex;justify-content:space-between;padding:7px 0;font-size:14px}.kv span{color:#888}.kv b{font-family:var(--fh)}
        .sec{font:600 13px var(--fh);letter-spacing:.05em;text-transform:uppercase;color:#777;margin:8px 0 12px}
        .tkt{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--bd);border-radius:14px;padding:14px;margin-bottom:10px;text-decoration:none;color:inherit}
        .tkt .ic{width:44px;height:44px;border-radius:11px;background:var(--blk);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px}
        .tkt .t{flex:1}.tkt .t b{font-family:var(--fh);display:block}.tkt .t span{font-size:12px;color:#888}
        .foot{text-align:center;padding:20px;color:#aaa;font-size:12px}.foot b{font-family:var(--fh);color:#777}
    </style>
</head>
<body>
<div class="wrap">
    <div class="ok"><i class="fa-solid fa-circle-check"></i><h1>{{ $order->isPaid() ? __('Billets confirmés!') : __('Commande créée') }}</h1><p>{{ __('Référence') }} : <b>{{ $order->reference }}</b></p></div>
    @if(! $order->isPaid() && $event->payPage)
        <a class="pay" href="{{ url('/pay/'.$event->payPage->alias) }}"><i class="fa-solid fa-credit-card"></i> {{ __('Payer') }} {{ number_format($order->total,2) }} {{ $order->currency }}</a>
    @endif
    <div class="summary">
        <div class="kv"><span>{{ __('Acheteur') }}</span><b>{{ $order->buyer_name }}</b></div>
        <div class="kv"><span>{{ __('Total') }}</span><b>{{ number_format($order->total,2) }} {{ $order->currency }}</b></div>
        <div class="kv"><span>{{ __('Statut') }}</span><b>{{ $order->isPaid() ? __('Payé') : __('En attente') }}</b></div>
    </div>
    <p class="sec">{{ __('Vos billets') }} ({{ $order->tickets->count() }})</p>
    @foreach($order->tickets as $t)
        <a class="tkt" href="{{ route('tagtoa.event.ticket', $t->code) }}"><span class="ic"><i class="fa-solid fa-ticket"></i></span><span class="t"><b>{{ optional($t->ticketType)->name }}</b><span>{{ $t->code }}</span></span><i class="fa-solid fa-qrcode"></i></a>
    @endforeach
    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b></div>
</div>
</body>
</html>

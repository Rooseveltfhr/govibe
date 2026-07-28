<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Recharger ma carte') }} · TAGTOA</title>
    <link rel="stylesheet" href="/tagtoa-asset/fontawesome-6.5.1.css">
    <style>
        body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f5f7f3;color:#0e140c;min-height:100vh;display:grid;place-items:center;padding:24px}
        .box{background:#fff;border:1px solid rgba(14,20,12,.1);border-radius:20px;padding:30px 26px;max-width:420px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,.06)}
        .badge{display:inline-flex;align-items:center;gap:6px;background:rgba(44,184,9,.1);color:#2cb809;font-weight:700;font-size:12px;padding:6px 12px;border-radius:999px;margin-bottom:14px}
        h1{font-size:21px;margin:0 0 6px}
        .sub{color:#6b7865;font-size:14px;margin:0 0 20px}
        label{display:block;font-size:13px;font-weight:600;margin:14px 0 6px}
        .inp{width:100%;box-sizing:border-box;border:1px solid rgba(14,20,12,.16);border-radius:12px;padding:13px 14px;font-size:16px;background:#fbfcfa}
        .card-info{background:#f4f6f2;border-radius:12px;padding:12px 14px;margin-bottom:6px;font-size:14px}
        .gw{display:flex;align-items:center;gap:10px;border:1px solid rgba(14,20,12,.14);border-radius:12px;padding:12px 14px;margin-top:8px;cursor:pointer}
        .gw input{accent-color:#2cb809}
        .btn{width:100%;margin-top:18px;background:#2cb809;color:#fff;border:0;border-radius:12px;padding:15px;font-size:16px;font-weight:700;cursor:pointer}
        .err{background:#fde8e8;color:#a01919;border-radius:12px;padding:12px 14px;font-size:14px;margin-bottom:16px}
        .none{background:#fff7e8;color:#8a5a00;border-radius:12px;padding:14px;font-size:14px;text-align:center}
        a.home{display:block;text-align:center;margin-top:16px;color:#6b7865;font-size:13px}
    </style>
</head>
<body>
    <div class="box">
        <span class="badge"><i class="fa-solid fa-credit-card"></i> TAGTOA CARD</span>
        <h1>{{ __('Recharger ma carte') }}</h1>
        <p class="sub">{{ __('Rechargez le solde de votre carte TAGTOA en ligne. Utilisable partout sur TAGTOA.') }}</p>

        @if(session('error'))<div class="err"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>@endif

        @if($card)
            <div class="card-info">
                <b>{{ $card->code }}</b> — {{ __('Solde actuel') }} : <b>{{ $card->balance_label }}</b>
            </div>
        @endif

        <form method="POST" action="{{ route('tagtoa.card.recharge.start') }}">
            @csrf
            <label>{{ __('Code de la carte') }} *</label>
            <input class="inp" name="code" value="{{ old('code', $code) }}" placeholder="TAG..." style="text-transform:uppercase" required @if($card) readonly @endif>

            <label>{{ __('Montant à recharger') }} ({{ $card?->currency ?: 'HTG' }}) *</label>
            <input class="inp" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" placeholder="0.00" required>

            @if(count($gateways) > 0)
                <label>{{ __('Moyen de recharge') }} *</label>
                @foreach($gateways as $driver => $lbl)
                    <label class="gw"><input type="radio" name="gateway" value="{{ $driver }}" @checked($loop->first)> {{ $lbl }}</label>
                @endforeach
                <button class="btn" type="submit"><i class="fa-solid fa-bolt"></i> {{ __('Recharger maintenant') }}</button>
            @else
                <div class="none" style="margin-top:16px"><i class="fa-solid fa-circle-info"></i> {{ __('Aucun moyen de recharge en ligne n\'est disponible pour le moment. Rechargez auprès d\'un point de vente TAGTOA.') }}</div>
            @endif
        </form>

        <a class="home" href="{{ url('/') }}">{{ __('Retour à l\'accueil') }}</a>
    </div>
</body>
</html>

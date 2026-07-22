<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Paiement') }} · TAGTOA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body{margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f5f7f3;color:#0e140c;
            min-height:100vh;display:grid;place-items:center;padding:24px}
        .box{background:#fff;border:1px solid rgba(14,20,12,.1);border-radius:20px;padding:38px 30px;max-width:400px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.06)}
        .ic{font-size:52px;margin-bottom:14px}
        .ic.ok{color:#2cb809}.ic.wait{color:#E08A1E}.ic.no{color:#8a8a8a}
        h1{font-size:22px;margin:0 0 8px}
        p{color:#6b7865;font-size:15px;margin:0 0 22px}
        a{display:inline-block;background:#2cb809;color:#fff;text-decoration:none;padding:13px 22px;border-radius:12px;font-weight:700}
    </style>
</head>
<body>
    <div class="box">
        @if($status === 'paid')
            <div class="ic ok"><i class="fa-solid fa-circle-check"></i></div>
            <h1>{{ __('Paiement confirmé !') }}</h1>
            <p>{{ __('Merci. Votre commande est réglée.') }}</p>
        @elseif($status === 'unavailable')
            <div class="ic no"><i class="fa-solid fa-clock"></i></div>
            <h1>{{ __('Paiement en ligne bientôt disponible') }}</h1>
            <p>{{ __('Utilisez le paiement manuel pour le moment.') }}</p>
        @else
            <div class="ic wait"><i class="fa-solid fa-hourglass-half"></i></div>
            <h1>{{ __('Paiement en cours de vérification') }}</h1>
            <p>{{ __('Si vous avez payé, votre commande sera confirmée sous peu.') }}</p>
        @endif
        <a href="{{ url('/') }}">{{ __('Retour à l\'accueil') }}</a>
    </div>
</body>
</html>

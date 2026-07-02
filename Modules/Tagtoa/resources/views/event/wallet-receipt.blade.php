{{-- TAGTOA EVENT — reçu public d'une transaction wallet. Variables: $txn, $event, $vendor --}}
@php use Modules\Tagtoa\App\Support\Money; use Modules\Tagtoa\App\Services\Audit\AuditService; @endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Reçu') }} — {{ $txn->reference }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--green:#2cb809;--ink:#111;--muted:#666;--bd:rgba(0,0,0,.12);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        h1,.amt{font-family:'Anton',sans-serif!important;font-weight:400!important;letter-spacing:.01em}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:#f4f4f5;color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:18px}
        .r{background:#fff;border:1px solid var(--bd);border-radius:18px;max-width:400px;width:100%;padding:26px;box-shadow:0 14px 40px rgba(0,0,0,.08)}
        .ic{width:64px;height:64px;border-radius:50%;background:var(--green);color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 12px}
        h1{font:700 20px var(--fh);text-align:center}
        .amt{font:700 34px var(--fh);color:var(--green);text-align:center;margin:6px 0 2px}
        .sub{text-align:center;color:var(--muted);font-size:13px;margin-bottom:18px}
        .row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--bd);font-size:14px}
        .row .k{color:var(--muted)}.row .v{font-weight:600;font-family:var(--fh)}
        .foot{text-align:center;color:var(--muted);font-size:12px;margin-top:16px}.foot b{color:var(--ink);font-family:var(--fh)}
    </style>
</head>
<body>
    <div class="r">
        <div class="ic"><i class="fa-solid fa-circle-check"></i></div>
        <h1>{{ __('Reçu') }}</h1>
        <div class="amt">{{ Money::formatMinor((int) $txn->amount_minor, $txn->currency) }}</div>
        <div class="sub">{{ __(AuditService::actionLabel('wallet.'.$txn->type)) }}</div>

        @if($event)<div class="row"><span class="k">{{ __('Événement') }}</span><span class="v">{{ $event->title }}</span></div>@endif
        @if($txn->type === 'purchase' && $vendor)<div class="row"><span class="k">{{ __('Stand') }}</span><span class="v">{{ $vendor->owner_label }}</span></div>@endif
        <div class="row"><span class="k">{{ __('Référence') }}</span><span class="v">{{ $txn->reference }}</span></div>
        <div class="row"><span class="k">{{ __('Date') }}</span><span class="v">{{ optional($txn->created_at)->format('d/m/Y H:i') }}</span></div>

        <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
    </div>
</body>
</html>

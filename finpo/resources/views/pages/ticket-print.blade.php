<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ $registration->number }} — FINPO 2026</title>
<style>
    body { font-family: "Segoe UI", Arial, sans-serif; margin: 0; padding: 24px; color: #0b1220; }
    .ticket { max-width: 680px; margin: 0 auto; border: 2px solid #0b1220; border-radius: 14px; overflow: hidden; }
    .head { background: #0b1220; color: #fff; padding: 18px 24px; display: flex; justify-content: space-between; }
    .head .brand b { color: #e8b931; }
    .body { display: flex; gap: 24px; padding: 24px; }
    .info { flex: 1; }
    .qr { text-align: center; }
    .qr img { width: 170px; height: 170px; }
    .label { font-size: 11px; text-transform: uppercase; letter-spacing: .1em; color: #667; margin-top: 12px; }
    .value { font-size: 15px; font-weight: 600; }
    .foot { border-top: 1px dashed #99a; padding: 12px 24px; font-size: 12px; color: #556; display: flex; justify-content: space-between; }
    @media print { body { padding: 0; } .no-print { display: none; } }
</style>
</head>
<body onload="window.print()">
<div class="ticket">
    <div class="head">
        <div class="brand"><b>FINPO</b> 2026<br><small>{{ config('finpo.subtitle') }}</small></div>
        <div style="text-align:right;"><b>{{ $registration->number }}</b><br><small>{{ $registration->category?->name }}</small></div>
    </div>
    <div class="body">
        <div class="info">
            <div class="label">{{ __('Participant') }}</div>
            <div class="value" style="font-size: 20px;">{{ $registration->fullName() }}</div>
            <div class="label">{{ __('Institution / Fonction') }}</div>
            <div class="value">{{ $registration->institution ?: '—' }} · {{ $registration->position ?: '—' }}</div>
            <div class="label">{{ __('Catégorie') }}</div>
            <div class="value">{{ $registration->audienceLabel() }} — {{ $registration->country }}</div>
            <div class="label">{{ __('Dates & lieu') }}</div>
            <div class="value">{{ \Illuminate\Support\Carbon::parse(config('finpo.starts_at'))->translatedFormat('d') }}–{{ \Illuminate\Support\Carbon::parse(config('finpo.ends_at'))->translatedFormat('d F Y') }}<br>{{ config('finpo.venue.name') }}, {{ config('finpo.venue.city') }}</div>
        </div>
        <div class="qr">
            <img src="{{ $qr }}" alt="QR">
            <div style="font-size: 11px; color: #667;">{{ __('À présenter à l\'entrée') }}</div>
        </div>
    </div>
    <div class="foot">
        <span>{{ $registration->isPaid() ? __('PAYÉ / CONFIRMÉ') : __('PAIEMENT EN ATTENTE — à régulariser à l\'accueil') }}</span>
        <span>finpo.ht · {{ config('finpo.contact.email') }}</span>
    </div>
</div>
</body>
</html>

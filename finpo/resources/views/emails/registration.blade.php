<!DOCTYPE html>
<html lang="fr">
<body style="margin:0; padding:24px; background:#f2f4f8; font-family: Arial, sans-serif; color:#17233b;">
<div style="max-width:600px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e2e6ee;">
    <div style="background:#0b1220; color:#fff; padding:22px 28px;">
        <div style="font-size:22px; font-weight:800;">FIN<span style="color:#e8b931;">PO</span> 2026</div>
        <div style="font-size:12px; opacity:.8;">{{ config('finpo.subtitle') }}</div>
    </div>
    <div style="padding:28px;">
        <h1 style="font-size:19px; margin:0 0 12px;">{{ __('Inscription confirmée !') }} 🎉</h1>
        <p>{{ __('Bonjour') }} <strong>{{ $registration->fullName() }}</strong>,</p>
        <p>{{ __('Votre inscription à FINPO 2026 est confirmée. Voici votre billet :') }}</p>
        <table style="width:100%; border-collapse:collapse; margin:16px 0;">
            <tr><td style="padding:6px 0; color:#667;">{{ __('Numéro de billet') }}</td><td style="text-align:right; font-weight:700;">{{ $registration->number }}</td></tr>
            <tr><td style="padding:6px 0; color:#667;">{{ __('Catégorie') }}</td><td style="text-align:right; font-weight:700;">{{ $registration->category?->name }}</td></tr>
            <tr><td style="padding:6px 0; color:#667;">{{ __('Montant') }}</td><td style="text-align:right; font-weight:700;">@if($registration->amount == 0){{ __('Gratuit') }}@else{{ number_format($registration->amount, 0, ',', ' ') }} {{ $registration->currency }}@endif</td></tr>
            <tr><td style="padding:6px 0; color:#667;">{{ __('Statut') }}</td><td style="text-align:right; font-weight:700;">{{ $registration->isPaid() ? __('Confirmé') : __('Paiement en attente') }}</td></tr>
        </table>
        <p style="text-align:center; margin:26px 0;">
            <a href="{{ route('ticket.show', $registration->qr_token) }}"
               style="background:#e8b931; color:#101a2e; text-decoration:none; font-weight:700; padding:13px 26px; border-radius:100px; display:inline-block;">
                {{ __('Voir mon billet & QR code') }}
            </a>
        </p>
        <p style="font-size:13px; color:#667;">
            📅 {{ \Illuminate\Support\Carbon::parse(config('finpo.starts_at'))->translatedFormat('d') }}–{{ \Illuminate\Support\Carbon::parse(config('finpo.ends_at'))->translatedFormat('d F Y') }}<br>
            📍 {{ config('finpo.venue.name') }}, {{ config('finpo.venue.city') }}
        </p>
    </div>
    <div style="background:#f6f8fc; padding:16px 28px; font-size:12px; color:#889;">
        {{ config('finpo.organizer.name') }} · {{ config('finpo.contact.email') }} · {{ config('finpo.contact.phone') }}
    </div>
</div>
</body>
</html>

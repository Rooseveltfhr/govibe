@extends('layouts.app', ['title' => __('Badge participant')])

@section('content')
<section class="fp-section" style="padding-top: 8.5rem;">
    <div class="container text-center">
        <div class="fp-badge-card">
            <div style="background: #0b1220; color: #fff; padding: 16px;">
                <strong style="font-size: 1.3rem;">FIN<span style="color:#e8b931;">PO</span> 2026</strong>
                <div style="font-size: .72rem; opacity: .8;">{{ config('finpo.subtitle') }}</div>
            </div>
            <div class="p-4">
                <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center"
                     style="width: 96px; height: 96px; background: {{ $registration->badgeColor() }}20; border: 3px solid {{ $registration->badgeColor() }}; font-size: 2rem; font-weight: 800; color: {{ $registration->badgeColor() }};">
                    {{ mb_strtoupper(mb_substr($registration->first_name, 0, 1).mb_substr($registration->last_name, 0, 1)) }}
                </div>
                <h1 class="h4 mt-3 mb-0">{{ $registration->fullName() }}</h1>
                <p class="text-secondary small mb-1">{{ $registration->position ?: '' }}</p>
                <p class="fw-semibold mb-2">{{ $registration->institution ?: '—' }}</p>
                <p class="small text-secondary mb-3">{{ $registration->country }}</p>
                <img src="{{ $qr }}" alt="{{ __('QR code du badge') }}" style="width: 150px; height: 150px;">
                <div class="small text-secondary mt-1">{{ $registration->number }}</div>
                @if ($registration->emergency_contact)
                    <div class="small text-secondary mt-2" style="font-size: .68rem;">{{ __('Urgence') }} : {{ $registration->emergency_contact }}</div>
                @endif
            </div>
            <div style="background: {{ $registration->badgeColor() }}; color: #fff; padding: 10px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;">
                {{ $registration->audienceLabel() }}
            </div>
        </div>
        <div class="mt-4 fp-no-print">
            <button onclick="window.print()" class="btn btn-fp-primary">🖨 {{ __('Imprimer le badge') }}</button>
            <a href="{{ route('ticket.show', $registration->qr_token) }}" class="btn btn-fp-outline">← {{ __('Retour au billet') }}</a>
        </div>
    </div>
</section>
@endsection

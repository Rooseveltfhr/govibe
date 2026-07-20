@extends('layouts.app', ['title' => __('Votre billet')])

@section('content')
<section class="fp-section" style="padding-top: 8.5rem;">
    <div class="container">
        <div class="fp-eticket reveal is-visible">
            <div class="fp-eticket-head d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong class="fs-4">FINPO 2026</strong>
                    <div class="small">{{ config('finpo.subtitle') }}</div>
                </div>
                <div class="text-end">
                    <div class="fw-bold">{{ $registration->number }}</div>
                    <div class="small">{{ $registration->category?->name }}</div>
                </div>
            </div>
            <div class="p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-md-7">
                        <h1 class="h3 mb-1">{{ $registration->fullName() }}</h1>
                        <p class="fp-muted mb-3">
                            {{ $registration->position }}@if($registration->institution) · {{ $registration->institution }}@endif<br>
                            {{ $registration->audienceLabel() }} · {{ $registration->country }}
                        </p>
                        <div class="d-grid gap-2 small fp-muted">
                            <span>📅 {{ \Illuminate\Support\Carbon::parse(config('finpo.starts_at'))->translatedFormat('d') }}–{{ \Illuminate\Support\Carbon::parse(config('finpo.ends_at'))->translatedFormat('d F Y') }}</span>
                            <span>📍 {{ config('finpo.venue.name') }}, {{ config('finpo.venue.city') }}</span>
                            <span>
                                💳 {{ config('finpo.payment_methods.'.$registration->payment_method, $registration->payment_method) }} —
                                @if ($registration->isPaid())
                                    <span class="badge text-bg-success">{{ __('Payé / Confirmé') }}</span>
                                @else
                                    <span class="badge text-bg-warning text-dark">{{ __('Paiement en attente') }}</span>
                                    {{ number_format($registration->amount, 0, ',', ' ') }} {{ $registration->currency }}
                                @endif
                            </span>
                        </div>
                        @unless ($registration->isPaid())
                            <div class="alert alert-warning small mt-3 mb-0">
                                {{ __('Finalisez votre paiement pour garantir votre place :') }}
                                <strong>{{ config('finpo.payment_methods.'.$registration->payment_method, $registration->payment_method) }}</strong> —
                                {{ __('nos équipes vous contactent avec les instructions, ou payez sur place à l\'accueil.') }}
                            </div>
                        @endunless
                    </div>
                    <div class="col-md-5 text-center">
                        <div class="bg-white rounded-4 p-3 d-inline-block">
                            <img src="{{ $qr }}" alt="{{ __('QR code du billet') }}" class="img-fluid" style="max-width: 200px;">
                        </div>
                        <p class="small fp-muted mt-2 mb-0">{{ __('Présentez ce QR code à l\'entrée') }}</p>
                    </div>
                </div>
            </div>
            <div class="px-4 pb-4 d-flex flex-wrap gap-2 fp-no-print">
                <a class="btn btn-fp-primary" href="{{ route('ticket.print', $registration->qr_token) }}" target="_blank">🖨 {{ __('Imprimer / PDF') }}</a>
                <a class="btn btn-fp-outline" href="{{ route('badge.show', $registration->qr_token) }}">🪪 {{ __('Voir mon badge') }}</a>
                <a class="btn btn-fp-outline" href="{{ route('ticket.ics', $registration->qr_token) }}">📅 {{ __('Ajouter au calendrier') }}</a>
            </div>
        </div>
        <p class="text-center fp-muted small mt-4 fp-no-print">
            {{ __('Un email de confirmation a été envoyé à') }} <strong>{{ $registration->email }}</strong>.
            {{ __('Conservez ce lien : il constitue votre billet officiel.') }}
        </p>
    </div>
</section>
@endsection

@extends('layouts.app', ['title' => __('Certificat de participation')])

@section('content')
<section class="fp-section" style="padding-top: 8.5rem;">
    <div class="container">
        <div class="mx-auto p-5 rounded-4 text-center position-relative" style="max-width: 820px; background: #fdfcf7; color: #17233b; border: 10px double #c9a227;">
            <div style="letter-spacing: .3em; text-transform: uppercase; font-size: .8rem; color: #8a7a2e;">{{ config('finpo.full_name') }}</div>
            <h1 class="mt-3" style="font-family: Georgia, serif; color: #17233b;">{{ __('Certificat de participation') }}</h1>
            <p class="mt-4 mb-1" style="color: #556;">{{ __('Ce certificat est décerné à') }}</p>
            <div class="fs-2 fw-bold" style="font-family: Georgia, serif; color: #0b1220;">{{ $certificate->registration->fullName() }}</div>
            <p style="color: #556;">{{ $certificate->registration->institution ?: $certificate->registration->audienceLabel() }}</p>
            <p class="mx-auto" style="max-width: 560px; color: #445;">
                {{ __('pour sa participation à FINPO 2026, tenu du :from au :to à :city.', [
                    'from' => \Illuminate\Support\Carbon::parse(config('finpo.starts_at'))->translatedFormat('d F'),
                    'to' => \Illuminate\Support\Carbon::parse(config('finpo.ends_at'))->translatedFormat('d F Y'),
                    'city' => config('finpo.venue.city'),
                ]) }}
            </p>
            <div class="d-flex justify-content-between align-items-end mt-5 px-3">
                <div class="text-start">
                    <img src="{{ $qr }}" alt="{{ __('QR de vérification') }}" style="width: 92px; height: 92px;">
                    <div style="font-size: .7rem; color: #778;">{{ $certificate->number }}</div>
                </div>
                <div class="text-center">
                    <div style="font-family: 'Brush Script MT', cursive; font-size: 1.8rem; color: #17233b;">R. Forestal</div>
                    <hr style="margin: 4px 0; border-color: #17233b;">
                    <div style="font-size: .78rem; color: #556;">{{ config('finpo.organizer.name') }}<br>{{ __('Comité organisateur') }}</div>
                </div>
                <div class="text-end" style="font-size: .72rem; color: #778;">
                    {{ __('Délivré le') }}<br>{{ $certificate->issued_at->translatedFormat('d F Y') }}
                </div>
            </div>
        </div>
        <div class="text-center mt-4 fp-no-print">
            <button onclick="window.print()" class="btn btn-fp-primary">🖨 {{ __('Imprimer / PDF') }}</button>
            <a href="{{ route('certificate.verify', $certificate->number) }}" class="btn btn-fp-outline">{{ __('Vérifier l\'authenticité') }}</a>
        </div>
    </div>
</section>
@endsection

@extends('layouts.app', ['title' => __('Vérification de certificat')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Vérification'),
    'heading' => __('Vérifier un certificat'),
    'lead' => __('Chaque certificat FINPO porte un numéro unique et un QR code vérifiables ici.'),
])

<section class="fp-section-tight">
    <div class="container" style="max-width: 640px;">
        <form method="get" action="{{ route('certificate.verify') }}" class="fp-card p-4 d-flex gap-2 mb-4 reveal">
            <label class="visually-hidden" for="v-number">{{ __('Numéro de certificat') }}</label>
            <input id="v-number" name="numero" class="form-control" placeholder="FINPO26-CERT-000123" value="{{ $number }}">
            <button class="btn btn-fp-primary text-nowrap">{{ __('Vérifier') }}</button>
        </form>

        @if ($number)
            @if ($certificate)
                <div class="alert alert-success reveal is-visible">
                    <h2 class="h5 mb-2">✓ {{ __('Certificat authentique') }}</h2>
                    <p class="mb-1"><strong>{{ $certificate->registration->fullName() }}</strong> — {{ $certificate->registration->institution ?: $certificate->registration->audienceLabel() }}</p>
                    <p class="mb-0 small">{{ __('N°') }} {{ $certificate->number }} · {{ __('délivré le') }} {{ $certificate->issued_at->translatedFormat('d F Y') }}</p>
                </div>
            @else
                <div class="alert alert-danger reveal is-visible">
                    <h2 class="h5 mb-1">✗ {{ __('Certificat introuvable') }}</h2>
                    <p class="mb-0 small">{{ __('Aucun certificat ne correspond au numéro « :number ». Vérifiez la saisie ou contactez l\'organisation.', ['number' => $number]) }}</p>
                </div>
            @endif
        @endif
    </div>
</section>
@endsection

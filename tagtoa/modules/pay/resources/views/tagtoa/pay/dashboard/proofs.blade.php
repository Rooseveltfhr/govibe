{{-- TAGTOA PAY — Dashboard : preuves reçues pour une page
     ADAPTER @extends au layout admin du projet. --}}
@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:820px">
    <a href="{{ route('tagtoa.pay.dashboard.index') }}" class="text-decoration-none small">
        <i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}
    </a>
    <h4 class="my-3 fw-bold" style="font-family:'Space Grotesk',sans-serif">
        {{ __('Preuves de paiement') }} — {{ $page->title ?: $page->alias }}
    </h4>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if($proofs->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="fa-regular fa-receipt fa-2x mb-3 d-block"></i>{{ __('Aucune preuve reçue.') }}
        </div>
    @else
        @foreach($proofs as $pr)
            @php
                $cls = [0=>'warning',1=>'success',2=>'danger'][$pr->status] ?? 'secondary';
            @endphp
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="fw-bold mb-1">{{ $pr->payer_name }}
                                <span class="badge bg-{{ $cls }} ms-1">{{ $pr->status_label }}</span>
                            </h6>
                            <div class="small text-muted">
                                <i class="fa-solid {{ optional($pr->method)->icon ?? 'fa-money-check-dollar' }} me-1"></i>
                                {{ optional($pr->method)->display_label }}
                                @if($pr->payer_phone) · <i class="fa-brands fa-whatsapp"></i> {{ $pr->payer_phone }} @endif
                            </div>
                            <div class="small mt-1">
                                @if($pr->amount)<b>{{ number_format($pr->amount, 2) }} {{ $pr->currency }}</b>@endif
                                @if($pr->reference) · {{ __('Réf') }}: {{ $pr->reference }} @endif
                                · {{ $pr->created_at->diffForHumans() }}
                            </div>
                            @if($pr->note)<div class="small text-muted mt-1"><i class="fa-solid fa-note-sticky"></i> {{ $pr->note }}</div>@endif
                        </div>
                        @if($pr->image_url)
                            <a href="{{ $pr->image_url }}" target="_blank">
                                <img src="{{ $pr->image_url }}" loading="lazy" alt="preuve"
                                     style="width:84px;height:84px;object-fit:cover;border-radius:10px;border:1px solid #eee">
                            </a>
                        @endif
                    </div>

                    @if($pr->isPending())
                        <div class="d-flex gap-2 mt-3">
                            <form method="POST" action="{{ route('tagtoa.pay.dashboard.proofs.approve', $pr->id) }}">
                                @csrf
                                <button class="btn btn-sm btn-success"><i class="fa-solid fa-check me-1"></i>{{ __('Approuver') }}</button>
                            </form>
                            <form method="POST" action="{{ route('tagtoa.pay.dashboard.proofs.reject', $pr->id) }}" class="d-flex gap-2">
                                @csrf
                                <input name="note" class="form-control form-control-sm" placeholder="{{ __('Raison (optionnel)') }}" style="max-width:220px">
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark me-1"></i>{{ __('Rejeter') }}</button>
                            </form>
                        </div>
                    @elseif($pr->reviewed_at)
                        <div class="small text-muted mt-2">{{ __('Traité') }} {{ $pr->reviewed_at->diffForHumans() }}</div>
                    @endif
                </div>
            </div>
        @endforeach
        <div class="mt-3">{{ $proofs->links() }}</div>
    @endif
</div>
@endsection

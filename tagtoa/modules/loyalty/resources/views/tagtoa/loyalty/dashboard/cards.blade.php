{{-- TAGTOA LOYALTY — Dashboard : cartes d'un programme (émettre, recharger, utiliser).
     ADAPTER @extends au layout admin du projet (Bootstrap). --}}
@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:880px">
    <a href="{{ route('tagtoa.loyalty.dashboard.index') }}" class="text-decoration-none small">
        <i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}
    </a>
    <h4 class="my-3 fw-bold" style="font-family:'Space Grotesk',sans-serif">
        {{ __('Cartes') }} — {{ $program->name }}
    </h4>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    {{-- Détails de la carte fraîchement émise (CVC visible une seule fois) --}}
    @if(session('new_card'))
        @php $nc = session('new_card'); @endphp
        <div class="alert alert-warning">
            <b>{{ __('Carte émise — notez le CVC, il ne sera plus affiché :') }}</b>
            <div class="mt-2" style="font-family:'Space Grotesk',monospace">
                <div><i class="fa-solid fa-credit-card me-2"></i>{{ $nc['number'] }}</div>
                <div><i class="fa-solid fa-lock me-2"></i>CVC : <b>{{ $nc['cvc'] }}</b></div>
                <div><i class="fa-solid fa-link me-2"></i><a href="{{ $nc['url'] }}" target="_blank">{{ $nc['url'] }}</a></div>
            </div>
        </div>
    @endif

    @error('amount')<div class="alert alert-danger">{{ $message }}</div>@enderror

    {{-- Émettre une carte --}}
    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px">
        <div class="card-body">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-plus me-1"></i>{{ __('Émettre une carte') }}</h6>
            <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.cards.issue', $program->id) }}" class="row g-2">
                @csrf
                <div class="col-md-4"><input name="cardholder_name" class="form-control form-control-sm" placeholder="{{ __('Nom du titulaire') }}" required></div>
                <div class="col-md-3"><input name="cardholder_phone" class="form-control form-control-sm" placeholder="{{ __('Téléphone') }}"></div>
                <div class="col-md-3"><input name="cardholder_email" type="email" class="form-control form-control-sm" placeholder="Email"></div>
                <div class="col-md-2"><input name="balance" type="number" step="0.01" class="form-control form-control-sm" placeholder="{{ __('Solde init.') }}"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary" style="background:#0055FF;border:0">{{ __('Émettre la carte') }}</button></div>
            </form>
        </div>
    </div>

    {{-- Liste des cartes --}}
    @if($cards->isEmpty())
        <p class="text-muted text-center py-4">{{ __('Aucune carte émise.') }}</p>
    @else
        @foreach($cards as $c)
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold mb-1">{{ $c->cardholder_name }}
                                <span class="badge bg-{{ $c->status === 1 ? 'success' : ($c->status === 2 ? 'danger' : 'secondary') }} ms-1">{{ $c->status_label }}</span>
                            </h6>
                            <div class="small text-muted" style="font-family:'Space Grotesk',monospace">{{ $c->masked_number }} · exp {{ optional($c->expiry_date)->format('m/y') }}</div>
                            <div class="small mt-1">
                                <b>{{ number_format($c->balance, 2) }} {{ $program->currency }}</b> ·
                                <i class="fa-solid fa-star text-warning"></i> {{ number_format($c->points) }} pts
                            </div>
                            <a href="{{ $c->public_url }}" target="_blank" class="small">{{ __('Voir la carte') }} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                        </div>
                        <div class="d-flex flex-column gap-2" style="min-width:240px">
                            <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.cards.topup', $c->id) }}" class="input-group input-group-sm">
                                @csrf
                                <input name="amount" type="number" step="0.01" class="form-control" placeholder="{{ __('Recharger') }}" required>
                                <button class="btn btn-success">{{ __('Top-up') }}</button>
                            </form>
                            <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.cards.redeem', $c->id) }}" class="input-group input-group-sm">
                                @csrf
                                <input name="amount" type="number" step="0.01" class="form-control" placeholder="{{ __('Débiter') }}" required>
                                <button class="btn btn-outline-danger">{{ __('Payer') }}</button>
                            </form>
                            <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.cards.status', $c->id) }}" class="input-group input-group-sm">
                                @csrf
                                <select name="status" class="form-select">
                                    <option value="1" @selected($c->status===1)>{{ __('Active') }}</option>
                                    <option value="0" @selected($c->status===0)>{{ __('Suspendue') }}</option>
                                    <option value="2" @selected($c->status===2)>{{ __('Expirée') }}</option>
                                </select>
                                <button class="btn btn-outline-dark">{{ __('OK') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="mt-3">{{ $cards->links() }}</div>
    @endif
</div>
@endsection

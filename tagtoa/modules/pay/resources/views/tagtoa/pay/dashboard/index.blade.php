{{-- TAGTOA PAY — Dashboard : liste des pages de paiement
     ADAPTER @extends au layout admin du projet (ex: 'layouts.app', 'admin.layout').
     Le projet existant est en Bootstrap : ces vues utilisent des classes Bootstrap. --}}
@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1" style="font-family:'Space Grotesk',sans-serif;font-weight:700">TAGTOA PAY</h4>
            <small class="text-muted">{{ __('Vos pages de paiement publiques') }}</small>
        </div>
        <a href="{{ route('tagtoa.pay.dashboard.create') }}" class="btn btn-primary" style="background:#0055FF;border:0">
            <i class="fa-solid fa-plus me-1"></i> {{ __('Nouvelle page') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($pages->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="fa-regular fa-credit-card fa-2x mb-3 d-block"></i>
            {{ __('Aucune page de paiement. Créez votre première!') }}
        </div>
    @else
        <div class="row g-3">
            @foreach($pages as $p)
                <div class="col-md-6">
                    <div class="card shadow-sm border-0" style="border-radius:16px">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h6 class="fw-bold mb-1">{{ $p->title ?: $p->alias }}</h6>
                                <span class="badge {{ $p->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $p->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </div>
                            <a href="{{ url('/pay/'.$p->alias) }}" target="_blank" class="small text-decoration-none">
                                tagtoa.com/pay/{{ $p->alias }} <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                            </a>
                            <div class="d-flex gap-3 mt-3 small text-muted">
                                <span><i class="fa-solid fa-wallet me-1"></i>{{ $p->methods_count }} {{ __('méthodes') }}</span>
                                <span><i class="fa-solid fa-eye me-1"></i>{{ $p->views }}</span>
                                @if($p->pending_proofs_count)
                                    <span class="text-danger fw-bold">
                                        <i class="fa-solid fa-bell me-1"></i>{{ $p->pending_proofs_count }} {{ __('en attente') }}
                                    </span>
                                @endif
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <a href="{{ route('tagtoa.pay.dashboard.edit', $p->id) }}" class="btn btn-sm btn-outline-dark">
                                    <i class="fa-solid fa-pen"></i> {{ __('Modifier') }}
                                </a>
                                <a href="{{ route('tagtoa.pay.dashboard.proofs', $p->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-receipt"></i> {{ __('Preuves') }}
                                </a>
                                <form method="POST" action="{{ route('tagtoa.pay.dashboard.destroy', $p->id) }}"
                                      onsubmit="return confirm('{{ __('Supprimer cette page?') }}')" class="ms-auto">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $pages->links() }}</div>
    @endif
</div>
@endsection

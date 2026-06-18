{{-- TAGTOA LOYALTY — Dashboard : liste des programmes.
     ADAPTER @extends au layout admin du projet (Bootstrap). --}}
@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1" style="font-family:'Space Grotesk',sans-serif;font-weight:700">TAGTOA LOYALTY</h4>
            <small class="text-muted">{{ __('Vos programmes de fidélité NFC') }}</small>
        </div>
        <a href="{{ route('tagtoa.loyalty.dashboard.create') }}" class="btn btn-primary" style="background:#0055FF;border:0">
            <i class="fa-solid fa-plus me-1"></i> {{ __('Nouveau programme') }}
        </a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if($programs->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="fa-regular fa-id-card fa-2x mb-3 d-block"></i>
            {{ __('Aucun programme. Créez votre première carte de fidélité!') }}
        </div>
    @else
        <div class="row g-3">
            @foreach($programs as $p)
                <div class="col-md-6">
                    <div class="card shadow-sm border-0" style="border-radius:16px">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h6 class="fw-bold mb-1">{{ $p->name }}</h6>
                                <span class="badge {{ $p->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $p->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </div>
                            <div class="small text-muted">
                                {{ $p->points_per_dollar }} pts / {{ $p->currency }} ·
                                {{ $p->cards_count }} {{ __('cartes') }}
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <a href="{{ route('tagtoa.loyalty.dashboard.cards', $p->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-credit-card"></i> {{ __('Cartes') }}
                                </a>
                                <a href="{{ route('tagtoa.loyalty.dashboard.edit', $p->id) }}" class="btn btn-sm btn-outline-dark">
                                    <i class="fa-solid fa-pen"></i> {{ __('Modifier') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $programs->links() }}</div>
    @endif
</div>
@endsection

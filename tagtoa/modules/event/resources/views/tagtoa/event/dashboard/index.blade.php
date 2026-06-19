{{-- TAGTOA EVENT — Dashboard liste. ADAPTER @extends au layout admin. --}}
@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1" style="font-family:'Space Grotesk',sans-serif;font-weight:700">TAGTOA EVENT</h4>
            <small class="text-muted">{{ __('Vos événements (billetterie + check-in NFC/QR)') }}</small>
        </div>
        <a href="{{ route('tagtoa.event.dashboard.create') }}" class="btn btn-primary" style="background:#0055FF;border:0">
            <i class="fa-solid fa-plus me-1"></i> {{ __('Nouvel événement') }}
        </a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if($events->isEmpty())
        <div class="text-center text-muted py-5"><i class="fa-regular fa-calendar fa-2x mb-3 d-block"></i>{{ __('Aucun événement.') }}</div>
    @else
        <div class="row g-3">
            @foreach($events as $e)
                <div class="col-md-6">
                    <div class="card shadow-sm border-0" style="border-radius:16px">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h6 class="fw-bold mb-1">{{ $e->title }}</h6>
                                <span class="badge {{ $e->is_published ? 'bg-success' : 'bg-secondary' }}">{{ $e->is_published ? __('Publié') : __('Brouillon') }}</span>
                            </div>
                            <div class="small text-muted">
                                {{ $e->starts_at ? $e->starts_at->format('d/m/Y H:i') : '—' }} ·
                                {{ $e->tickets_count }} {{ __('billets') }} · {{ $e->orders_count }} {{ __('commandes') }}
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a href="{{ route('tagtoa.event.dashboard.edit', $e->id) }}" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                                <a href="{{ route('tagtoa.event.dashboard.orders', $e->id) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-chart-line"></i> {{ __('Commandes') }}</a>
                                <a href="{{ route('tagtoa.event.dashboard.scanner', $e->id) }}" class="btn btn-sm btn-dark"><i class="fa-solid fa-qrcode"></i> {{ __('Scanner') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
</div>
@endsection

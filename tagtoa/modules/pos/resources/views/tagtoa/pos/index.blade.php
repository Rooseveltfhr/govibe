{{-- TAGTOA POS — liste des caisses. ADAPTER @extends au layout admin. --}}
@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1" style="font-family:'Space Grotesk',sans-serif;font-weight:700">TAGTOA POS</h4>
            <small class="text-muted">{{ __('Caisses tactiles (offline-first)') }}</small>
        </div>
        <form method="POST" action="{{ route('tagtoa.pos.store') }}" class="d-flex gap-2">
            @csrf
            <input name="name" class="form-control form-control-sm" placeholder="{{ __('Nom de la caisse') }}" required>
            <button class="btn btn-sm btn-primary" style="background:#0055FF;border:0"><i class="fa-solid fa-plus"></i></button>
        </form>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if($terminals->isEmpty())
        <div class="text-center text-muted py-5"><i class="fa-solid fa-cash-register fa-2x mb-3 d-block"></i>{{ __('Aucune caisse.') }}</div>
    @else
        <div class="row g-3">
            @foreach($terminals as $t)
                <div class="col-md-4">
                    <div class="card shadow-sm border-0" style="border-radius:16px"><div class="card-body">
                        <h6 class="fw-bold">{{ $t->name }}</h6>
                        <div class="small text-muted">{{ $t->products_count }} {{ __('produits') }} · {{ __('Caisse') }}: {{ number_format($t->cash_balance,2) }} {{ $t->currency }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a href="{{ route('tagtoa.pos.register',$t->id) }}" class="btn btn-sm btn-dark"><i class="fa-solid fa-cash-register"></i> {{ __('Ouvrir') }}</a>
                            <a href="{{ route('tagtoa.pos.products',$t->id) }}" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-boxes-stacked"></i> {{ __('Produits') }}</a>
                            <a href="{{ route('tagtoa.pos.report',$t->id) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-chart-simple"></i> {{ __('Rapport') }}</a>
                        </div>
                    </div></div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

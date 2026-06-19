{{-- TAGTOA POS — Rapport Z journalier + historique. ADAPTER @extends. --}}
@extends('layouts.app')
@section('content')
<div class="container py-4" style="max-width:820px">
    <a href="{{ route('tagtoa.pos.index') }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}</a>
    <div class="d-flex justify-content-between align-items-center my-3">
        <h4 class="fw-bold mb-0" style="font-family:'Space Grotesk',sans-serif">{{ __('Rapport Z') }} — {{ $terminal->name }}</h4>
        <form method="GET" class="d-flex gap-2"><input type="date" name="date" value="{{ $z['date'] }}" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-dark">{{ __('Voir') }}</button></form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body text-center"><div class="fw-bold fs-4">{{ $z['count'] }}</div><div class="small text-muted">{{ __('Ventes') }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body text-center"><div class="fw-bold fs-4">{{ number_format($z['total'],2) }}</div><div class="small text-muted">{{ __('Total') }} ({{ $terminal->currency }})</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body text-center"><div class="fw-bold fs-4">{{ number_format($terminal->cash_balance,2) }}</div><div class="small text-muted">{{ __('Caisse') }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body text-center"><div class="fw-bold fs-4">{{ count($z['by_method']) }}</div><div class="small text-muted">{{ __('Méthodes') }}</div></div></div></div>
    </div>

    @if($z['by_method'])
    <div class="card border-0 shadow-sm mb-3" style="border-radius:14px"><div class="card-body">
        <h6 class="fw-bold">{{ __('Ventilation par paiement') }}</h6>
        @foreach($z['by_method'] as $m=>$amt)
            <div class="d-flex justify-content-between border-bottom py-1"><span class="text-capitalize">{{ $m }}</span><b>{{ number_format($amt,2) }}</b></div>
        @endforeach
    </div></div>
    @endif

    <h6 class="fw-bold">{{ __('Historique du jour') }}</h6>
    @if($sales->isEmpty())
        <p class="text-muted small">{{ __('Aucune vente.') }}</p>
    @else
        <table class="table table-sm align-middle">
            <thead><tr><th>{{ __('Réf') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Paiement') }}</th><th>{{ __('Heure') }}</th></tr></thead>
            <tbody>
            @foreach($sales as $s)
                <tr><td class="small fw-bold">{{ $s->reference }}</td><td class="text-end">{{ number_format($s->total,2) }}</td>
                    <td class="small">{{ collect($s->payments)->pluck('method')->implode(', ') }}</td>
                    <td class="small text-muted">{{ optional($s->sold_at)->format('H:i') }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection

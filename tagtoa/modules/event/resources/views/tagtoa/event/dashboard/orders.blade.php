{{-- TAGTOA EVENT — Dashboard commandes + analytics. ADAPTER @extends. --}}
@extends('layouts.app')
@section('content')
<div class="container py-4" style="max-width:900px">
    <a href="{{ route('tagtoa.event.dashboard.index') }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}</a>
    <div class="d-flex justify-content-between align-items-center my-3">
        <h4 class="fw-bold mb-0" style="font-family:'Space Grotesk',sans-serif">{{ $event->title }}</h4>
        <a href="{{ route('tagtoa.event.dashboard.orders.export', $event->id) }}" class="btn btn-sm btn-outline-dark"><i class="fa-solid fa-file-csv me-1"></i>{{ __('Export CSV') }}</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body text-center"><div class="fw-bold fs-4">{{ number_format($analytics['revenue'],2) }}</div><div class="small text-muted">{{ __('Revenu') }} ({{ $event->currency }})</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body text-center"><div class="fw-bold fs-4">{{ $analytics['orders'] }}</div><div class="small text-muted">{{ __('Commandes') }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body text-center"><div class="fw-bold fs-4">{{ $analytics['tickets'] }}</div><div class="small text-muted">{{ __('Billets') }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body text-center"><div class="fw-bold fs-4 text-success">{{ $analytics['checked_in'] }}</div><div class="small text-muted">{{ __('Entrés') }}</div></div></div></div>
    </div>

    @if($orders->isEmpty())
        <p class="text-muted text-center py-4">{{ __('Aucune commande.') }}</p>
    @else
        <table class="table table-sm align-middle">
            <thead><tr><th>{{ __('Réf') }}</th><th>{{ __('Acheteur') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Billets') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Date') }}</th></tr></thead>
            <tbody>
            @foreach($orders as $o)
                <tr>
                    <td class="small fw-bold">{{ $o->reference }}</td>
                    <td>{{ $o->buyer_name }}<div class="small text-muted">{{ $o->buyer_phone }}</div></td>
                    <td class="text-end">{{ number_format($o->total,2) }}</td>
                    <td>{{ $o->tickets_count }}</td>
                    <td><span class="badge bg-{{ $o->status===1?'success':($o->status===0?'warning':'secondary') }}">{{ [0=>__('En attente'),1=>__('Payé'),2=>__('Annulé'),3=>__('Remboursé')][$o->status] ?? '' }}</span></td>
                    <td class="small text-muted">{{ $o->created_at->format('d/m/y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $orders->links() }}
    @endif
</div>
@endsection

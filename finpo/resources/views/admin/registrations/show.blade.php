@extends('layouts.admin', ['title' => $registration->number])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h1 class="h3 mb-0">{{ $registration->number }} <small class="fp-muted fs-6">{{ $registration->fullName() }}</small></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-fp-outline btn-sm" target="_blank" href="{{ route('ticket.show', $registration->qr_token) }}">🎟️ Billet</a>
        <a class="btn btn-fp-outline btn-sm" target="_blank" href="{{ route('badge.show', $registration->qr_token) }}">🪪 Badge</a>
        <form method="post" action="{{ route('admin.registrations.certificate', $registration) }}">
            @csrf
            <button class="btn btn-fp-outline btn-sm">📜 {{ $registration->certificate ? 'Voir le certificat' : 'Générer le certificat' }}</button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="fp-card p-4">
            <h2 class="h5 mb-3">Informations</h2>
            <dl class="row mb-0 small">
                <dt class="col-sm-4">Nom complet</dt><dd class="col-sm-8">{{ $registration->fullName() }}</dd>
                <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $registration->email }}</dd>
                <dt class="col-sm-4">Téléphone</dt><dd class="col-sm-8">{{ $registration->phone ?: '—' }}</dd>
                <dt class="col-sm-4">Institution</dt><dd class="col-sm-8">{{ $registration->institution ?: '—' }}</dd>
                <dt class="col-sm-4">Fonction</dt><dd class="col-sm-8">{{ $registration->position ?: '—' }}</dd>
                <dt class="col-sm-4">Pays</dt><dd class="col-sm-8">{{ $registration->country }}</dd>
                <dt class="col-sm-4">Catégorie</dt><dd class="col-sm-8">{{ $registration->audienceLabel() }}</dd>
                <dt class="col-sm-4">Contact d'urgence</dt><dd class="col-sm-8">{{ $registration->emergency_contact ?: '—' }}</dd>
                <dt class="col-sm-4">Billet</dt><dd class="col-sm-8">{{ $registration->category?->name }} — {{ number_format($registration->amount, 0, ',', ' ') }} {{ $registration->currency }} @if($registration->coupon) (code {{ $registration->coupon->code }}) @endif</dd>
                <dt class="col-sm-4">Paiement</dt><dd class="col-sm-8">{{ config('finpo.payment_methods.'.$registration->payment_method, $registration->payment_method) }} — @include('admin.partials.payment-badge', ['registration' => $registration])</dd>
                <dt class="col-sm-4">Statut</dt><dd class="col-sm-8">{{ $registration->status === 'cancelled' ? '❌ Annulée' : '✅ Confirmée' }}</dd>
                <dt class="col-sm-4">Check-in</dt><dd class="col-sm-8">{{ $registration->checked_in_at?->format('d/m/Y H:i') ?: '—' }}</dd>
            </dl>
        </div>
        @if ($registration->checkins->isNotEmpty())
            <div class="fp-card p-4 mt-3">
                <h2 class="h5 mb-3">Historique check-in</h2>
                <ul class="small mb-0">
                    @foreach ($registration->checkins as $log)
                        <li>{{ $log->created_at->format('d/m/Y H:i') }} — {{ $log->method }} — {{ $log->result }} @if($log->user) par {{ $log->user->name }} @endif</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <div class="col-lg-5">
        <div class="fp-card p-4">
            <h2 class="h5 mb-3">Actions</h2>
            <div class="d-grid gap-2">
                @foreach ([
                    'mark_paid' => ['✅ Marquer payé', $registration->payment_status !== 'paid'],
                    'mark_pending' => ['↩️ Repasser en attente', $registration->payment_status === 'paid'],
                    'checkin' => ['🎫 Check-in manuel', ! $registration->checked_in_at],
                    'undo_checkin' => ['↩️ Annuler le check-in', (bool) $registration->checked_in_at],
                    'cancel' => ['❌ Annuler l\'inscription', $registration->status !== 'cancelled'],
                    'restore' => ['♻️ Restaurer', $registration->status === 'cancelled'],
                ] as $action => [$label, $show])
                    @if ($show)
                        <form method="post" action="{{ route('admin.registrations.status', $registration) }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $action }}">
                            <button class="btn btn-fp-outline btn-sm w-100 text-start">{{ $label }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

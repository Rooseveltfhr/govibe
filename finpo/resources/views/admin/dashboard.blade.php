@extends('layouts.admin', ['title' => 'Tableau de bord'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Tableau de bord</h1>
    <a href="{{ route('admin.registrations.export') }}" class="btn btn-fp-outline btn-sm">⬇ Export CSV inscriptions</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="fp-card p-3"><div class="fp-muted small">Inscriptions</div><div class="h3 mb-0">{{ number_format($totalRegistrations, 0, ',', ' ') }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="fp-card p-3"><div class="fp-muted small">Payées / confirmées</div><div class="h3 mb-0">{{ number_format($paidRegistrations, 0, ',', ' ') }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="fp-card p-3"><div class="fp-muted small">Check-ins</div><div class="h3 mb-0">{{ number_format($checkedIn, 0, ',', ' ') }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="fp-card p-3"><div class="fp-muted small">Revenu encaissé</div><div class="h3 mb-0">{{ number_format($revenue, 0, ',', ' ') }} <small class="fs-6">HTG</small></div><div class="fp-muted small">En attente : {{ number_format($pendingRevenue, 0, ',', ' ') }} HTG</div></div></div>
</div>

@if ($pendingPartners + $pendingSponsors + $pendingExhibitors > 0)
    <div class="alert alert-warning d-flex flex-wrap gap-3">
        <strong>À traiter :</strong>
        @if ($pendingPartners)<a href="{{ route('admin.partners.index') }}">{{ $pendingPartners }} partenaire(s) en attente</a>@endif
        @if ($pendingSponsors)<a href="{{ route('admin.sponsors.index') }}">{{ $pendingSponsors }} sponsor(s) en attente</a>@endif
        @if ($pendingExhibitors)<a href="{{ route('admin.exhibitors.index') }}">{{ $pendingExhibitors }} exposant(s) en attente</a>@endif
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="fp-card p-4 h-100">
            <h2 class="h5 mb-3">Inscriptions — 14 derniers jours</h2>
            @php $chartMax = max(1, $chartValues->max()); @endphp
            <div class="d-flex align-items-end gap-1" style="height: 160px;" role="img" aria-label="Histogramme des inscriptions">
                @foreach ($chartValues as $i => $value)
                    <div class="flex-fill text-center" title="{{ $chartDays[$i] }} : {{ $value }}">
                        <div class="mx-auto rounded-top" style="width: 70%; height: {{ max(3, ($value / $chartMax) * 140) }}px; background: var(--fp-grad);"></div>
                        <small class="fp-muted" style="font-size:.58rem;">{{ substr($chartDays[$i], 8) }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="fp-card p-4 h-100">
            <h2 class="h5 mb-3">Par catégorie de billet</h2>
            <div class="d-grid gap-2">
                @foreach ($byCategory as $category)
                    <div>
                        <div class="d-flex justify-content-between small">
                            <span>{{ $category->name }}</span>
                            <span class="fp-muted">{{ $category->registrations_count }}@if($category->quota) / {{ $category->quota }}@endif</span>
                        </div>
                        <div class="progress" style="height: 6px; background: var(--fp-card);">
                            <div class="progress-bar" style="width: {{ $category->quota ? min(100, $category->registrations_count / $category->quota * 100) : ($category->registrations_count ? 30 : 0) }}%; background: {{ $category->color }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="fp-card p-4 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Dernières inscriptions</h2>
        <a href="{{ route('admin.registrations.index') }}" class="small">Tout voir →</a>
    </div>
    <div class="table-responsive">
        <table class="table fp-table">
            <thead><tr><th>N°</th><th>Nom</th><th>Billet</th><th>Montant</th><th>Paiement</th><th>Date</th></tr></thead>
            <tbody>
                @foreach ($latest as $registration)
                    <tr>
                        <td><a href="{{ route('admin.registrations.show', $registration) }}">{{ $registration->number }}</a></td>
                        <td>{{ $registration->fullName() }}</td>
                        <td>{{ $registration->category?->name }}</td>
                        <td>{{ number_format($registration->amount, 0, ',', ' ') }} {{ $registration->currency }}</td>
                        <td>@include('admin.partials.payment-badge', ['registration' => $registration])</td>
                        <td class="fp-muted small">{{ $registration->created_at->format('d/m H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@extends('layouts.admin', ['title' => 'Inscriptions'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h1 class="h3 mb-0">Inscriptions <span class="fp-muted fs-6">({{ $registrations->total() }})</span></h1>
    <a href="{{ route('admin.registrations.export', request()->query()) }}" class="btn btn-fp-outline btn-sm">⬇ Export CSV / Excel</a>
</div>

<form method="get" class="fp-card p-3 d-flex flex-wrap gap-2 mb-3">
    <input type="search" name="q" value="{{ request('q') }}" class="form-control w-auto flex-grow-1" placeholder="🔍 Nom, email, n° billet…">
    <select name="categorie" class="form-select w-auto">
        <option value="">Tous les billets</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(request('categorie') == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <select name="paiement" class="form-select w-auto">
        <option value="">Tout paiement</option>
        @foreach (['pending' => 'En attente', 'paid' => 'Payé', 'free' => 'Gratuit', 'refunded' => 'Remboursé'] as $key => $label)
            <option value="{{ $key }}" @selected(request('paiement') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="form-check align-self-center ms-1">
        <input class="form-check-input" type="checkbox" id="f-checkin" name="checkin" value="1" @checked(request('checkin') === '1')>
        <label class="form-check-label small" for="f-checkin">Check-in fait</label>
    </div>
    <button class="btn btn-fp-primary">Filtrer</button>
</form>

<div class="fp-card p-3">
    <div class="table-responsive">
        <table class="table fp-table">
            <thead><tr><th>N°</th><th>Participant</th><th>Billet</th><th>Montant</th><th>Paiement</th><th>Check-in</th><th>Date</th></tr></thead>
            <tbody>
                @forelse ($registrations as $registration)
                    <tr>
                        <td><a href="{{ route('admin.registrations.show', $registration) }}">{{ $registration->number }}</a></td>
                        <td>{{ $registration->fullName() }}<br><small class="fp-muted">{{ $registration->email }}</small></td>
                        <td>{{ $registration->category?->name }}</td>
                        <td>{{ number_format($registration->amount, 0, ',', ' ') }} {{ $registration->currency }}</td>
                        <td>@include('admin.partials.payment-badge', ['registration' => $registration])</td>
                        <td>{!! $registration->checked_in_at ? '<span class="badge text-bg-success">'.$registration->checked_in_at->format('d/m H:i').'</span>' : '<span class="fp-muted small">—</span>' !!}</td>
                        <td class="fp-muted small">{{ $registration->created_at->format('d/m/y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="fp-muted">Aucune inscription trouvée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $registrations->links() }}
</div>
@endsection

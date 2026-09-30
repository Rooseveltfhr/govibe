@extends('layouts.admin', ['title' => 'Catégories de billets'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Catégories de billets</h1>
    <a href="{{ route('admin.tickets.create') }}" class="btn btn-fp-primary btn-sm">+ Nouvelle catégorie</a>
</div>
<div class="fp-card p-3">
    <div class="table-responsive">
        <table class="table fp-table">
            <thead><tr><th></th><th>Nom</th><th>Prix</th><th>Quota</th><th>Vendus</th><th>Période de vente</th><th>Statut</th><th></th></tr></thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td><span class="d-inline-block rounded-circle" style="width: 14px; height: 14px; background: {{ $category->color }};"></span></td>
                        <td><strong>{{ $category->name }}</strong><br><small class="fp-muted">{{ config('finpo.attendee_categories.'.$category->audience.'.label', $category->audience) }}</small></td>
                        <td>@if ($category->isFree()) <span class="badge text-bg-info">Gratuit</span> @else {{ number_format($category->price, 0, ',', ' ') }} {{ $category->currency }} @endif</td>
                        <td>{{ $category->quota ?? '∞' }}</td>
                        <td>{{ $category->registrations_count }}</td>
                        <td class="small fp-muted">{{ $category->sales_start?->format('d/m/y') ?? '—' }} → {{ $category->sales_end?->format('d/m/y') ?? '—' }}</td>
                        <td>
                            @if ($category->isOnSale()) <span class="badge text-bg-success">En vente</span>
                            @elseif (! $category->active) <span class="badge text-bg-secondary">Inactif</span>
                            @else <span class="badge text-bg-warning text-dark">Fermé / épuisé</span> @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.tickets.edit', $category) }}" class="btn btn-sm btn-fp-outline py-1">Modifier</a>
                            <form method="post" action="{{ route('admin.tickets.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Supprimer cette catégorie ?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-1">✕</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

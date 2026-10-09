@extends('layouts.admin', ['title' => 'Exposants'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Exposants</h1>
    <a href="{{ route('admin.exhibitors.create') }}" class="btn btn-fp-primary btn-sm">+ Ajouter</a>
</div>
<div class="fp-card p-3">
    <table class="table fp-table">
        <thead><tr><th>Entreprise</th><th>Secteur</th><th>Stand</th><th>Contact</th><th>Statut</th><th></th></tr></thead>
        <tbody>
            @foreach ($exhibitors as $exhibitor)
                <tr>
                    <td><strong>{{ $exhibitor->company }}</strong>{{ $exhibitor->featured ? ' ⭐' : '' }}</td>
                    <td class="small">{{ $exhibitor->sector }}</td>
                    <td>{{ $exhibitor->booth?->code ?? '—' }}</td>
                    <td class="small fp-muted">{{ $exhibitor->contact_name }} {{ $exhibitor->contact_email ? '· '.$exhibitor->contact_email : '' }}</td>
                    <td>@include('admin.partials.status-badge', ['status' => $exhibitor->status])</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.exhibitors.edit', $exhibitor) }}" class="btn btn-sm btn-fp-outline py-1">{{ $exhibitor->status === 'pending' ? 'Traiter' : 'Modifier' }}</a>
                        <form method="post" action="{{ route('admin.exhibitors.destroy', $exhibitor) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-1">✕</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

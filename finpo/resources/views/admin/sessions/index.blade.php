@extends('layouts.admin', ['title' => 'Programme'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Programme</h1>
    <a href="{{ route('admin.sessions.create') }}" class="btn btn-fp-primary btn-sm">+ Nouvelle session</a>
</div>
<div class="fp-card p-3">
    <table class="table fp-table">
        <thead><tr><th>Jour</th><th>Horaire</th><th>Titre</th><th>Salle</th><th>Type</th><th>Intervenants</th><th></th></tr></thead>
        <tbody>
            @foreach ($sessions as $session)
                <tr>
                    <td class="text-nowrap">{{ $session->day->format('d/m') }}</td>
                    <td class="text-nowrap">{{ substr($session->starts_at, 0, 5) }}–{{ substr($session->ends_at, 0, 5) }}</td>
                    <td><strong>{{ $session->title }}</strong>{{ $session->featured ? ' ⭐' : '' }}{!! $session->active ? '' : ' <span class="badge text-bg-secondary">Inactif</span>' !!}</td>
                    <td class="small">{{ $session->room?->name }}</td>
                    <td><span class="fp-chip">{{ $session->typeLabel() }}</span></td>
                    <td class="small fp-muted">{{ $session->speakers->pluck('name')->implode(', ') ?: '—' }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.sessions.edit', $session) }}" class="btn btn-sm btn-fp-outline py-1">Modifier</a>
                        <form method="post" action="{{ route('admin.sessions.destroy', $session) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-1">✕</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@extends('layouts.admin', ['title' => 'Sponsors'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Sponsors</h1>
    <a href="{{ route('admin.sponsors.create') }}" class="btn btn-fp-primary btn-sm">+ Ajouter</a>
</div>
<div class="fp-card p-3">
    <table class="table fp-table">
        <thead><tr><th>Nom</th><th>Niveau</th><th>Contact</th><th>Statut</th><th></th></tr></thead>
        <tbody>
            @foreach ($sponsors as $sponsor)
                <tr>
                    <td><strong>{{ $sponsor->name }}</strong></td>
                    <td><span class="badge" style="background: {{ $sponsor->levelColor() }}; color: #101a2e;">{{ $sponsor->levelLabel() }}</span></td>
                    <td class="small fp-muted">{{ $sponsor->contact_name }} {{ $sponsor->contact_email ? '· '.$sponsor->contact_email : '' }}</td>
                    <td>@include('admin.partials.status-badge', ['status' => $sponsor->status])</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.sponsors.edit', $sponsor) }}" class="btn btn-sm btn-fp-outline py-1">{{ $sponsor->status === 'pending' ? 'Traiter' : 'Modifier' }}</a>
                        <form method="post" action="{{ route('admin.sponsors.destroy', $sponsor) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-1">✕</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

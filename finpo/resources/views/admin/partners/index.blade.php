@extends('layouts.admin', ['title' => 'Partenaires'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Partenaires</h1>
    <a href="{{ route('admin.partners.create') }}" class="btn btn-fp-primary btn-sm">+ Ajouter</a>
</div>
<div class="fp-card p-3">
    <table class="table fp-table">
        <thead><tr><th>Nom</th><th>Catégorie</th><th>Contact</th><th>Statut</th><th></th></tr></thead>
        <tbody>
            @foreach ($partners as $partner)
                <tr>
                    <td><strong>{{ $partner->name }}</strong></td>
                    <td><span class="fp-chip">{{ $partner->categoryLabel() }}</span></td>
                    <td class="small fp-muted">{{ $partner->contact_name }} {{ $partner->contact_email ? '· '.$partner->contact_email : '' }}</td>
                    <td>@include('admin.partials.status-badge', ['status' => $partner->status])</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.partners.edit', $partner) }}" class="btn btn-sm btn-fp-outline py-1">{{ $partner->status === 'pending' ? 'Traiter' : 'Modifier' }}</a>
                        <form method="post" action="{{ route('admin.partners.destroy', $partner) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-1">✕</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@extends('layouts.admin', ['title' => 'Intervenants'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Intervenants</h1>
    <a href="{{ route('admin.speakers.create') }}" class="btn btn-fp-primary btn-sm">+ Ajouter</a>
</div>
<div class="fp-card p-3">
    <table class="table fp-table">
        <thead><tr><th>Nom</th><th>Institution</th><th>Catégorie</th><th>À la une</th><th>Actif</th><th></th></tr></thead>
        <tbody>
            @foreach ($speakers as $speaker)
                <tr>
                    <td class="d-flex align-items-center gap-2">
                        <img src="{{ $speaker->photo_url ?: 'https://placehold.co/80' }}" alt="" width="36" height="36" class="rounded-circle" style="object-fit: cover;">
                        <strong>{{ $speaker->name }}</strong>
                    </td>
                    <td>{{ $speaker->institution }}</td>
                    <td><span class="fp-chip">{{ $speaker->categoryLabel() }}</span></td>
                    <td>{{ $speaker->featured ? '⭐' : '—' }}</td>
                    <td>{!! $speaker->active ? '<span class="badge text-bg-success">Oui</span>' : '<span class="badge text-bg-secondary">Non</span>' !!}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.speakers.edit', $speaker) }}" class="btn btn-sm btn-fp-outline py-1">Modifier</a>
                        <form method="post" action="{{ route('admin.speakers.destroy', $speaker) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-1">✕</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@extends('layouts.admin', ['title' => 'Actualités'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Actualités</h1>
    <a href="{{ route('admin.news.create') }}" class="btn btn-fp-primary btn-sm">+ Nouvel article</a>
</div>
<div class="fp-card p-3">
    <table class="table fp-table">
        <thead><tr><th>Titre</th><th>Tag</th><th>Publication</th><th></th></tr></thead>
        <tbody>
            @foreach ($posts as $post)
                <tr>
                    <td><strong>{{ $post->title }}</strong></td>
                    <td><span class="fp-chip fp-chip-gold">{{ $post->tag }}</span></td>
                    <td class="small fp-muted">{{ $post->published_at ? $post->published_at->format('d/m/Y H:i') : 'Brouillon' }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('news.show', $post) }}" target="_blank" class="btn btn-sm btn-fp-outline py-1">Voir</a>
                        <a href="{{ route('admin.news.edit', $post) }}" class="btn btn-sm btn-fp-outline py-1">Modifier</a>
                        <form method="post" action="{{ route('admin.news.destroy', $post) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-1">✕</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@extends('layouts.admin', ['title' => 'Article'])

@section('content')
<h1 class="h3 mb-4">{{ $post->exists ? 'Modifier l\'article' : 'Nouvel article' }}</h1>
<form method="post" action="{{ $post->exists ? route('admin.news.update', $post) : route('admin.news.store') }}" class="fp-card p-4 row g-3" style="max-width: 900px;">
    @csrf
    @if ($post->exists) @method('PUT') @endif
    <div class="col-md-8"><label class="form-label" for="title">Titre *</label><input id="title" name="title" class="form-control" required value="{{ old('title', $post->title) }}"></div>
    <div class="col-md-4"><label class="form-label" for="tag">Tag *</label>
        <select id="tag" name="tag" class="form-select">
            @foreach (['Actualité', 'Annonce', 'Article', 'Communiqué', 'Mise à jour'] as $tag)<option @selected(old('tag', $post->tag) === $tag)>{{ $tag }}</option>@endforeach
        </select></div>
    <div class="col-md-8"><label class="form-label" for="cover_url">Image de couverture (URL)</label><input id="cover_url" type="url" name="cover_url" class="form-control" value="{{ old('cover_url', $post->cover_url) }}"></div>
    <div class="col-md-4"><label class="form-label" for="published_at">Publier le (vide = brouillon)</label><input id="published_at" type="datetime-local" name="published_at" class="form-control" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-12"><label class="form-label" for="excerpt">Extrait</label><textarea id="excerpt" name="excerpt" rows="2" class="form-control">{{ old('excerpt', $post->excerpt) }}</textarea></div>
    <div class="col-12"><label class="form-label" for="body">Contenu</label><textarea id="body" name="body" rows="10" class="form-control">{{ old('body', $post->body) }}</textarea></div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.news.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

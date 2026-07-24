@extends('layouts.admin', ['title' => 'Galerie'])

@section('content')
<h1 class="h3 mb-4">{{ $item->exists ? 'Modifier l\'élément' : 'Nouvel élément' }}</h1>
<form method="post" action="{{ $item->exists ? route('admin.gallery.update', $item) : route('admin.gallery.store') }}" class="fp-card p-4 row g-3" style="max-width: 640px;">
    @csrf
    @if ($item->exists) @method('PUT') @endif
    <div class="col-md-4"><label class="form-label" for="type">Type *</label>
        <select id="type" name="type" class="form-select"><option value="photo" @selected(old('type', $item->type) === 'photo')>Photo</option><option value="video" @selected(old('type', $item->type) === 'video')>Vidéo</option></select></div>
    <div class="col-md-4"><label class="form-label" for="edition">Édition *</label><input id="edition" type="number" name="edition" class="form-control" required value="{{ old('edition', $item->edition) }}"></div>
    <div class="col-md-4"><label class="form-label" for="sort">Ordre</label><input id="sort" type="number" min="0" name="sort" class="form-control" value="{{ old('sort', $item->sort ?? 0) }}"></div>
    <div class="col-12"><label class="form-label" for="url">URL (image ou vidéo) *</label><input id="url" type="url" name="url" class="form-control" required value="{{ old('url', $item->url) }}"></div>
    <div class="col-12"><label class="form-label" for="thumb_url">Miniature (URL)</label><input id="thumb_url" type="url" name="thumb_url" class="form-control" value="{{ old('thumb_url', $item->thumb_url) }}"></div>
    <div class="col-12"><label class="form-label" for="caption">Légende</label><input id="caption" name="caption" class="form-control" value="{{ old('caption', $item->caption) }}"></div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.gallery.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

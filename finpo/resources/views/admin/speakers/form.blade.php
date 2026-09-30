@extends('layouts.admin', ['title' => 'Intervenant'])

@section('content')
<h1 class="h3 mb-4">{{ $speaker->exists ? 'Modifier '.$speaker->name : 'Nouvel intervenant' }}</h1>
<form method="post" action="{{ $speaker->exists ? route('admin.speakers.update', $speaker) : route('admin.speakers.store') }}" class="fp-card p-4 row g-3" style="max-width: 900px;">
    @csrf
    @if ($speaker->exists) @method('PUT') @endif
    <div class="col-md-6"><label class="form-label" for="name">Nom *</label><input id="name" name="name" class="form-control" required value="{{ old('name', $speaker->name) }}"></div>
    <div class="col-md-6"><label class="form-label" for="category">Catégorie *</label>
        <select id="category" name="category" class="form-select">
            @foreach (config('finpo.speaker_categories') as $key => $label)
                <option value="{{ $key }}" @selected(old('category', $speaker->category) === $key)>{{ $label }}</option>
            @endforeach
        </select></div>
    <div class="col-md-6"><label class="form-label" for="position">Fonction</label><input id="position" name="position" class="form-control" value="{{ old('position', $speaker->position) }}"></div>
    <div class="col-md-6"><label class="form-label" for="institution">Institution</label><input id="institution" name="institution" class="form-control" value="{{ old('institution', $speaker->institution) }}"></div>
    <div class="col-md-6"><label class="form-label" for="country">Pays *</label><input id="country" name="country" class="form-control" required value="{{ old('country', $speaker->country ?? 'Haïti') }}"></div>
    <div class="col-md-6"><label class="form-label" for="topic">Sujet d'intervention</label><input id="topic" name="topic" class="form-control" value="{{ old('topic', $speaker->topic) }}"></div>
    <div class="col-12"><label class="form-label" for="photo_url">Photo (URL)</label><input id="photo_url" type="url" name="photo_url" class="form-control" value="{{ old('photo_url', $speaker->photo_url) }}"></div>
    <div class="col-12"><label class="form-label" for="bio">Biographie</label><textarea id="bio" name="bio" rows="4" class="form-control">{{ old('bio', $speaker->bio) }}</textarea></div>
    <div class="col-md-4"><label class="form-label" for="linkedin">LinkedIn</label><input id="linkedin" type="url" name="linkedin" class="form-control" value="{{ old('linkedin', $speaker->linkedin) }}"></div>
    <div class="col-md-4"><label class="form-label" for="facebook">Facebook</label><input id="facebook" type="url" name="facebook" class="form-control" value="{{ old('facebook', $speaker->facebook) }}"></div>
    <div class="col-md-4"><label class="form-label" for="website">Site web</label><input id="website" type="url" name="website" class="form-control" value="{{ old('website', $speaker->website) }}"></div>
    <div class="col-md-3"><label class="form-label" for="sort">Ordre</label><input id="sort" type="number" min="0" name="sort" class="form-control" value="{{ old('sort', $speaker->sort ?? 0) }}"></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" @checked(old('featured', $speaker->featured))><label class="form-check-label" for="featured">À la une</label></div></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="active" name="active" value="1" @checked(old('active', $speaker->active ?? true))><label class="form-check-label" for="active">Actif</label></div></div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.speakers.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

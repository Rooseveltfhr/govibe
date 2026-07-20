@extends('layouts.admin', ['title' => 'Session'])

@section('content')
<h1 class="h3 mb-4">{{ $session->exists ? 'Modifier la session' : 'Nouvelle session' }}</h1>
<form method="post" action="{{ $session->exists ? route('admin.sessions.update', $session) : route('admin.sessions.store') }}" class="fp-card p-4 row g-3" style="max-width: 900px;">
    @csrf
    @if ($session->exists) @method('PUT') @endif
    <div class="col-12"><label class="form-label" for="title">Titre *</label><input id="title" name="title" class="form-control" required value="{{ old('title', $session->title) }}"></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea id="description" name="description" rows="3" class="form-control">{{ old('description', $session->description) }}</textarea></div>
    <div class="col-md-3"><label class="form-label" for="day">Jour *</label><input id="day" type="date" name="day" class="form-control" required value="{{ old('day', $session->day?->format('Y-m-d')) }}"></div>
    <div class="col-md-3"><label class="form-label" for="starts_at">Début *</label><input id="starts_at" type="time" name="starts_at" class="form-control" required value="{{ old('starts_at', $session->starts_at ? substr($session->starts_at, 0, 5) : '') }}"></div>
    <div class="col-md-3"><label class="form-label" for="ends_at">Fin *</label><input id="ends_at" type="time" name="ends_at" class="form-control" required value="{{ old('ends_at', $session->ends_at ? substr($session->ends_at, 0, 5) : '') }}"></div>
    <div class="col-md-3"><label class="form-label" for="room_id">Salle</label>
        <select id="room_id" name="room_id" class="form-select"><option value="">—</option>
            @foreach ($rooms as $room)<option value="{{ $room->id }}" @selected(old('room_id', $session->room_id) == $room->id)>{{ $room->name }}</option>@endforeach
        </select></div>
    <div class="col-md-4"><label class="form-label" for="type">Format *</label>
        <select id="type" name="type" class="form-select">
            @foreach (config('finpo.session_types') as $key => $label)<option value="{{ $key }}" @selected(old('type', $session->type) === $key)>{{ $label }}</option>@endforeach
        </select></div>
    <div class="col-md-4"><label class="form-label" for="track">Thématique</label><input id="track" name="track" class="form-control" value="{{ old('track', $session->track) }}"></div>
    <div class="col-md-4 d-flex align-items-end gap-3">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" @checked(old('featured', $session->featured))><label class="form-check-label" for="featured">Phare</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="active" name="active" value="1" @checked(old('active', $session->active ?? true))><label class="form-check-label" for="active">Actif</label></div>
    </div>
    <div class="col-12">
        <label class="form-label" for="speaker_ids">Intervenants</label>
        <select id="speaker_ids" name="speaker_ids[]" class="form-select" multiple size="6">
            @foreach ($speakers as $speaker)
                <option value="{{ $speaker->id }}" @selected(collect(old('speaker_ids', $session->speakers->pluck('id')))->contains($speaker->id))>{{ $speaker->name }} — {{ $speaker->institution }}</option>
            @endforeach
        </select>
        <div class="form-text">Ctrl/Cmd + clic pour sélectionner plusieurs.</div>
    </div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.sessions.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

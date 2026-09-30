@extends('layouts.admin', ['title' => 'Sponsor'])

@section('content')
<h1 class="h3 mb-4">{{ $sponsor->exists ? 'Modifier '.$sponsor->name : 'Nouveau sponsor' }}</h1>
<form method="post" action="{{ $sponsor->exists ? route('admin.sponsors.update', $sponsor) : route('admin.sponsors.store') }}" class="fp-card p-4 row g-3" style="max-width: 860px;">
    @csrf
    @if ($sponsor->exists) @method('PUT') @endif
    <div class="col-md-6"><label class="form-label" for="name">Entreprise *</label><input id="name" name="name" class="form-control" required value="{{ old('name', $sponsor->name) }}"></div>
    <div class="col-md-6"><label class="form-label" for="level">Niveau *</label>
        <select id="level" name="level" class="form-select">
            @foreach (config('finpo.sponsor_levels') as $key => $meta)<option value="{{ $key }}" @selected(old('level', $sponsor->level) === $key)>{{ $meta['label'] }} — {{ number_format($meta['price'], 0, ',', ' ') }} HTG</option>@endforeach
        </select></div>
    <div class="col-md-6"><label class="form-label" for="logo_url">Logo (URL)</label><input id="logo_url" type="url" name="logo_url" class="form-control" value="{{ old('logo_url', $sponsor->logo_url) }}"></div>
    <div class="col-md-6"><label class="form-label" for="website">Site web</label><input id="website" type="url" name="website" class="form-control" value="{{ old('website', $sponsor->website) }}"></div>
    <div class="col-md-4"><label class="form-label" for="contact_name">Contact</label><input id="contact_name" name="contact_name" class="form-control" value="{{ old('contact_name', $sponsor->contact_name) }}"></div>
    <div class="col-md-4"><label class="form-label" for="contact_email">Email</label><input id="contact_email" type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $sponsor->contact_email) }}"></div>
    <div class="col-md-4"><label class="form-label" for="contact_phone">Téléphone</label><input id="contact_phone" name="contact_phone" class="form-control" value="{{ old('contact_phone', $sponsor->contact_phone) }}"></div>
    @if ($sponsor->message)
        <div class="col-12"><div class="alert alert-secondary small mb-0"><strong>Message :</strong><br>{{ $sponsor->message }}</div></div>
    @endif
    <div class="col-md-4"><label class="form-label" for="status">Statut *</label>
        <select id="status" name="status" class="form-select">
            @foreach (['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté'] as $key => $label)<option value="{{ $key }}" @selected(old('status', $sponsor->status) === $key)>{{ $label }}</option>@endforeach
        </select></div>
    <div class="col-md-3"><label class="form-label" for="sort">Ordre</label><input id="sort" type="number" min="0" name="sort" class="form-control" value="{{ old('sort', $sponsor->sort ?? 0) }}"></div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.sponsors.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

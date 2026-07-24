@extends('layouts.admin', ['title' => 'Partenaire'])

@section('content')
<h1 class="h3 mb-4">{{ $partner->exists ? 'Modifier '.$partner->name : 'Nouveau partenaire' }}</h1>
<form method="post" action="{{ $partner->exists ? route('admin.partners.update', $partner) : route('admin.partners.store') }}" class="fp-card p-4 row g-3" style="max-width: 860px;">
    @csrf
    @if ($partner->exists) @method('PUT') @endif
    <div class="col-md-6"><label class="form-label" for="name">Organisation *</label><input id="name" name="name" class="form-control" required value="{{ old('name', $partner->name) }}"></div>
    <div class="col-md-6"><label class="form-label" for="category">Catégorie *</label>
        <select id="category" name="category" class="form-select">
            @foreach (config('finpo.partner_categories') as $key => $label)<option value="{{ $key }}" @selected(old('category', $partner->category) === $key)>{{ $label }}</option>@endforeach
        </select></div>
    <div class="col-md-6"><label class="form-label" for="logo_url">Logo (URL)</label><input id="logo_url" type="url" name="logo_url" class="form-control" value="{{ old('logo_url', $partner->logo_url) }}"></div>
    <div class="col-md-6"><label class="form-label" for="website">Site web</label><input id="website" type="url" name="website" class="form-control" value="{{ old('website', $partner->website) }}"></div>
    <div class="col-md-4"><label class="form-label" for="contact_name">Contact</label><input id="contact_name" name="contact_name" class="form-control" value="{{ old('contact_name', $partner->contact_name) }}"></div>
    <div class="col-md-4"><label class="form-label" for="contact_email">Email</label><input id="contact_email" type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $partner->contact_email) }}"></div>
    <div class="col-md-4"><label class="form-label" for="contact_phone">Téléphone</label><input id="contact_phone" name="contact_phone" class="form-control" value="{{ old('contact_phone', $partner->contact_phone) }}"></div>
    @if ($partner->message)
        <div class="col-12"><div class="alert alert-secondary small mb-0"><strong>Message de candidature :</strong><br>{{ $partner->message }}</div></div>
    @endif
    <div class="col-md-4"><label class="form-label" for="status">Statut (workflow d'approbation) *</label>
        <select id="status" name="status" class="form-select">
            @foreach (['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté'] as $key => $label)<option value="{{ $key }}" @selected(old('status', $partner->status) === $key)>{{ $label }}</option>@endforeach
        </select></div>
    <div class="col-md-3"><label class="form-label" for="sort">Ordre</label><input id="sort" type="number" min="0" name="sort" class="form-control" value="{{ old('sort', $partner->sort ?? 0) }}"></div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.partners.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

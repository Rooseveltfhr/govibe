@extends('layouts.admin', ['title' => 'Exposant'])

@section('content')
<h1 class="h3 mb-4">{{ $exhibitor->exists ? 'Modifier '.$exhibitor->company : 'Nouvel exposant' }}</h1>
<form method="post" action="{{ $exhibitor->exists ? route('admin.exhibitors.update', $exhibitor) : route('admin.exhibitors.store') }}" class="fp-card p-4 row g-3" style="max-width: 900px;">
    @csrf
    @if ($exhibitor->exists) @method('PUT') @endif
    <div class="col-md-6"><label class="form-label" for="company">Entreprise *</label><input id="company" name="company" class="form-control" required value="{{ old('company', $exhibitor->company) }}"></div>
    <div class="col-md-6"><label class="form-label" for="sector">Secteur</label><input id="sector" name="sector" class="form-control" value="{{ old('sector', $exhibitor->sector) }}"></div>
    <div class="col-md-6"><label class="form-label" for="logo_url">Logo (URL)</label><input id="logo_url" type="url" name="logo_url" class="form-control" value="{{ old('logo_url', $exhibitor->logo_url) }}"></div>
    <div class="col-md-6"><label class="form-label" for="banner_url">Bannière (URL)</label><input id="banner_url" type="url" name="banner_url" class="form-control" value="{{ old('banner_url', $exhibitor->banner_url) }}"></div>
    <div class="col-12"><label class="form-label" for="description">Description</label><textarea id="description" name="description" rows="3" class="form-control">{{ old('description', $exhibitor->description) }}</textarea></div>
    <div class="col-md-6"><label class="form-label" for="products">Produits</label><textarea id="products" name="products" rows="2" class="form-control">{{ old('products', $exhibitor->products) }}</textarea></div>
    <div class="col-md-6"><label class="form-label" for="services">Services</label><textarea id="services" name="services" rows="2" class="form-control">{{ old('services', $exhibitor->services) }}</textarea></div>
    <div class="col-md-4"><label class="form-label" for="website">Site web</label><input id="website" type="url" name="website" class="form-control" value="{{ old('website', $exhibitor->website) }}"></div>
    <div class="col-md-4"><label class="form-label" for="video_url">Vidéo (URL)</label><input id="video_url" type="url" name="video_url" class="form-control" value="{{ old('video_url', $exhibitor->video_url) }}"></div>
    <div class="col-md-4"><label class="form-label" for="brochure_url">Brochure (URL)</label><input id="brochure_url" type="url" name="brochure_url" class="form-control" value="{{ old('brochure_url', $exhibitor->brochure_url) }}"></div>
    <div class="col-md-4"><label class="form-label" for="facebook">Facebook</label><input id="facebook" type="url" name="facebook" class="form-control" value="{{ old('facebook', $exhibitor->socials['facebook'] ?? '') }}"></div>
    <div class="col-md-4"><label class="form-label" for="instagram">Instagram</label><input id="instagram" type="url" name="instagram" class="form-control" value="{{ old('instagram', $exhibitor->socials['instagram'] ?? '') }}"></div>
    <div class="col-md-4"><label class="form-label" for="linkedin">LinkedIn</label><input id="linkedin" type="url" name="linkedin" class="form-control" value="{{ old('linkedin', $exhibitor->socials['linkedin'] ?? '') }}"></div>
    <div class="col-md-4"><label class="form-label" for="booth_id">Stand</label>
        <select id="booth_id" name="booth_id" class="form-select"><option value="">—</option>
            @foreach ($booths as $booth)
                <option value="{{ $booth->id }}" @selected(old('booth_id', $exhibitor->booth_id) == $booth->id) @disabled($booth->status !== 'available' && $booth->id !== $exhibitor->booth_id)>{{ $booth->code }} ({{ $booth->status }})</option>
            @endforeach
        </select></div>
    <div class="col-md-4"><label class="form-label" for="contact_name">Contact</label><input id="contact_name" name="contact_name" class="form-control" value="{{ old('contact_name', $exhibitor->contact_name) }}"></div>
    <div class="col-md-4"><label class="form-label" for="contact_email">Email</label><input id="contact_email" type="email" name="contact_email" class="form-control" value="{{ old('contact_email', $exhibitor->contact_email) }}"></div>
    <div class="col-md-4"><label class="form-label" for="status">Statut *</label>
        <select id="status" name="status" class="form-select">
            @foreach (['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Rejeté'] as $key => $label)<option value="{{ $key }}" @selected(old('status', $exhibitor->status) === $key)>{{ $label }}</option>@endforeach
        </select></div>
    <div class="col-md-3"><label class="form-label" for="sort">Ordre</label><input id="sort" type="number" min="0" name="sort" class="form-control" value="{{ old('sort', $exhibitor->sort ?? 0) }}"></div>
    <div class="col-md-3 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" @checked(old('featured', $exhibitor->featured))><label class="form-check-label" for="featured">À la une</label></div></div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.exhibitors.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

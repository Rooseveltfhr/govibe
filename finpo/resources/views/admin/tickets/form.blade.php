@extends('layouts.admin', ['title' => $category->exists ? 'Modifier la catégorie' : 'Nouvelle catégorie'])

@section('content')
<h1 class="h3 mb-4">{{ $category->exists ? 'Modifier « '.$category->name.' »' : 'Nouvelle catégorie de billet' }}</h1>
<form method="post" action="{{ $category->exists ? route('admin.tickets.update', $category) : route('admin.tickets.store') }}" class="fp-card p-4 row g-3" style="max-width: 900px;">
    @csrf
    @if ($category->exists) @method('PUT') @endif
    <div class="col-md-6">
        <label class="form-label" for="name">Nom *</label>
        <input id="name" name="name" class="form-control" required value="{{ old('name', $category->name) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="audience">Catégorie de participant *</label>
        <select id="audience" name="audience" class="form-select" required>
            @foreach (config('finpo.attendee_categories') as $key => $meta)
                <option value="{{ $key }}" @selected(old('audience', $category->audience) === $key)>{{ $meta['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="price">Prix (0 = gratuit) *</label>
        <input id="price" type="number" min="0" name="price" class="form-control" required value="{{ old('price', $category->price) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="currency">Devise *</label>
        <input id="currency" name="currency" class="form-control" maxlength="3" required value="{{ old('currency', $category->currency ?? 'HTG') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="quota">Quota (vide = illimité)</label>
        <input id="quota" type="number" min="1" name="quota" class="form-control" value="{{ old('quota', $category->quota) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="color">Couleur badge *</label>
        <input id="color" type="color" name="color" class="form-control form-control-color w-100" value="{{ old('color', $category->color ?? '#e8b931') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="sales_start">Début des ventes</label>
        <input id="sales_start" type="datetime-local" name="sales_start" class="form-control" value="{{ old('sales_start', $category->sales_start?->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="sales_end">Fin des ventes</label>
        <input id="sales_end" type="datetime-local" name="sales_end" class="form-control" value="{{ old('sales_end', $category->sales_end?->format('Y-m-d\TH:i')) }}">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" rows="2" class="form-control">{{ old('description', $category->description) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label" for="benefits">Avantages (un par ligne)</label>
        <textarea id="benefits" name="benefits" rows="4" class="form-control">{{ old('benefits', implode("\n", $category->benefits ?? [])) }}</textarea>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="sort">Ordre</label>
        <input id="sort" type="number" min="0" name="sort" class="form-control" value="{{ old('sort', $category->sort ?? 0) }}">
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="active" name="active" value="1" @checked(old('active', $category->active ?? true))>
            <label class="form-check-label" for="active">Actif</label>
        </div>
    </div>
    <div class="col-12">
        <button class="btn btn-fp-primary">Enregistrer</button>
        <a href="{{ route('admin.tickets.index') }}" class="btn btn-fp-outline">Annuler</a>
    </div>
</form>
@endsection

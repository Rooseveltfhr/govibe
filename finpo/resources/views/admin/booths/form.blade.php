@extends('layouts.admin', ['title' => 'Stand'])

@section('content')
<h1 class="h3 mb-4">{{ $booth->exists ? 'Modifier '.$booth->code : 'Nouveau stand' }}</h1>
<form method="post" action="{{ $booth->exists ? route('admin.booths.update', $booth) : route('admin.booths.store') }}" class="fp-card p-4 row g-3" style="max-width: 560px;">
    @csrf
    @if ($booth->exists) @method('PUT') @endif
    <div class="col-md-4"><label class="form-label" for="code">Code *</label><input id="code" name="code" class="form-control" required value="{{ old('code', $booth->code) }}" placeholder="A-01"></div>
    <div class="col-md-4"><label class="form-label" for="zone">Zone *</label><input id="zone" name="zone" class="form-control" required value="{{ old('zone', $booth->zone) }}"></div>
    <div class="col-md-4"><label class="form-label" for="size">Taille *</label><input id="size" name="size" class="form-control" required value="{{ old('size', $booth->size) }}" placeholder="3x3"></div>
    <div class="col-md-6"><label class="form-label" for="price">Prix (HTG) *</label><input id="price" type="number" min="0" name="price" class="form-control" required value="{{ old('price', $booth->price) }}"></div>
    <div class="col-md-6"><label class="form-label" for="status">Statut *</label>
        <select id="status" name="status" class="form-select">
            @foreach (['available' => 'Disponible', 'reserved' => 'Réservé', 'sold' => 'Vendu'] as $key => $label)<option value="{{ $key }}" @selected(old('status', $booth->status) === $key)>{{ $label }}</option>@endforeach
        </select></div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.booths.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

@extends('layouts.admin', ['title' => 'Code promo'])

@section('content')
<h1 class="h3 mb-4">{{ $coupon->exists ? 'Modifier '.$coupon->code : 'Nouveau code promo' }}</h1>
<form method="post" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="fp-card p-4 row g-3" style="max-width: 640px;">
    @csrf
    @if ($coupon->exists) @method('PUT') @endif
    <div class="col-md-6"><label class="form-label" for="code">Code *</label><input id="code" name="code" class="form-control text-uppercase" required value="{{ old('code', $coupon->code) }}"></div>
    <div class="col-md-3"><label class="form-label" for="type">Type *</label>
        <select id="type" name="type" class="form-select"><option value="percent" @selected(old('type', $coupon->type) === 'percent')>%</option><option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>Montant HTG</option></select></div>
    <div class="col-md-3"><label class="form-label" for="value">Valeur *</label><input id="value" type="number" min="1" name="value" class="form-control" required value="{{ old('value', $coupon->value) }}"></div>
    <div class="col-md-4"><label class="form-label" for="max_uses">Max utilisations</label><input id="max_uses" type="number" min="1" name="max_uses" class="form-control" value="{{ old('max_uses', $coupon->max_uses) }}"></div>
    <div class="col-md-4"><label class="form-label" for="expires_at">Expire le</label><input id="expires_at" type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d\TH:i')) }}"></div>
    <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="active" name="active" value="1" @checked(old('active', $coupon->active ?? true))><label class="form-check-label" for="active">Actif</label></div></div>
    <div class="col-12"><button class="btn btn-fp-primary">Enregistrer</button> <a href="{{ route('admin.coupons.index') }}" class="btn btn-fp-outline">Annuler</a></div>
</form>
@endsection

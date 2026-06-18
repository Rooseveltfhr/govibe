{{-- TAGTOA LOYALTY — Dashboard : créer / modifier un programme + récompenses.
     ADAPTER @extends au layout admin du projet (Bootstrap). --}}
@extends('layouts.app')

@section('content')
@php $editing = $program->exists; @endphp
<div class="container py-4" style="max-width:760px">
    <a href="{{ route('tagtoa.loyalty.dashboard.index') }}" class="text-decoration-none small">
        <i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}
    </a>
    <h4 class="my-3 fw-bold" style="font-family:'Space Grotesk',sans-serif">
        {{ $editing ? __('Modifier le programme') : __('Nouveau programme') }}
    </h4>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('tagtoa.loyalty.dashboard.update', $program->id) : route('tagtoa.loyalty.dashboard.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card border-0 shadow-sm mb-3" style="border-radius:16px">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">{{ __('Nom du programme') }} *</label>
                    <input name="name" class="form-control" required value="{{ old('name', $program->name) }}"
                           placeholder="{{ __('TAGTOA Fidélité') }}">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">{{ __('Vcard (optionnel)') }}</label>
                        <select name="vcard_id" class="form-select">
                            <option value="">{{ __('— Aucun —') }}</option>
                            @foreach($vcards as $v)
                                <option value="{{ $v->id }}" @selected(old('vcard_id', $program->vcard_id) == $v->id)>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">{{ __('Devise') }}</label>
                        <select name="currency" class="form-select">
                            @foreach(['HTG','USD'] as $c)
                                <option value="{{ $c }}" @selected(old('currency', $program->currency ?: 'HTG') == $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">{{ __('Points par unité monétaire') }}</label>
                        <input name="points_per_dollar" type="number" step="0.01" class="form-control"
                               value="{{ old('points_per_dollar', $program->points_per_dollar ?? 1) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">{{ __('Valeur d\'un point') }}</label>
                        <input name="dollar_per_point" type="number" step="0.0001" class="form-control"
                               value="{{ old('dollar_per_point', $program->dollar_per_point ?? 0.01) }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">{{ __('Logo') }}</label>
                    <input type="file" name="logo" accept="image/*" class="form-control">
                    @if($editing && $program->logo_url)<img src="{{ $program->logo_url }}" class="mt-2" style="height:48px;border-radius:8px">@endif
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">{{ __('Description') }}</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $program->description) }}</textarea>
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act"
                           @checked(old('is_active', $program->is_active ?? true))>
                    <label class="form-check-label" for="act">{{ __('Programme actif') }}</label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100" style="background:#0055FF;border:0;padding:12px">
            <i class="fa-solid fa-floppy-disk me-1"></i> {{ __('Enregistrer') }}
        </button>
    </form>

    {{-- Récompenses (uniquement en édition) --}}
    @if($editing)
        <h6 class="fw-bold mt-4 mb-2">{{ __('Récompenses') }}</h6>
        @forelse($program->rewards as $rw)
            <div class="d-flex align-items-center gap-2 border rounded p-2 mb-2">
                <i class="fa-solid fa-gift text-primary"></i>
                <div class="flex-grow-1"><b>{{ $rw->name }}</b> <span class="text-muted small">— {{ $rw->discount_label }}</span></div>
                <span class="badge bg-light text-dark">{{ $rw->points_required }} pts</span>
                <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.rewards.destroy', $rw->id) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                </form>
            </div>
        @empty
            <p class="text-muted small">{{ __('Aucune récompense.') }}</p>
        @endforelse

        <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.rewards.store', $program->id) }}"
              class="row g-2 align-items-end mt-2">
            @csrf
            <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="{{ __('Nom récompense') }}" required></div>
            <div class="col-md-2"><input name="points_required" type="number" class="form-control form-control-sm" placeholder="Points" required></div>
            <div class="col-md-2"><input name="discount_value" type="number" step="0.01" class="form-control form-control-sm" placeholder="Valeur"></div>
            <div class="col-md-2">
                <select name="discount_type" class="form-select form-select-sm">
                    <option value="fixed">{{ __('Fixe') }}</option>
                    <option value="percent">%</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-dark w-100"><i class="fa-solid fa-plus"></i></button></div>
        </form>
    @endif
</div>
@endsection

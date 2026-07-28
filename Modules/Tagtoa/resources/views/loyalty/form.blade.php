@extends('tagtoa::layouts.dashboard')
@php $editing = $program->exists; @endphp
@section('title', $editing ? __('Modifier le programme') : __('Nouveau programme'))
@section('page', $editing ? __('Modifier le programme') : __('Nouveau programme'))

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.loyalty.dashboard.update',$program->id) : route('tagtoa.loyalty.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif
    <div class="card">
        <label class="lbl">{{ __('Nom du programme') }} *</label>
        <input class="inp" name="name" required value="{{ old('name',$program->name) }}" placeholder="{{ __('TAGTOA Fidélité') }}">
        <div class="row">
            <div><label class="lbl">{{ __('Points par unité') }}</label><input class="inp" type="number" step="0.01" name="points_per_dollar" value="{{ old('points_per_dollar',$program->points_per_dollar ?? 1) }}"></div>
            <div><label class="lbl">{{ __('Valeur d\'un point') }}</label><input class="inp" type="number" step="0.0001" name="dollar_per_point" value="{{ old('dollar_per_point',$program->dollar_per_point ?? 0.01) }}"></div>
            <div><label class="lbl">{{ __('Devise') }}</label><select class="sel" name="currency">@foreach(['HTG','USD'] as $c)<option @selected(old('currency',$program->currency ?: 'HTG')===$c)>{{ $c }}</option>@endforeach</select></div>
        </div>
        <label class="lbl">{{ __('Logo') }}</label><input class="inp" type="file" name="logo" accept="image/*">
        @if($editing && $program->logo_url)<img src="{{ $program->logo_url }}" style="height:46px;border-radius:8px;margin-top:8px">@endif
        <label class="lbl">{{ __('Description') }}</label><textarea class="inp" name="description" rows="2">{{ old('description',$program->description) }}</textarea>
        <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$program->is_active ?? true))> {{ __('Programme actif') }}</label>
    </div>
    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

@if($editing)
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Récompenses') }}</h2></div>
    @forelse($program->rewards as $rw)
        <div style="display:flex;align-items:center;gap:10px;border:1px solid var(--bd);border-radius:10px;padding:10px;margin-bottom:8px">
            <i class="fa-solid fa-gift" style="color:var(--blue)"></i>
            <div style="flex:1"><b>{{ $rw->name }}</b> <span style="color:var(--muted);font-size:13px">— {{ $rw->discount_label }}</span></div>
            <span class="pill n">{{ $rw->points_required }} pts</span>
        </div>
    @empty
        <p style="color:var(--muted);font-size:14px">{{ __('Aucune récompense.') }}</p>
    @endforelse
    <form method="POST" action="{{ route('tagtoa.loyalty.dashboard.rewards.store',$program->id) }}" class="row" style="margin-top:10px;align-items:flex-end">
        @csrf
        <div style="flex:2"><label class="lbl">{{ __('Nom') }}</label><input class="inp" name="name" required></div>
        <div><label class="lbl">{{ __('Points') }}</label><input class="inp" type="number" name="points_required" required></div>
        <div><label class="lbl">{{ __('Valeur') }}</label><input class="inp" type="number" step="0.01" name="discount_value"></div>
        <div><label class="lbl">{{ __('Type') }}</label><select class="sel" name="discount_type"><option value="fixed">{{ __('Fixe') }}</option><option value="percent">%</option></select></div>
        <button class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-plus"></i></button>
    </form>
</div>
@endif
@endsection

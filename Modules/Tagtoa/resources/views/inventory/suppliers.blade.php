@extends('tagtoa::layouts.dashboard')
@section('title', __('Fournisseurs'))
@section('page', __('Vos fournisseurs'))

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.inventory.index') }}" style="color:var(--muted);font-size:14px">
        <i class="fa-solid fa-arrow-left"></i> {{ __('Retour au stock') }}
    </a>
</div>

<div class="card" style="border-left:4px solid #2cb809">
    <b style="font-family:var(--fh,sans-serif)"><i class="fa-solid fa-circle-info" style="color:#2cb809"></i> {{ __('À quoi ça sert') }}</b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:72ch">
        {{ __('Le numéro à rappeler quand un article baisse, au même endroit que le stock — pas dans un téléphone qui peut se perdre.') }}
        {{ __('Chaque réception que vous enregistrez est rattachée à son fournisseur : vous saurez combien vous achetez chez qui.') }}
    </p>
</div>

{{-- ---------- Nouveau fournisseur ---------- --}}
<div class="card">
    <div class="h-row"><h2>{{ __('Ajouter un fournisseur') }}</h2></div>
    <form method="POST" action="{{ route('tagtoa.inventory.suppliers.store') }}">
        @csrf
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <label style="font-size:12px;color:var(--muted);flex:1;min-width:180px">{{ __('Nom') }}
                <input name="name" class="inp" maxlength="160" required placeholder="{{ __('ex. Dépôt Bon Prix') }}">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Personne à demander') }}
                <input name="contact_name" class="inp" maxlength="120" style="max-width:190px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('WhatsApp') }}
                <input name="whatsapp" class="inp" maxlength="40" style="max-width:160px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Téléphone') }}
                <input name="phone" class="inp" maxlength="40" style="max-width:160px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Adresse') }}
                <input name="address" class="inp" maxlength="240" style="max-width:230px">
            </label>
            <label style="font-size:12px;color:var(--muted);flex:1;min-width:200px">{{ __('Notes') }}
                <input name="notes" class="inp" maxlength="2000" placeholder="{{ __('ex. livre le mardi, paiement à 15 jours') }}">
            </label>
        </div>
        <button class="btn btn-p" style="margin-top:14px">
            <i class="fa-solid fa-plus"></i> {{ __('Enregistrer') }}
        </button>
    </form>
</div>

{{-- ---------- L'annuaire ---------- --}}
<div class="h-row" style="margin-top:24px">
    <h2>{{ __('Enregistrés') }} <span style="color:var(--muted);font-weight:400">({{ $suppliers->count() }})</span></h2>
</div>

@if($suppliers->isEmpty())
    <div class="card" style="text-align:center;color:var(--muted);padding:34px 16px">
        <i class="fa-solid fa-truck-field" style="font-size:30px;opacity:.35"></i>
        <p style="margin-top:10px">{{ __('Aucun fournisseur pour le moment.') }}</p>
    </div>
@else
    @foreach($suppliers as $f)
    <div class="card" style="{{ $f->is_active ? '' : 'opacity:.6' }}">
        <form method="POST" action="{{ route('tagtoa.inventory.suppliers.update', $f->id) }}">
            @csrf @method('PUT')
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <label style="font-size:12px;color:var(--muted);flex:1;min-width:180px">{{ __('Nom') }}
                    <input name="name" class="inp" maxlength="160" value="{{ $f->name }}" required>
                </label>
                <label style="font-size:12px;color:var(--muted)">{{ __('Personne à demander') }}
                    <input name="contact_name" class="inp" maxlength="120" value="{{ $f->contact_name }}" style="max-width:190px">
                </label>
                <label style="font-size:12px;color:var(--muted)">{{ __('WhatsApp') }}
                    <input name="whatsapp" class="inp" maxlength="40" value="{{ $f->whatsapp }}" style="max-width:160px">
                </label>
                <label style="font-size:12px;color:var(--muted)">{{ __('Téléphone') }}
                    <input name="phone" class="inp" maxlength="40" value="{{ $f->phone }}" style="max-width:160px">
                </label>
                <label style="font-size:12px;color:var(--muted)">{{ __('Adresse') }}
                    <input name="address" class="inp" maxlength="240" value="{{ $f->address }}" style="max-width:230px">
                </label>
                <label style="font-size:12px;color:var(--muted);flex:1;min-width:200px">{{ __('Notes') }}
                    <input name="notes" class="inp" maxlength="2000" value="{{ $f->notes }}">
                </label>
            </div>

            <div style="display:flex;gap:8px;align-items:center;margin-top:12px;flex-wrap:wrap">
                <button class="btn btn-d btn-sm"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
                @if($f->reach)
                    <a class="btn btn-o btn-sm" href="https://wa.me/{{ preg_replace('/\D/', '', $f->reach) }}" target="_blank" rel="noopener">
                        <i class="fa-brands fa-whatsapp"></i> {{ $f->reach }}
                    </a>
                @endif
                <span style="flex:1"></span>
                @if(! $f->is_active)
                    <span style="color:var(--muted);font-size:12px">{{ __('Archivé') }}</span>
                @endif
            </div>
        </form>

        {{-- Archiver, pas supprimer : l'historique des réceptions désigne
             encore ce fournisseur. --}}
        <form method="POST" action="{{ route('tagtoa.inventory.suppliers.toggle', $f->id) }}" style="margin-top:8px">
            @csrf
            <button class="btn btn-o btn-sm" style="color:{{ $f->is_active ? 'var(--red)' : '#2cb809' }}">
                <i class="fa-solid fa-box-archive"></i>
                {{ $f->is_active ? __('Archiver') : __('Réactiver') }}
            </button>
        </form>
    </div>
    @endforeach
@endif
@endsection

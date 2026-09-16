@extends('tagtoa::layouts.dashboard')
@section('title', __('Paramètres'))
@section('page', __('Paramètres de la caisse'))

@push('head')
<style>
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;align-items:end}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
.poste + .poste{border-top:1px solid var(--bd);margin-top:14px;padding-top:14px}
.ailleurs{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
.ailleurs a{display:flex;align-items:center;gap:11px;padding:13px 14px;border-radius:13px;
            border:1px solid var(--bd);background:#fff;color:var(--blk)}
.ailleurs a i{font-size:17px;color:var(--blue-deep);width:20px;text-align:center}
.ailleurs a b{font:700 13.5px var(--fh);display:block}
.ailleurs a span{font-size:12px;color:var(--muted)}
</style>
@endpush

@section('content')
<div class="card">
    <div class="h-row" style="margin-bottom:6px"><h2>{{ __('Type d\'activité') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:12px">
        {{ __('La caisse s\'adapte à votre commerce : pharmacie, bar, boutique… Ce choix change par exemple les unités proposées quand vous ajoutez un produit (comprimé, plaquette pour une pharmacie ; bouteille, verre, shot pour un bar).') }}
    </p>
    <form method="POST" action="{{ route('tagtoa.pos.settings.business-type') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        @csrf @method('PUT')
        <div style="min-width:220px">
            <label class="lbl" style="display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em">{{ __('Activité') }}</label>
            <select class="ic" name="type" required>
                @foreach($types as $code => $meta)
                    <option value="{{ $code }}" @selected(optional($business)->type === $code)>{{ __($meta['label']) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Enregistrer') }}</button>
    </form>
</div>

<div class="card">
    <div class="h-row" style="margin-bottom:6px"><h2>{{ __('Vos postes de caisse') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:12px">
        {{ __('Un poste, c\'est un appareil qui encaisse. Le catalogue, lui, est celui du commerce : tous les postes vendent les mêmes articles.') }}
    </p>

    @foreach($terminals as $t)
        <form method="POST" action="{{ route('tagtoa.pos.settings.update', $t->id) }}" class="poste">
            @csrf @method('PUT')
            <div class="pf">
                <div style="grid-column:span 2">
                    <label>{{ __('Nom du poste') }}</label>
                    <input class="ic" name="name" value="{{ $t->name }}" maxlength="120" required>
                </div>
                <div>
                    <label>{{ __('Devise') }}</label>
                    <select class="ic" name="currency">
                        @foreach($currencies as $code => $label)
                            <option value="{{ $code }}" @selected($t->currency === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;margin-top:11px;flex-wrap:wrap">
                <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Enregistrer') }}</button>
                <label style="display:inline-flex;align-items:center;gap:7px;font:600 12.5px var(--fh);color:var(--muted)">
                    <input type="checkbox" name="is_active" value="1" @checked($t->is_active)> {{ __('En service') }}
                </label>
                <span style="font-size:12.5px;color:var(--muted)">
                    {{ trans_choice('{0}aucun article|{1}:count article|[2,*]:count articles', $t->products_count, ['count' => $t->products_count]) }}
                </span>
                <a class="btn btn-o btn-sm" href="{{ route('tagtoa.pos.register', $t->id) }}">
                    <i class="fa-solid fa-cash-register"></i> {{ __('Ouvrir') }}
                </a>
            </div>
        </form>
    @endforeach
</div>

<div class="card">
    <div class="h-row" style="margin-bottom:6px"><h2>{{ __('Réglages du commerce') }}</h2></div>
    {{-- Ces réglages appartiennent au COMMERCE, pas à un poste. Les recopier ici
         donnerait deux endroits pour un seul réglage — et le jour où ils
         divergent, deux caisses délivrent des reçus avec des taxes
         différentes. On mène donc à l'écran qui en est propriétaire. --}}
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:12px">
        {{ __('Ils valent pour toutes vos caisses à la fois.') }}
    </p>
    <div class="ailleurs">
        <a href="{{ route('tagtoa.business.index') }}">
            <i class="fa-solid fa-percent"></i>
            <span><b>{{ __('Taxe (TCA / TVA)') }}</b><span>{{ __('Taux, libellé, numéro fiscal') }}</span></span>
        </a>
        <a href="{{ route('tagtoa.business.index') }}">
            <i class="fa-solid fa-receipt"></i>
            <span><b>{{ __('Mot en bas des reçus') }}</b><span>{{ __('« Merci… », « pas de retour »') }}</span></span>
        </a>
        <a href="{{ route('tagtoa.pay.methods') }}">
            <i class="fa-solid fa-credit-card"></i>
            <span><b>{{ __('Moyens de paiement') }}</b><span>{{ __('Ce que vous acceptez') }}</span></span>
        </a>
        <a href="{{ route('tagtoa.staff.index') }}">
            <i class="fa-solid fa-users-gear"></i>
            <span><b>{{ __('Caissiers') }}</b><span>{{ __('Codes d\'accès et droits') }}</span></span>
        </a>
        <a href="{{ route('tagtoa.inventory.suppliers') }}">
            <i class="fa-solid fa-truck-field"></i>
            <span><b>{{ __('Fournisseurs') }}</b><span>{{ __('Chez qui vous achetez') }}</span></span>
        </a>
    </div>
</div>
@endsection

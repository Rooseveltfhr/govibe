@extends('tagtoa::layouts.dashboard')
@section('title', __('Boutique TAGTOA'))
@section('page', __('Boutique TAGTOA'))

@push('head')
<style>
[hidden]{display:none!important}
.mat{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}
.art{background:var(--surface);border:1px solid var(--bd);border-radius:16px;overflow:hidden;display:flex;flex-direction:column}
.art .ph{width:100%;aspect-ratio:4/3;object-fit:cover;display:flex;align-items:center;justify-content:center;
         background:var(--blue-pale);color:var(--blue-deep);font-size:34px}
.art .bd{padding:13px 14px;display:flex;flex-direction:column;flex:1}
.art b{font:700 15px var(--fh)}
.art .ds{font-size:12.5px;color:var(--muted);margin-top:4px;line-height:1.45;
         display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.9em}
.art .lot{font-size:11.5px;color:var(--muted);margin-top:8px}
.art .ft{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:10px}
.art .pr{font:700 16px var(--fh)}
.pan{position:sticky;bottom:0}
.lg{display:flex;gap:10px;align-items:center;padding:10px 0}
.lg + .lg{border-top:1px solid var(--bd)}
.lg .n{flex:1;min-width:0}
.lg input{width:88px;padding:8px 10px;border:1.5px solid var(--bd);border-radius:9px;font:15px var(--fb);text-align:right}
</style>
@endpush

@section('content')
<div class="card" style="border-left:4px solid #2cb809">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-truck-fast" style="color:#2cb809"></i> {{ __('Le matériel TAGTOA, commandé depuis votre tableau de bord') }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:74ch">
        {{ __('Vous commandez ici ; TAGTOA vous confirme le transport et le délai avant tout paiement.') }}
        {{ __('Le transport dépend d\'où vous êtes — il n\'est donc pas chiffré au moment du clic.') }}
    </p>
</div>

<form method="POST" action="{{ route('tagtoa.shop.cart') }}">
    @csrf
    <div class="mat">
        @forelse($items as $it)
            <div class="art">
                @if($it->image_url)
                    <img class="ph" src="{{ $it->image_url }}" alt="{{ $it->name }}" loading="lazy">
                @else
                    <span class="ph"><i class="fa-solid fa-box-open"></i></span>
                @endif
                <div class="bd">
                    <b>{{ $it->name }}</b>
                    <div class="ds">{{ $it->description }}</div>
                    <div class="lot">
                        {{-- Ce qu'on sait RÉELLEMENT expédier : le dire ici évite
                             une quantité corrigée en silence à la validation. --}}
                        @if($it->min_qty > 1){{ __('Minimum :n', ['n' => $it->min_qty]) }}@endif
                        @if($it->step_qty > 1) · {{ __('par :n', ['n' => $it->step_qty]) }}@endif
                        @if($it->lead_time_days) · {{ __('délai ~:n j', ['n' => $it->lead_time_days]) }}@endif
                    </div>
                    <div class="ft">
                        <span class="pr">{{ \Modules\Tagtoa\App\Support\Money::format($it->unit_price, $chiffre['devise']) }}</span>
                        <button class="btn btn-p btn-sm" name="add" value="{{ $it->id }}">
                            <i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="empty" style="grid-column:1/-1;padding:40px 16px">
                <i class="fa-solid fa-box-open"></i>
                {{ __('La boutique n\'a pas encore d\'articles.') }}
            </div>
        @endforelse
    </div>
</form>

@if($chiffre['lignes'])
<div class="card pan" style="margin-top:18px">
    <div class="h-row"><h2>{{ __('Votre panier') }}</h2></div>
    <form method="POST" action="{{ route('tagtoa.shop.cart') }}">
        @csrf
        @foreach($chiffre['lignes'] as $l)
            <div class="lg">
                <span class="n">
                    <b style="font:700 14.5px var(--fh)">{{ $l['item']->name }}</b>
                    <span style="display:block;font-size:12.5px;color:var(--muted)">
                        {{ \Modules\Tagtoa\App\Support\Money::format($l['item']->unit_price, $chiffre['devise']) }}
                        @if($l['corrigee'])
                            · <span style="color:var(--amber)">{{ __('ajusté au lot livrable') }}</span>
                        @endif
                    </span>
                </span>
                <input type="number" name="qty[{{ $l['item']->id }}]" value="{{ $l['qty'] }}"
                       min="0" step="{{ $l['item']->step_qty }}" aria-label="{{ __('Quantité') }}">
                <span style="font:700 14.5px var(--fh);min-width:84px;text-align:right">
                    {{ \Modules\Tagtoa\App\Support\Money::format($l['line_total'], $chiffre['devise']) }}
                </span>
            </div>
        @endforeach

        <div style="display:flex;align-items:center;gap:12px;margin-top:14px;flex-wrap:wrap">
            <button class="btn btn-o btn-sm"><i class="fa-solid fa-rotate"></i> {{ __('Mettre à jour') }}</button>
            <span style="flex:1"></span>
            <span style="font:700 18px var(--ft)">
                {{ \Modules\Tagtoa\App\Support\Money::format($chiffre['subtotal'], $chiffre['devise']) }}
            </span>
        </div>
    </form>
    <p style="color:var(--muted);font-size:12.5px;margin-top:8px">
        {{ __('Transport non compris — TAGTOA le chiffre en confirmant.') }}
    </p>
    <a class="btn btn-p" href="{{ route('tagtoa.shop.checkout') }}" style="margin-top:10px">
        <i class="fa-solid fa-arrow-right"></i> {{ __('Continuer') }}
    </a>
</div>
@endif

<div style="margin-top:16px;text-align:center">
    <a class="btn btn-o" href="{{ route('tagtoa.shop.orders') }}">
        <i class="fa-solid fa-clipboard-list"></i> {{ __('Mes commandes') }}
    </a>
</div>
@endsection

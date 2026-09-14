@extends('tagtoa::layouts.dashboard')
@section('title', __('Boutique — fondateur'))
@section('page', __('Boutique TAGTOA'))

@push('head')
<style>
[hidden]{display:none!important}
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;align-items:end}
.pf .w2{grid-column:span 2}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
.art{display:flex;gap:11px;align-items:center;padding:11px 0}
.art + .art{border-top:1px solid var(--bd)}
.art .vg{width:46px;height:46px;border-radius:11px;flex:0 0 46px;object-fit:cover;display:flex;
         align-items:center;justify-content:center;background:var(--blue-pale);color:var(--blue-deep)}
.art .corps{flex:1;min-width:0}
.cmd{padding:13px 0}
.cmd + .cmd{border-top:1px solid var(--bd)}
</style>
@endpush

@section('content')
<div class="card" style="border-left:4px solid var(--amber)">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-shield-halved" style="color:var(--amber)"></i> {{ __('Vue plateforme') }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:74ch">
        {{-- Cet écran voit les commandes de TOUS les commerces — c'est sa raison
             d'être, puisque c'est TAGTOA qui expédie. Le dire à l'écran évite
             qu'on l'oublie. --}}
        {{ __('Cet écran montre les commandes de tous les commerces. Vous pouvez fixer le transport et faire avancer le statut — pas retoucher les lignes : une commande fausse s\'annule et se repasse.') }}
    </p>
</div>

{{-- ══ LE CATALOGUE ══════════════════════════════════════════════════════ --}}
<form method="POST" action="{{ route('tagtoa.superadmin.shop.items') }}"
      enctype="multipart/form-data" class="card">
    @csrf
    <div class="h-row" style="margin-bottom:10px"><h2>{{ __('Ajouter un article') }}</h2></div>
    <div class="pf">
        <div><label for="sku">{{ __('Référence') }}</label>
            <input class="ic" id="sku" name="sku" required maxlength="40" placeholder="STAND-A5"></div>
        <div class="w2"><label for="nm">{{ __('Nom') }}</label>
            <input class="ic" id="nm" name="name" required maxlength="120" placeholder="{{ __('Smart Stand A5') }}"></div>
        <div><label for="pp">{{ __('Prix unitaire') }}</label>
            <input class="ic" id="pp" name="unit_price" type="number" step="0.01" min="0" required></div>
        <div><label for="mq">{{ __('Minimum') }}</label>
            <input class="ic" id="mq" name="min_qty" type="number" min="1" value="1"></div>
        <div><label for="sq">{{ __('Par lots de') }}</label>
            <input class="ic" id="sq" name="step_qty" type="number" min="1" value="1"></div>
        <div><label for="lt">{{ __('Délai (jours)') }}</label>
            <input class="ic" id="lt" name="lead_time_days" type="number" min="0"></div>
        <div class="w2"><label for="ds">{{ __('Description') }}</label>
            <input class="ic" id="ds" name="description" maxlength="255"></div>
        <div><label for="im">{{ __('Photo') }}</label>
            <input class="ic" id="im" name="image" type="file" accept="image/*" style="padding:6px"></div>
    </div>
    <button class="btn btn-p" style="margin-top:12px"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
</form>

<div class="card">
    <div class="h-row"><h2>{{ __('Articles') }} <span style="color:var(--muted);font-weight:400">({{ $items->count() }})</span></h2></div>
    @forelse($items as $it)
        <div class="art">
            @if($it->image_url)<img class="vg" src="{{ $it->image_url }}" alt="">
            @else<span class="vg"><i class="fa-solid fa-box"></i></span>@endif
            <span class="corps">
                <b style="font:700 14.5px var(--fh);display:block">{{ $it->name }}</b>
                <span style="font-size:12.5px;color:var(--muted)">
                    {{ $it->sku }} ·
                    {{ \Modules\Tagtoa\App\Support\Money::format($it->unit_price, config('tagtoa.shop_currency', 'USD')) }}
                    @if($it->min_qty > 1) · min {{ $it->min_qty }}@endif
                    @if($it->step_qty > 1) · ×{{ $it->step_qty }}@endif
                    @unless($it->is_active) · {{ __('masqué') }} @endunless
                </span>
            </span>
            <button type="button" class="btn btn-o btn-sm modifier"><i class="fa-solid fa-pen"></i></button>
        </div>
        <form method="POST" action="{{ route('tagtoa.superadmin.shop.item.update', $it->id) }}"
              enctype="multipart/form-data" class="edition" hidden style="padding:4px 0 16px 57px">
            @csrf @method('PUT')
            <div class="pf">
                <div><label>{{ __('Référence') }}</label><input class="ic" name="sku" value="{{ $it->sku }}" required maxlength="40"></div>
                <div class="w2"><label>{{ __('Nom') }}</label><input class="ic" name="name" value="{{ $it->name }}" required maxlength="120"></div>
                <div><label>{{ __('Prix') }}</label><input class="ic" name="unit_price" type="number" step="0.01" min="0" value="{{ $it->unit_price }}" required></div>
                <div><label>{{ __('Minimum') }}</label><input class="ic" name="min_qty" type="number" min="1" value="{{ $it->min_qty }}"></div>
                <div><label>{{ __('Par lots de') }}</label><input class="ic" name="step_qty" type="number" min="1" value="{{ $it->step_qty }}"></div>
                <div><label>{{ __('Délai (j)') }}</label><input class="ic" name="lead_time_days" type="number" min="0" value="{{ $it->lead_time_days }}"></div>
                <div class="w2"><label>{{ __('Description') }}</label><input class="ic" name="description" value="{{ $it->description }}" maxlength="255"></div>
                <div><label>{{ __('Photo') }}</label><input class="ic" name="image" type="file" accept="image/*" style="padding:6px"></div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;margin-top:11px;flex-wrap:wrap">
                <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Enregistrer') }}</button>
                <button type="button" class="btn btn-o btn-sm annuler">{{ __('Annuler') }}</button>
                <label style="display:inline-flex;align-items:center;gap:7px;font:600 12.5px var(--fh);color:var(--muted)">
                    <input type="checkbox" name="is_active" value="1" @checked($it->is_active)> {{ __('En vente') }}
                </label>
            </div>
        </form>
    @empty
        <div class="empty" style="padding:26px 16px"><i class="fa-solid fa-box-open"></i> {{ __('Aucun article.') }}</div>
    @endforelse
</div>

{{-- ══ LES COMMANDES REÇUES ══════════════════════════════════════════════ --}}
<div class="card">
    <div class="h-row"><h2>{{ __('Commandes reçues') }}</h2></div>
    @forelse($orders as $o)
        <div class="cmd">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <b style="font:700 14.5px var(--fh)">{{ strtoupper(substr($o->reference, 0, 8)) }}</b>
                <span class="pill a">{{ __($o->status_label) }}</span>
                <span style="color:var(--muted);font-size:12.5px">
                    {{ optional($o->placed_at)->format('d/m H:i') }} · {{ $o->tenant_id }}
                </span>
                <span style="flex:1"></span>
                <b>{{ \Modules\Tagtoa\App\Support\Money::format($o->total, $o->currency) }}</b>
            </div>
            <div style="font-size:12.5px;color:var(--muted);margin-top:5px">
                @foreach($o->items as $i){{ $i->name }} × {{ $i->qty }}@if(! $loop->last) · @endif @endforeach
                <br>{{ $o->contact_name }} · {{ $o->contact_phone }} · {{ $o->address }} {{ $o->city }}
                @if($o->note)<br><i>{{ $o->note }}</i>@endif
            </div>

            <form method="POST" action="{{ route('tagtoa.superadmin.shop.order.update', $o->id) }}"
                  style="margin-top:10px">
                @csrf @method('PUT')
                <div class="pf">
                    <div><label>{{ __('Statut') }}</label>
                        <select class="ic" name="status">
                            @foreach($statuts as $cle => $label)
                                <option value="{{ $cle }}" @selected($o->status === $cle)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label>{{ __('Transport') }}</label>
                        <input class="ic" name="shipping" type="number" step="0.01" min="0" value="{{ $o->shipping }}"></div>
                    <div class="w2"><label>{{ __('Réponse au marchand') }}</label>
                        <input class="ic" name="reply" value="{{ $o->reply }}" maxlength="255"
                               placeholder="{{ __('Délai, suivi, plage de stands…') }}"></div>
                    <div><label>&nbsp;</label>
                        <button class="btn btn-d btn-sm" style="width:100%">{{ __('Mettre à jour') }}</button></div>
                </div>
            </form>
        </div>
    @empty
        <div class="empty" style="padding:26px 16px">
            <i class="fa-solid fa-clipboard-list"></i> {{ __('Aucune commande reçue.') }}
        </div>
    @endforelse
    @if($orders->hasPages())<div style="margin-top:14px">{{ $orders->links() }}</div>@endif
</div>
@endsection

@push('scripts')
<script>
window.addEventListener('load', function () {
    document.querySelectorAll('.modifier').forEach(function (b) {
        b.addEventListener('click', function () {
            var f = b.closest('.art').nextElementSibling;
            f.hidden = !f.hidden;
        });
    });
    document.querySelectorAll('.annuler').forEach(function (b) {
        b.addEventListener('click', function () { b.closest('form').hidden = true; });
    });
});
</script>
@endpush

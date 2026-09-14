@extends('tagtoa::layouts.dashboard')
@section('title', __('Revendeurs'))
@section('page', __('Réseau de distribution'))

@push('head')
<style>
[hidden]{display:none!important}
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;align-items:end}
.pf .w2{grid-column:span 2}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
.rv{padding:13px 0}
.rv + .rv{border-top:1px solid var(--bd)}
</style>
@endpush

@section('content')
<div class="card" style="border-left:4px solid var(--amber)">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-lock" style="color:var(--amber)"></i> {{ __('Ni ici, ni chez le revendeur : aucun code d\'activation') }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:74ch">
        {{ __('Les codes vivent dans le fichier de frappe, produit une fois et détruit après tirage. Les remettre dans une page web les rendrait consultables depuis n\'importe quel navigateur resté ouvert.') }}
    </p>
</div>

<form method="POST" action="{{ route('tagtoa.superadmin.resellers.store') }}" class="card">
    @csrf
    <div class="h-row" style="margin-bottom:10px"><h2>{{ __('Nouveau revendeur') }}</h2></div>
    <div class="pf">
        <div class="w2"><label for="rn">{{ __('Nom') }}</label>
            <input class="ic" id="rn" name="name" required maxlength="120"></div>
        <div class="w2"><label for="rt">{{ __('Identifiant du commerce') }}</label>
            <input class="ic" id="rt" name="business_id" required maxlength="64" style="font-family:monospace"
                   placeholder="biz_..."></div>
        <div><label for="rz">{{ __('Zone') }}</label>
            <input class="ic" id="rz" name="zone" maxlength="80" placeholder="{{ __('Cap-Haïtien') }}"></div>
        <div><label for="rp">{{ __('Téléphone') }}</label>
            <input class="ic" id="rp" name="contact_phone" maxlength="40"></div>
        <div><label for="rc">{{ __('Commission %') }}</label>
            <input class="ic" id="rc" name="commission_pct" type="number" step="0.01" min="0" max="100" value="0"></div>
    </div>
    <button class="btn btn-p" style="margin-top:12px"><i class="fa-solid fa-plus"></i> {{ __('Créer') }}</button>
</form>

<div class="card">
    <div class="h-row"><h2>{{ __('Revendeurs') }} <span style="color:var(--muted);font-weight:400">({{ $resellers->count() }})</span></h2></div>

    @forelse($resellers as $r)
        @php
            $n = $compte[$r->id] ?? collect();
            $parEtat = $n->pluck('n', 'physical_state');
        @endphp
        <div class="rv">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <b style="font:700 15px var(--fh)">{{ $r->name }}</b>
                @unless($r->is_active)<span class="pill r">{{ __('désactivé') }}</span>@endunless
                <span style="color:var(--muted);font-size:12.5px">
                    {{ $r->zone }} @if($r->contact_phone) · {{ $r->contact_phone }} @endif
                    · {{ rtrim(rtrim(number_format($r->commission_pct, 2, '.', ''), '0'), '.') }}%
                </span>
                <span style="flex:1"></span>
                <span class="pill n">{{ (int) ($parEtat['allocated'] ?? 0) }} {{ __('en stock') }}</span>
                <span class="pill g">{{ (int) ($parEtat['sold'] ?? 0) }} {{ __('vendus') }}</span>
                <button type="button" class="btn btn-o btn-sm ouvrir"><i class="fa-solid fa-sliders"></i></button>
            </div>

            <div class="volet" hidden style="margin-top:12px;padding-left:4px">
                {{-- Affecter un CARTON, c'est-à-dire une plage. On n'affecte pas
                     trente stands un par un : on envoie le carton 41–80. --}}
                <form method="POST" action="{{ route('tagtoa.superadmin.resellers.allocate', $r->id) }}">
                    @csrf
                    <div class="pf">
                        <div class="w2"><label>{{ __('Lot') }}</label>
                            <select class="ic" name="batch_id">
                                @foreach($batches as $b)
                                    <option value="{{ $b->id }}">{{ $b->code }} ({{ $b->range_start }}–{{ $b->range_end }})</option>
                                @endforeach
                            </select></div>
                        <div><label>{{ __('Du n°') }}</label>
                            <input class="ic" name="from" type="number" min="1" required></div>
                        <div><label>{{ __('Au n°') }}</label>
                            <input class="ic" name="to" type="number" min="1" required></div>
                        <div><label>&nbsp;</label>
                            <button class="btn btn-d btn-sm" style="width:100%">{{ __('Affecter') }}</button></div>
                    </div>
                </form>

                <form method="POST" action="{{ route('tagtoa.superadmin.resellers.update', $r->id) }}" style="margin-top:14px">
                    @csrf @method('PUT')
                    <div class="pf">
                        <div class="w2"><label>{{ __('Nom') }}</label>
                            <input class="ic" name="name" value="{{ $r->name }}" required maxlength="120"></div>
                        <div><label>{{ __('Zone') }}</label>
                            <input class="ic" name="zone" value="{{ $r->zone }}" maxlength="80"></div>
                        <div><label>{{ __('Téléphone') }}</label>
                            <input class="ic" name="contact_phone" value="{{ $r->contact_phone }}" maxlength="40"></div>
                        <div><label>{{ __('Commission %') }}</label>
                            <input class="ic" name="commission_pct" type="number" step="0.01" min="0" max="100" value="{{ $r->commission_pct }}"></div>
                        <div class="w2"><label>{{ __('Note') }}</label>
                            <input class="ic" name="notes" value="{{ $r->notes }}" maxlength="255"></div>
                    </div>
                    <div style="display:flex;gap:12px;align-items:center;margin-top:11px;flex-wrap:wrap">
                        <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Enregistrer') }}</button>
                        <label style="display:inline-flex;align-items:center;gap:7px;font:600 12.5px var(--fh);color:var(--muted)">
                            <input type="checkbox" name="is_active" value="1" @checked($r->is_active)> {{ __('Actif') }}
                        </label>
                        <span style="font-size:12px;color:var(--muted)">
                            {{-- Le dire, parce que c'est contre-intuitif. --}}
                            {{ __('Désactiver ferme sa console ; les cartons restent chez lui.') }}
                        </span>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="empty" style="padding:30px 16px">
            <i class="fa-solid fa-truck-field"></i>
            {{ __('Aucun revendeur. TAGTOA ne peut pas livrer douze dollars de matériel à travers le pays : le réseau commence ici.') }}
        </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
window.addEventListener('load', function () {
    document.querySelectorAll('.ouvrir').forEach(function (b) {
        b.addEventListener('click', function () {
            var v = b.closest('.rv').querySelector('.volet');
            v.hidden = !v.hidden;
        });
    });
});
</script>
@endpush

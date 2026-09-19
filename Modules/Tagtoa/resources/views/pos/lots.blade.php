@extends('tagtoa::layouts.dashboard')
@section('title', __('Péremption'))
@section('page', __('Lots & péremption'))

@push('head')
<style>
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
select.ic{padding:8px 8px}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;align-items:end}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
.lot{display:flex;gap:11px;align-items:center;padding:11px 0}
.lot + .lot{border-top:1px solid var(--bd)}
.lot .corps{flex:1;min-width:0}
.lot .corps b{font:700 15px var(--fh);display:block}
.lot .corps span{font-size:13px;color:var(--muted)}
.pill{font:700 11px var(--fh);padding:3px 9px;border-radius:999px;white-space:nowrap}
.pill.perime{background:#fdecea;color:var(--red)}
.pill.bientot{background:#fff5e6;color:#7a5200}
.pill.ok{background:#eaf7ee;color:#1a7a2e}
</style>
@endpush

@section('content')
<div class="card">
    <div class="h-row" style="margin-bottom:10px"><h2>{{ __('Recevoir un lot') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:12px">
        {{ __('Deux réassorts du même article ont presque toujours deux dates de péremption différentes. Enregistrer un lot fait aussi monter le stock de l\'article, comme une réception fournisseur.') }}
    </p>
    <form method="POST" action="{{ route('tagtoa.pos.lots.store') }}">
        @csrf
        <div class="pf">
            <div style="grid-column:span 2">
                <label for="lProduit">{{ __('Article') }}</label>
                <select class="ic" id="lProduit" name="product_id" required>
                    <option value="">—</option>
                    @foreach($produits as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="lQte">{{ __('Quantité reçue') }}</label>
                <input class="ic" id="lQte" name="quantity" type="number" step="0.001" min="0.001" required>
            </div>
            <div>
                <label for="lPeremp">{{ __('Périme le') }}</label>
                <input class="ic" id="lPeremp" name="expires_at" type="date">
            </div>
            <div>
                <label for="lRecu">{{ __('Reçu le') }}</label>
                <input class="ic" id="lRecu" name="received_at" type="date" value="{{ now()->toDateString() }}">
            </div>
            <div>
                <label for="lNote">{{ __('Note') }}</label>
                <input class="ic" id="lNote" name="note" maxlength="160" placeholder="{{ __('Nº de lot, fournisseur…') }}">
            </div>
        </div>
        <button class="btn btn-p" style="margin-top:14px"><i class="fa-solid fa-plus"></i> {{ __('Enregistrer le lot') }}</button>
    </form>
</div>

<div class="card">
    <div class="h-row"><h2>{{ __('Périme bientôt') }} <span style="color:var(--muted);font-weight:400">({{ $expirant->count() }})</span></h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:6px">{{ __('Les 30 prochains jours, le plus proche d\'abord.') }}</p>

    @forelse($expirant as $l)
        @php($jours = $l->days_until_expiry)
        <div class="lot">
            <span class="corps">
                <b>{{ $l->product->name ?? __('Article supprimé') }}</b>
                <span>{{ number_format($l->quantity, $l->quantity == floor($l->quantity) ? 0 : 3) }}{{ $l->note ? ' · '.$l->note : '' }}</span>
            </span>
            <span class="pill {{ $jours < 0 ? 'perime' : ($jours <= 7 ? 'bientot' : 'ok') }}">
                @if($jours < 0)
                    {{ __('Périmé') }} — {{ $l->expires_at->format('d/m/Y') }}
                @else
                    {{ trans_choice('{0}Périme aujourd\'hui|{1}Périme dans :count jour|[2,*]Périme dans :count jours', $jours, ['count' => $jours]) }}
                @endif
            </span>
        </div>
    @empty
        <div class="empty" style="padding:24px 16px">
            <i class="fa-solid fa-calendar-check"></i>
            {{ __('Rien à surveiller pour l\'instant.') }}
        </div>
    @endforelse
</div>

<div class="card">
    <div class="h-row"><h2>{{ __('Tous les lots') }} <span style="color:var(--muted);font-weight:400">({{ $tous->count() }})</span></h2></div>
    @forelse($tous as $l)
        <div class="lot">
            <span class="corps">
                <b>{{ $l->product->name ?? __('Article supprimé') }}</b>
                <span>
                    {{ number_format($l->quantity, $l->quantity == floor($l->quantity) ? 0 : 3) }}
                    @if($l->received_at) · {{ __('reçu le :date', ['date' => $l->received_at->format('d/m/Y')]) }} @endif
                    @if($l->expires_at) · {{ __('périme le :date', ['date' => $l->expires_at->format('d/m/Y')]) }} @endif
                </span>
            </span>
        </div>
    @empty
        <div class="empty" style="padding:24px 16px">
            <i class="fa-solid fa-boxes-stacked"></i>
            {{ __('Aucun lot enregistré.') }}
        </div>
    @endforelse
</div>
@endsection

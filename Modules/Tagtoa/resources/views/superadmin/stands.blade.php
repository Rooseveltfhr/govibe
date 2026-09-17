@extends('tagtoa::layouts.dashboard')
@section('title', __('Parc de stands'))
@section('page', __('Le parc TAGTOA'))

@push('head')
<style>
[hidden]{display:none!important}
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;align-items:end}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.04em}
.filtres{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;padding-bottom:4px}
.filtres::-webkit-scrollbar{display:none}
.filtres a{flex:0 0 auto;padding:7px 13px;border-radius:999px;border:1.5px solid var(--bd);
           background:#fff;font:600 13px var(--fh);color:#4a4a4a;white-space:nowrap;text-decoration:none}
.filtres a.on{background:var(--blk);border-color:var(--blk);color:#fff}
.st{display:flex;gap:10px;align-items:center;padding:10px 0;flex-wrap:wrap}
.st + .st{border-top:1px solid var(--bd)}
.st .id{font:700 14px monospace;flex:0 0 auto}
.st .co{flex:1;min-width:120px;font-size:13px;color:var(--muted)}
.tag{border-radius:999px;padding:3px 9px;font:700 11px var(--fh);white-space:nowrap}
.lots{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}
.lots a{display:block;padding:11px 13px;border:1.5px solid var(--bd);border-radius:11px;
        background:#fff;color:var(--blk);text-decoration:none}
.lots a.on{border-color:var(--blk);background:var(--blk);color:#fff}
.lots b{font:700 13.5px var(--fh);display:block}
.lots span{font-size:11.5px;opacity:.7}
</style>
@endpush

@section('content')

<div class="card" style="border-left:4px solid var(--amber)">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-lock" style="color:var(--amber)"></i> {{ __('Aucun code d\'activation sur cette page') }}
    </b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px;max-width:74ch">
        {{ __('Les codes vivent dans le fichier de frappe, produit une fois et détruit après tirage. Les remettre dans une page web les rendrait consultables depuis n\'importe quel navigateur resté ouvert sur un comptoir.') }}
    </p>
</div>

{{-- LE CHIFFRE DU MODULE.
     Un stand MUET est un objet vendu dont personne n'a jamais gratté le
     panneau : le marchand a payé, et n'a jamais eu son compte. C'est la seule
     fuite qui ne produit aucune erreur et aucun ticket de support. --}}
<div class="grid g3" style="margin-bottom:16px">
    <div class="stat" style="border-left:4px solid var(--red)">
        <div class="v" style="color:var(--red)">{{ $parc['muets'] }}</div>
        <div class="k">{{ __('VENDUS ET MUETS') }}</div>
        <div style="color:var(--muted);font-size:11.5px;margin-top:4px">
            {{ __('payés, jamais activés — à rappeler') }}
        </div>
    </div>
    <div class="stat">
        <div class="v">{{ $parc['actifs'] }}</div><div class="k">{{ __('Actifs') }}</div>
        <div style="color:var(--muted);font-size:11.5px;margin-top:4px">
            {{ __('rattachés à un commerce') }}
        </div>
    </div>
    <div class="stat">
        <div class="v">{{ $parc['jamais_scannes'] }}</div><div class="k">{{ __('Jamais scannés') }}</div>
        <div style="color:var(--muted);font-size:11.5px;margin-top:4px">
            {{ __('activés, mais dans un tiroir') }}
        </div>
    </div>
</div>

<div class="card">
    <div class="h-row" style="margin-bottom:8px"><h2>{{ __('Les lots') }}</h2></div>
    <div class="lots">
        <a href="{{ route('tagtoa.superadmin.stands', ['vue' => $vue]) }}" class="{{ $batchId ? '' : 'on' }}">
            <b>{{ __('Tout le parc') }}</b><span>{{ $parc['total'] }} {{ __('stands') }}</span>
        </a>
        @foreach($batches as $b)
            <a href="{{ route('tagtoa.superadmin.stands', ['batch' => $b->id, 'vue' => $vue]) }}"
               class="{{ (int) $batchId === (int) $b->id ? 'on' : '' }}">
                <b>{{ $b->code }}</b>
                <span>{{ $b->range_start }}–{{ $b->range_end }}
                    @if($b->recalled_at) · {{ __('RAPPELÉ') }} @endif
                </span>
            </a>
        @endforeach
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:14px;font-size:13px">
        <span style="color:var(--muted);font:600 11px var(--fh);text-transform:uppercase;letter-spacing:.04em;width:100%">
            {{ __('L\'objet') }}
        </span>
        @foreach($etatsP as $cle => $label)
            <span>{{ $label }} : <b>{{ $parc['physique'][$cle] ?? 0 }}</b></span>
        @endforeach
        <span style="color:var(--muted);font:600 11px var(--fh);text-transform:uppercase;letter-spacing:.04em;width:100%;margin-top:6px">
            {{ __('L\'identité numérique') }}
        </span>
        @foreach($etatsN as $cle => $label)
            <span>{{ $label }} : <b>{{ $parc['numerique'][$cle] ?? 0 }}</b></span>
        @endforeach
    </div>
</div>

<div class="card">
    <div class="filtres" style="margin-bottom:10px">
        @foreach(['muets' => __('Vendus et muets'), 'jamais_scannes' => __('Jamais scannés'), 'tous' => __('Tous')] as $cle => $label)
            <a href="{{ route('tagtoa.superadmin.stands', array_filter(['batch' => $batchId, 'vue' => $cle])) }}"
               class="{{ $vue === $cle ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($liste->isEmpty())
        <p style="color:var(--muted);text-align:center;padding:26px 10px">
            {{ __('Rien dans cette vue.') }}
        </p>
    @else
        @foreach($liste as $s)
            <div class="st">
                <a class="id" href="{{ route('tagtoa.superadmin.stand', $s->public_id) }}">{{ $s->public_id }}</a>
                <span class="co">
                    @if($s->tenant_id)
                        {{ $s->tenant_id }}
                    @elseif($s->holder_type === \Modules\Tagtoa\App\Models\Stand\Stand::HOLDER_RESELLER)
                        {{ $vendeurs[$s->holder_id] ?? __('revendeur #:id', ['id' => $s->holder_id]) }}
                    @else
                        {{ __('entrepôt') }}
                    @endif
                </span>
                <span class="tag" style="background:#f2f2f2;color:#555">{{ $etatsP[$s->physical_state] ?? '?' }}</span>
                <span class="tag" style="{{ $s->digital_state === 'active'
                    ? 'background:rgba(44,184,9,.12);color:#1a7a05'
                    : 'background:#f2f2f2;color:#666' }}">{{ $etatsN[$s->digital_state] ?? '?' }}</span>
            </div>
        @endforeach

        <div style="margin-top:12px">{{ $liste->links() }}</div>
    @endif
</div>

@endsection

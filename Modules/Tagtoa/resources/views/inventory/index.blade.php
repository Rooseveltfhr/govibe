@extends('tagtoa::layouts.dashboard')
@section('title', __('Stock'))
@section('page', __('Votre réserve'))

@php
    use Modules\Tagtoa\App\Support\Money;
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
@endphp

@section('content')

{{-- ---------- Ce que le patron regarde en premier ---------- --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:18px">
    <div class="card" style="margin:0">
        <div style="color:var(--muted);font-size:12px">{{ __('Valeur de la réserve') }}</div>
        <div style="font-size:24px;font-weight:700">{{ Money::format($summary['value'], $currency) }}</div>
        @if($summary['articles'] > 0 && $summary['known'] < $summary['articles'])
            {{-- Dire le chiffre sans dire sur quoi il porte le rendrait faux
                 dans la tête du marchand. --}}
            <div style="color:var(--muted);font-size:11.5px;margin-top:4px">
                {{ __('sur :connus des :total articles (prix d\'achat renseigné)', [
                    'connus' => $summary['known'], 'total' => $summary['articles'],
                ]) }}
            </div>
        @endif
    </div>

    <div class="card" style="margin:0;{{ $summary['low'] ? 'border-left:4px solid #e0a800' : '' }}">
        <div style="color:var(--muted);font-size:12px">{{ __('À recommander') }}</div>
        <div style="font-size:24px;font-weight:700">{{ $summary['low'] }}</div>
        <div style="color:var(--muted);font-size:11.5px;margin-top:4px">{{ __(':n en rupture', ['n' => $summary['out']]) }}</div>
    </div>

    <div class="card" style="margin:0;{{ $shrink['qty'] > 0 ? 'border-left:4px solid var(--red)' : '' }}">
        <div style="color:var(--muted);font-size:12px">{{ __('Disparu sans vente (30 j)') }}</div>
        <div style="font-size:24px;font-weight:700">{{ $fmt($shrink['qty']) }}</div>
        @if($shrink['value'] > 0)
            {{-- Estimation : le coût vient du prix d'achat d'aujourd'hui. --}}
            <div style="color:var(--muted);font-size:11.5px;margin-top:4px">
                ≈ {{ Money::format($shrink['value'], $currency) }} {{ __('perdus') }}
            </div>
        @endif
    </div>
</div>

{{-- ---------- La réserve ---------- --}}
<div class="h-row">
    <h2>{{ __('Articles suivis') }} <span style="color:var(--muted);font-weight:400">({{ $articles->count() }})</span></h2>
    <span style="flex:1"></span>
    <a href="{{ route('tagtoa.inventory.movements') }}" class="btn btn-o btn-sm">
        <i class="fa-solid fa-clock-rotate-left"></i> {{ __('Journal') }}
    </a>
    <a href="{{ route('tagtoa.inventory.suppliers') }}" class="btn btn-o btn-sm">
        <i class="fa-solid fa-truck-field"></i> {{ __('Fournisseurs') }}
    </a>
    <a href="{{ route('tagtoa.inventory.index', $onlyLow ? [] : ['low' => 1]) }}"
       class="btn {{ $onlyLow ? 'btn-p' : 'btn-o' }} btn-sm">
        <i class="fa-solid fa-triangle-exclamation"></i> {{ __('À recommander') }}
    </a>
</div>

@if($articles->isEmpty())
    <div class="card" style="text-align:center;color:var(--muted);padding:34px 16px">
        <i class="fa-solid fa-boxes-stacked" style="font-size:30px;opacity:.35"></i>
        <p style="margin-top:10px">
            {{ $onlyLow
                ? __('Rien à recommander pour le moment.')
                : __('Aucun article ne suit son stock. Indiquez une quantité sur un article du catalogue ou du menu pour commencer à le suivre.') }}
        </p>
    </div>
@else
<div class="card" style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:14px;min-width:640px">
        <thead>
            <tr style="text-align:left;color:var(--muted);font-size:12px">
                <th style="padding:8px 6px">{{ __('Article') }}</th>
                <th style="padding:8px 6px">{{ __('En stock') }}</th>
                <th style="padding:8px 6px">{{ __('Prix d\'achat') }}</th>
                <th style="padding:8px 6px">{{ __('Valeur') }}</th>
                <th style="padding:8px 6px"></th>
            </tr>
        </thead>
        <tbody>
        @foreach($articles as $a)
            <tr style="border-top:1px solid var(--bd)">
                <td style="padding:9px 6px">
                    <b>{{ $a->name }}</b>
                    <span style="color:var(--muted);font-size:11.5px">
                        · {{ $a->source === 'menu' ? __('Menu') : __('Caisse') }}
                    </span>
                </td>
                <td style="padding:9px 6px;white-space:nowrap">
                    <span @class(['stock-out' => $a->out]) style="{{ $a->out ? 'color:var(--red);font-weight:600' : ($a->low ? 'color:#b58100;font-weight:600' : '') }}">
                        {{ $fmt($a->stock) }} {{ $a->unit }}
                    </span>
                    @if($a->out)
                        <span style="color:var(--red);font-size:11px"> · {{ __('rupture') }}</span>
                    @elseif($a->low)
                        <span style="color:#b58100;font-size:11px"> · {{ __('à recommander') }}</span>
                    @endif
                </td>
                <td style="padding:9px 6px;color:var(--muted)">
                    {{ $a->cost === null ? '—' : Money::format($a->cost, $currency) }}
                </td>
                <td style="padding:9px 6px">
                    {{ $a->value === null ? '—' : Money::format($a->value, $currency) }}
                </td>
                <td style="padding:9px 6px;text-align:right;white-space:nowrap">
                    <button type="button" class="btn btn-o btn-sm mv"
                            data-ref="{{ $a->ref }}" data-name="{{ $a->name }}" data-stock="{{ $fmt($a->stock) }}">
                        <i class="fa-solid fa-right-left"></i> {{ __('Mouvement') }}
                    </button>
                    <a href="{{ route('tagtoa.inventory.movements', ['ref' => $a->ref]) }}"
                       class="btn btn-o btn-sm" title="{{ __('Historique de cet article') }}">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ---------- Enregistrer un mouvement ---------- --}}
<div class="card" id="mvcard" hidden style="margin-top:18px;border-left:4px solid #2cb809">
    <div class="h-row">
        <h2 style="margin:0">{{ __('Mouvement de stock') }} — <span id="mvname"></span></h2>
        <span style="flex:1"></span>
        <button type="button" class="btn btn-o btn-sm" onclick="fermerMv()"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <form method="POST" action="{{ route('tagtoa.inventory.move') }}">
        @csrf
        <input type="hidden" name="ref" id="mvref">

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:8px">
            <label style="font-size:12px;color:var(--muted)">{{ __('Motif') }}
                <select name="type" id="mvtype" class="inp" style="max-width:210px" required>
                    @foreach($motifs as $cle)
                        <option value="{{ $cle }}">{{ __($types[$cle]['label']) }}</option>
                    @endforeach
                </select>
            </label>

            <label style="font-size:12px;color:var(--muted)">
                <span id="mvqtylabel">{{ __('Quantité') }}</span>
                <input name="qty" type="number" step="0.001" min="0.001" class="inp" style="max-width:130px" required>
            </label>

            {{-- Réception seulement : ce que la livraison a coûté, et de qui. --}}
            <label class="mvbuy" style="font-size:12px;color:var(--muted)">{{ __('Prix d\'achat unitaire') }}
                <input name="unit_cost" type="number" step="0.01" min="0" class="inp" style="max-width:150px">
            </label>
            <label class="mvbuy" style="font-size:12px;color:var(--muted)">{{ __('Fournisseur') }}
                <select name="supplier_id" class="inp" style="max-width:200px">
                    <option value="">—</option>
                    @foreach($suppliers as $f)
                        <option value="{{ $f->id }}">{{ $f->name }}</option>
                    @endforeach
                </select>
            </label>

            <label style="font-size:12px;color:var(--muted);flex:1;min-width:200px">{{ __('Raison (ce que vous voulez retrouver plus tard)') }}
                <input name="reason" class="inp" maxlength="240" placeholder="{{ __('ex. cassé au transport, inventaire du samedi') }}">
            </label>
        </div>

        <p id="mvhint" style="color:var(--muted);font-size:12.5px;margin-top:10px"></p>

        <button class="btn btn-p" style="margin-top:6px">
            <i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer le mouvement') }}
        </button>
    </form>
</div>

@push('scripts')
<script>
var MV_COUNT = @json(\Modules\Tagtoa\App\Support\Inventory\MovementType::COUNT);
var MV_BUY   = @json(\Modules\Tagtoa\App\Support\Inventory\MovementType::PURCHASE);
var mvStock  = '0';

function fermerMv(){ document.getElementById('mvcard').hidden = true; }

/* Le comptage physique ne demande pas un écart mais ce qui a été COMPTÉ :
   l'écart est justement le chiffre que le patron vient chercher. */
function majMv(){
    var type = document.getElementById('mvtype').value;
    var compte = (type === MV_COUNT);

    document.getElementById('mvqtylabel').textContent = compte
        ? @json(__('Compté sur l\'étagère'))
        : @json(__('Quantité'));

    document.getElementById('mvhint').textContent = compte
        ? @json(__('Tapez ce que vous avez réellement compté. L\'écart est calculé pour vous. Stock affiché aujourd\'hui : ')) + mvStock
        : '';

    document.querySelectorAll('.mvbuy').forEach(function(el){
        el.style.display = (type === MV_BUY) ? '' : 'none';
    });
}

document.querySelectorAll('.mv').forEach(function(b){
    b.addEventListener('click', function(){
        mvStock = b.dataset.stock;
        document.getElementById('mvref').value  = b.dataset.ref;
        document.getElementById('mvname').textContent = b.dataset.name;
        document.getElementById('mvcard').hidden = false;
        majMv();
        document.getElementById('mvcard').scrollIntoView({behavior:'smooth', block:'center'});
    });
});
document.getElementById('mvtype').addEventListener('change', majMv);
majMv();
</script>
@endpush
@endsection

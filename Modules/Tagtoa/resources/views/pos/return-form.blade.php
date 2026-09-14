@extends('tagtoa::layouts.dashboard')
@section('title', __('Retour'))
@section('page', __('Retour sur').' '.$sale->reference)

@push('head')
<style>
.lg{display:flex;gap:11px;align-items:center;padding:12px 0}
.lg + .lg{border-top:1px solid var(--bd)}
.lg .corps{flex:1;min-width:0}
.lg .corps b{font:700 14.5px var(--fh);display:block}
.lg .corps span{font-size:12.5px;color:var(--muted)}
.lg input[type=number]{width:92px;padding:9px 10px;border:1.5px solid var(--bd);border-radius:9px;
                       font:15px var(--fb);text-align:right}
.lg.fini{opacity:.5}
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;font:14.5px var(--fb);background:#fff}
</style>
@endpush

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.pos.returns') }}" style="color:var(--muted);font-size:14px">
        <i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}
    </a>
</div>

<form method="POST" action="{{ route('tagtoa.pos.returns.store', $sale->id) }}" class="card">
    @csrf
    {{-- La clé est posée UNE FOIS au rendu de la page. Rechargée, réenvoyée,
         touchée deux fois par un doigt impatient : c'est la même clé, donc un
         seul remboursement. Sans elle, une connexion qui repart fait sortir
         l'argent deux fois. --}}
    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

    <div class="h-row" style="margin-bottom:4px">
        <h2>{{ __('Que rend le client ?') }}</h2>
        <span style="font:700 15px var(--fh)">{{ \Modules\Tagtoa\App\Support\Money::format($sale->total, $sale->currency) }}</span>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:8px">
        {{ __('Vous n\'indiquez que des quantités : le montant est recalculé sur les prix du ticket.') }}
    </p>

    @foreach($sale->items as $it)
        @php $reste = $rendable[$it->id] ?? 0; @endphp
        <div class="lg {{ $reste <= 0 ? 'fini' : '' }}">
            <span class="corps">
                <b>{{ $it->name }}</b>
                <span>
                    {{ \Modules\Tagtoa\App\Support\Money::format($it->price, $sale->currency) }}
                    · {{ __('vendu') }} {{ rtrim(rtrim(number_format($it->qty, 3, '.', ''), '0'), '.') }}
                    @if($reste <= 0)
                        · <b style="color:var(--red)">{{ __('déjà entièrement rendu') }}</b>
                    @else
                        · {{ __('rendable') }} {{ rtrim(rtrim(number_format($reste, 3, '.', ''), '0'), '.') }}
                    @endif
                </span>
            </span>
            <input type="number" name="qty[{{ $it->id }}]" min="0" max="{{ $reste }}" step="0.001"
                   value="0" {{ $reste <= 0 ? 'disabled' : '' }}
                   aria-label="{{ __('Quantité rendue') }} — {{ $it->name }}">
        </div>
    @endforeach

    <div style="margin-top:16px">
        <label class="lbl" for="kind">{{ __('Pourquoi ?') }}</label>
        <select class="ic" id="kind" name="kind">
            @foreach($kinds as $cle => $label)
                <option value="{{ $cle }}">{{ __($label) }}</option>
            @endforeach
        </select>
        <p style="color:var(--muted);font-size:12.5px;margin-top:6px">
            {{-- Un article défectueux ou périmé ne revient PAS en vente : le
                 remettre au stock ferait croire au marchand qu'il possède une
                 marchandise qu'il va jeter, et il ne recommanderait pas à temps. --}}
            {{ __('Un article défectueux ou périmé n\'est pas remis en stock.') }}
        </p>

        <label class="lbl" for="reason">{{ __('Note') }}</label>
        <input class="ic" id="reason" name="reason" maxlength="160" placeholder="{{ __('Facultatif') }}">

        <label class="switch" style="margin-top:12px">
            <input type="checkbox" name="restock" value="1" checked>
            {{ __('Remettre la marchandise en stock') }}
        </label>
    </div>

    <button class="btn btn-p" style="margin-top:16px">
        <i class="fa-solid fa-rotate-left"></i> {{ __('Enregistrer le retour') }}
    </button>
</form>
@endsection

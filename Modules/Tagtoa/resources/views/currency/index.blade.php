@extends('tagtoa::layouts.dashboard')
@section('title', __('Devises et taux'))
@section('page', __('Devises et taux'))

@section('content')
<div class="h-row"><h2 style="flex:1"><i class="fa-solid fa-coins"></i> {{ __('Devises et taux') }}</h2></div>
<p style="color:var(--muted);font-size:13px">{{ __('Un article peut avoir son prix en USD ou dans une devise à vous (ex. $HT). Le client voit ce prix, et le total de la commande est calculé dans la devise principale avec le taux ci-dessous. Une commande garde le taux du moment où elle a été passée. Le paiement en ligne se fait toujours dans la devise principale.') }}</p>

@if($errors->any())
    <div class="card" style="border-left:4px solid var(--red)">
        @foreach($errors->all() as $e)<div style="color:var(--red);font-size:13.5px">{{ $e }}</div>@endforeach
    </div>
@endif

<div class="card">
    <h3 style="margin:0 0 8px">{{ __('Ajouter ou modifier un taux') }}</h3>
    <form method="POST" action="{{ route('tagtoa.currencies.store') }}">
        @csrf
        <div class="row" style="align-items:flex-end;flex-wrap:wrap">
            <div>
                <label class="lbl">{{ __('Devise') }}</label>
                <input class="inp" name="code" maxlength="8" required list="known-currencies" placeholder="USD, $HT…" value="{{ old('code') }}" style="max-width:140px">
                <datalist id="known-currencies">@foreach($known as $c => $l)<option value="{{ $c }}">{{ $l }}</option>@endforeach<option value="$HT">{{ __('Dollar haïtien (5 gourdes)') }}</option></datalist>
            </div>
            <div><label class="lbl">1 = </label><input class="inp" type="number" name="rate" step="0.000001" min="0.000001" required value="{{ old('rate') }}" placeholder="132" style="max-width:140px"></div>
            <div>
                <label class="lbl">{{ __('Devise principale') }}</label>
                <select class="sel" name="base_currency">@foreach($bases as $b)<option value="{{ $b }}" @selected(old('base_currency') === $b)>{{ $b }}</option>@endforeach</select>
            </div>
            <div><label class="lbl">{{ __('Symbole') }}</label><input class="inp" name="symbol" maxlength="8" value="{{ old('symbol') }}" placeholder="$" style="max-width:90px"></div>
            <div><label class="lbl">{{ __('Décimales') }}</label><input class="inp" type="number" name="decimals" min="0" max="3" value="{{ old('decimals', 2) }}" style="max-width:80px"></div>
        </div>
        @if(session('needs_confirm'))
            <label style="display:block;margin-top:8px;color:var(--red)"><input type="checkbox" name="confirm_jump" value="1"> {{ __('Je confirme ce taux') }}</label>
        @endif
        <button class="btn btn-p" style="margin-top:10px"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
    </form>
</div>

@if($rates->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-coins"></i>{{ __('Aucune devise ajoutée : tous vos prix sont dans la devise principale.') }}</div></div>
@else
    <div class="card" style="padding:0;overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <thead><tr style="color:var(--muted);text-align:left">
                <th style="padding:8px 10px">{{ __('Devise') }}</th><th style="padding:8px 10px">{{ __('Taux') }}</th>
                <th style="padding:8px 10px">{{ __('Statut') }}</th><th style="padding:8px 10px">{{ __('Mis à jour') }}</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($rates as $r)
                <tr style="border-top:1px solid var(--bd,#eee)">
                    <td style="padding:7px 10px"><b>{{ $r->code }}</b>@if($r->symbol && $r->symbol !== $r->code) <span style="color:var(--muted)">({{ $r->symbol }})</span>@endif</td>
                    <td style="padding:7px 10px">1 {{ $r->code }} = {{ rtrim(rtrim(number_format((float) $r->rate, 6, '.', ','), '0'), '.') }} {{ $r->base_currency }}</td>
                    <td style="padding:7px 10px"><span class="pill {{ $r->is_active ? 'g' : 'n' }}">{{ $r->is_active ? __('Active') : __('Désactivée') }}</span></td>
                    <td style="padding:7px 10px;color:var(--muted)">{{ $r->updated_at?->format('d/m/Y H:i') }}</td>
                    <td style="padding:7px 10px">
                        <form method="POST" action="{{ route('tagtoa.currencies.toggle', $r->id) }}">@csrf
                            <button class="btn btn-o btn-sm" title="{{ $r->is_active ? __('Désactiver') : __('Activer') }}"><i class="fa-solid {{ $r->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($history->isNotEmpty())
    <details class="card"><summary style="cursor:pointer;font-weight:600">{{ __('Historique des taux') }}</summary>
        <ul style="font-size:13px;margin:8px 0 0;padding-left:18px">
            @foreach($history as $h)
                <li>{{ $h->created_at?->format('d/m/Y H:i') }} — {{ $h->code }} → {{ $h->base_currency }} :
                    @if($h->event === 'rate'){{ (float) $h->old_rate }} → {{ (float) $h->new_rate }}
                    @elseif($h->event === 'created'){{ __('créée à') }} {{ (float) $h->new_rate }}
                    @elseif($h->event === 'activated'){{ __('réactivée') }}
                    @else{{ __('désactivée') }}@endif
                </li>
            @endforeach
        </ul>
    </details>
@endif
@endsection

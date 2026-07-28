@extends('tagtoa::layouts.dashboard')
@section('title', __('Cartes TAGTOA'))
@section('page', __('Cartes TAGTOA'))

@section('content')

<div class="card" style="margin-bottom:18px">
    <div class="h-row"><h2><i class="fa-solid fa-credit-card"></i> {{ __('Carte prépayée closed-loop') }}</h2></div>
    <p style="color:var(--muted);margin:0 0 14px">{{ __('Une carte NFC TAGTOA porte un solde utilisable chez tous vos points de vente TAGTOA (paiements, menu, événements, caisse). Le client recharge, puis paie en tapant sa carte + PIN — sans espèces, sans réseau externe.') }}</p>
    @if($totals->isNotEmpty())
        <div style="display:flex;flex-wrap:wrap;gap:12px">
            @foreach($totals as $t)
                <div style="background:var(--soft,#f4f6f8);border-radius:14px;padding:14px 18px;min-width:150px">
                    <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em">{{ __('Solde en circulation') }} · {{ $t['currency'] }}</div>
                    <div style="font-family:var(--fh);font-size:22px;font-weight:700">{{ $t['label'] }}</div>
                    <div style="font-size:12px;color:var(--muted)">{{ $t['count'] }} {{ __('carte(s)') }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="card" style="margin-bottom:18px">
    <div class="h-row"><h2>{{ __('Émettre une carte') }}</h2></div>
    <form method="POST" action="{{ route('tagtoa.cards.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
            <div>
                <label class="lbl">{{ __('UID de la carte (NFC)') }} *</label>
                <div style="display:flex;gap:8px">
                    <input class="inp" id="issue-uid" name="card_uid" value="{{ old('card_uid') }}" placeholder="{{ __('Tapez la carte…') }}" required>
                    <button type="button" class="btn btn-o" style="flex:0;white-space:nowrap" onclick="readIssue()"><i class="fa-solid fa-wifi"></i></button>
                </div>
            </div>
            <div><label class="lbl">{{ __('Titulaire') }}</label><input class="inp" name="holder_name" value="{{ old('holder_name') }}" placeholder="{{ __('Nom (optionnel)') }}"></div>
            <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="holder_phone" value="{{ old('holder_phone') }}" placeholder="+509 ..."></div>
            <div>
                <label class="lbl">{{ __('Devise') }}</label>
                <select class="sel inp" name="currency">
                    @foreach($currencies as $code => $label)
                        <option value="{{ $code }}" @selected(old('currency','HTG')===$code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="lbl">{{ __('PIN (4-6 chiffres)') }} *</label><input class="inp" name="pin" inputmode="numeric" maxlength="6" pattern="\d{4,6}" value="{{ old('pin') }}" placeholder="{{ __('Obligatoire — sécurise la carte') }}" required></div>
            <div><label class="lbl">{{ __('Recharge initiale') }}</label><input class="inp" name="initial_amount" type="number" step="0.01" min="0" value="{{ old('initial_amount') }}" placeholder="0.00"></div>
        </div>
        <button class="btn btn-p" type="submit" style="margin-top:14px"><i class="fa-solid fa-plus"></i> {{ __('Émettre la carte') }}</button>
    </form>
</div>

<div class="card">
    <div class="h-row">
        <h2>{{ __('Cartes émises') }}</h2>
        <form method="GET" style="flex:0">
            <input class="inp" name="q" value="{{ $q }}" placeholder="{{ __('Code, nom, téléphone…') }}" style="min-width:220px">
        </form>
    </div>

    @if($cards->isEmpty())
        <div class="empty"><i class="fa-solid fa-credit-card"></i>{{ __('Aucune carte pour le moment.') }}</div>
    @else
        <table>
            <thead><tr>
                <th>{{ __('Code') }}</th>
                <th>{{ __('Titulaire') }}</th>
                <th>{{ __('Solde') }}</th>
                <th>{{ __('Statut') }}</th>
                <th style="text-align:right">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @foreach($cards as $c)
                <tr>
                    <td><b>{{ $c->code }}</b>@if($c->hasPin()) <i class="fa-solid fa-lock" title="{{ __('PIN activé') }}" style="color:var(--muted);font-size:11px"></i>@endif</td>
                    <td>{{ $c->holder_name ?: '—' }}<br><span style="color:var(--muted);font-size:12px">{{ $c->holder_phone }}</span></td>
                    <td><b>{{ $c->balance_label }}</b></td>
                    <td>
                        @if($c->status==='active')<span class="pill ok">{{ __('Active') }}</span>
                        @elseif($c->status==='blocked')<span class="pill n">{{ __('Bloquée') }}</span>
                        @else<span class="pill n" style="opacity:.7">{{ __('Perdue') }}</span>@endif
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                            <form method="POST" action="{{ route('tagtoa.cards.topup',$c->id) }}" style="display:flex;gap:4px;flex:0">
                                @csrf
                                <input class="inp" name="amount" type="number" step="0.01" min="0.01" placeholder="{{ __('Recharge') }}" style="width:110px" required>
                                <button class="btn btn-p btn-sm" style="flex:0"><i class="fa-solid fa-plus"></i></button>
                            </form>
                            @if($c->status==='active')
                                <form method="POST" action="{{ route('tagtoa.cards.status',$c->id) }}" style="flex:0" onsubmit="return confirm('{{ __('Bloquer cette carte ?') }}')">
                                    @csrf<input type="hidden" name="status" value="blocked">
                                    <button class="btn btn-o btn-sm" style="flex:0;color:var(--red)"><i class="fa-solid fa-ban"></i></button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('tagtoa.cards.status',$c->id) }}" style="flex:0">
                                    @csrf<input type="hidden" name="status" value="active">
                                    <button class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-rotate-left"></i></button>
                                </form>
                            @endif
                            <a href="{{ route('tagtoa.cards.show',$c->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-list"></i></a>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px">{{ $cards->links() }}</div>
    @endif
</div>

<script>
function nfcInto(input){
    if(!('NDEFReader' in window)){alert('{{ __('NFC non supporté ici. Saisissez l\'UID manuellement.') }}');return;}
    try{var r=new NDEFReader();r.scan().then(function(){r.onreading=function(e){if(e.serialNumber){input.value=e.serialNumber;}};}).catch(function(){alert('{{ __('Lecture NFC refusée.') }}');});}catch(err){}
}
function readIssue(){nfcInto(document.getElementById('issue-uid'));}
</script>
@endsection

@extends('tagtoa::layouts.dashboard')
@php use Modules\Tagtoa\App\Support\Money; @endphp
@section('title', __('Wallet') . ' — ' . $event->title)
@section('page', __('Wallet') . ' — ' . $event->title)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.event.dashboard.edit',$event->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Wallet closed-loop') }}</h2>
    <a href="{{ route('tagtoa.event.dashboard.wallet.terminal',$event->id) }}" target="_blank" class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-cash-register"></i> {{ __('Terminal vendeur') }}</a>
    <a href="{{ route('tagtoa.event.dashboard.wallet.export',$event->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-file-csv"></i> {{ __('Export') }}</a>
</div>

<div class="grid g4">
    <div class="stat"><div class="ic"><i class="fa-solid fa-users"></i></div><div class="v">{{ $participantsCount }}</div><div class="k">{{ __('Participants') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-wallet"></i></div><div class="v">{{ Money::formatMinor($loaded, $event->currency) }}</div><div class="k">{{ __('Solde chargé (participants)') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-store"></i></div><div class="v">{{ Money::formatMinor((int) $vendors->sum('balance_minor'), $event->currency) }}</div><div class="k">{{ __('Dû aux stands') }}</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-building-columns"></i></div><div class="v">{{ Money::formatMinor((int) optional($system->get('organizer'))->balance_minor, $event->currency) }}</div><div class="k">{{ __('Réglé à l\'organisateur') }}</div></div>
</div>

<div class="grid g3" style="margin-top:16px">
    {{-- Enregistrer un tag --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Enregistrer un tag NFC') }}</h2></div>
        <form method="POST" action="{{ route('tagtoa.event.dashboard.wallet.tag',$event->id) }}">@csrf
            <label class="lbl">{{ __('UID du tag') }}</label><input class="inp" name="uid" placeholder="04:A2:..." required>
            <label class="lbl">{{ __('Nom du porteur') }}</label><input class="inp" name="label" placeholder="{{ __('Client A') }}">
            <label class="lbl">{{ __('Téléphone (WhatsApp)') }}</label><input class="inp" name="phone" placeholder="+509 0000 0000">
            <label class="lbl">{{ __('Type') }}</label>
            <select class="sel" name="kind">@foreach(['card'=>'Carte','wristband'=>'Bracelet','virtual'=>'Virtuel'] as $k=>$v)<option value="{{ $k }}">{{ __($v) }}</option>@endforeach</select>
            <button class="btn btn-p" style="margin-top:12px"><i class="fa-solid fa-id-card"></i> {{ __('Enregistrer') }}</button>
        </form>
    </div>

    {{-- Recharge --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Recharger un wallet') }}</h2></div>
        <form method="POST" action="{{ route('tagtoa.event.dashboard.wallet.topup',$event->id) }}">@csrf
            <label class="lbl">{{ __('UID du tag') }}</label><input class="inp" name="uid" placeholder="04:A2:..." required>
            <label class="lbl">{{ __('Montant') }} ({{ $event->currency }})</label><input class="inp" type="number" step="0.01" min="0.01" name="amount" required>
            <label class="lbl">{{ __('Référence paiement (optionnel)') }}</label><input class="inp" name="payment_ref" placeholder="MonCash / NatCash / …">
            <button class="btn btn-p" style="margin-top:12px"><i class="fa-solid fa-plus"></i> {{ __('Recharger') }}</button>
        </form>
        <p style="color:var(--muted);font-size:12.5px;margin-top:10px"><i class="fa-solid fa-circle-info"></i> {{ __('Recharge manuelle / sur preuve. Le top-up par API de paiement arrivera avec les passerelles PAY.') }}</p>
    </div>

    {{-- Ajouter un stand --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Ajouter un stand') }}</h2></div>
        <form method="POST" action="{{ route('tagtoa.event.dashboard.wallet.vendor',$event->id) }}">@csrf
            <label class="lbl">{{ __('Nom du stand') }}</label><input class="inp" name="label" placeholder="{{ __('Bar principal') }}" required>
            <button class="btn btn-p" style="margin-top:12px"><i class="fa-solid fa-store"></i> {{ __('Ajouter') }}</button>
        </form>
    </div>
</div>

{{-- Encoder une carte participant (billet d'entrée + wallet) --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Encoder une carte (entrée + wallet)') }}</h2>
        <a href="{{ route('tagtoa.event.dashboard.wallet.mass-encode',$event->id) }}" target="_blank" class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-layer-group"></i> {{ __('Encodage en masse') }}</a>
    </div>
    <p style="color:var(--muted);font-size:13px;margin-top:-8px">{{ __('Point de vente : tapez une carte vierge, saisissez le participant et son billet. La carte servira à l\'entrée (check-in) ET au paiement.') }}</p>
    <form method="POST" action="{{ route('tagtoa.event.dashboard.wallet.encode',$event->id) }}">@csrf
        <div class="row">
            <div><label class="lbl">{{ __('UID du tag') }}</label><input class="inp" name="uid" placeholder="04:A2:..." required></div>
            <div><label class="lbl">{{ __('Nom du participant') }}</label><input class="inp" name="name" required></div>
        </div>
        <div class="row">
            <div><label class="lbl">{{ __('Téléphone (WhatsApp)') }}</label><input class="inp" name="phone" placeholder="+509 0000 0000"></div>
            <div><label class="lbl">{{ __('E-mail (optionnel)') }}</label><input class="inp" type="email" name="email" placeholder="participant@exemple.com"></div>
            <div><label class="lbl">{{ __('Type de billet') }}</label>
                <select class="sel" name="ticket_type_id"><option value="">{{ __('— Aucun —') }}</option>@foreach($ticketTypes as $tt)<option value="{{ $tt->id }}">{{ $tt->name }}</option>@endforeach</select>
            </div>
            <div><label class="lbl">{{ __('Recharge initiale') }} ({{ $event->currency }})</label><input class="inp" type="number" step="0.01" min="0" name="amount" placeholder="0"></div>
        </div>
        <button class="btn btn-p" style="margin-top:12px"><i class="fa-solid fa-id-card-clip"></i> {{ __('Encoder la carte') }}</button>
    </form>
</div>

{{-- Réglages notifications --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Notifications organisateur') }}</h2></div>
    <form method="POST" action="{{ route('tagtoa.event.dashboard.wallet.settings',$event->id) }}" style="display:flex;gap:8px;align-items:flex-end">@csrf
        <div style="flex:1"><label class="lbl">{{ __('E-mail organisateur (alerte à chaque entrée)') }}</label><input class="inp" type="email" name="notify_email" value="{{ $event->notify_email }}" placeholder="organisateur@exemple.com"></div>
        <button class="btn btn-d" style="flex:0"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
    </form>
</div>

{{-- Réconciliation stands --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Réconciliation des stands') }}</h2></div>
    @if($vendors->isEmpty())
        <div class="empty"><i class="fa-solid fa-store"></i>{{ __('Aucun stand. Ajoutez-en un ci-dessus.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Stand') }}</th><th>{{ __('Solde à régler') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($vendors as $v)
                <tr>
                    <td>{{ $v->owner_label }}</td>
                    <td><b>{{ Money::formatMinor((int) $v->balance_minor, $v->currency) }}</b></td>
                    <td style="text-align:right">
                        @if((int) $v->balance_minor > 0)
                            <form method="POST" action="{{ route('tagtoa.event.dashboard.wallet.payout',$event->id) }}" onsubmit="return confirm('{{ __('Régler ce stand ?') }}')">@csrf<input type="hidden" name="vendor_id" value="{{ $v->id }}"><button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Régler') }}</button></form>
                        @else
                            <span class="pill n">{{ __('Rien à régler') }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Transactions récentes --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Transactions récentes') }}</h2></div>
    @if($txns->isEmpty())
        <div class="empty"><i class="fa-solid fa-receipt"></i>{{ __('Aucune transaction.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Référence') }}</th><th>{{ __('Type') }}</th><th>{{ __('Montant') }}</th><th>{{ __('Date') }}</th></tr></thead>
            <tbody>
            @foreach($txns as $t)
                <tr>
                    <td style="font-family:var(--fh)">{{ $t->reference }}</td>
                    <td><span class="pill n">{{ __(\Modules\Tagtoa\App\Services\Audit\AuditService::actionLabel('wallet.'.$t->type)) }}</span></td>
                    <td>{{ Money::formatMinor((int) $t->amount_minor, $t->currency) }}</td>
                    <td style="color:var(--muted)">{{ optional($t->created_at)->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection

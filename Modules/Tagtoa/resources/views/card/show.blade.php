@extends('tagtoa::layouts.dashboard')
@section('title', __('Carte :code', ['code' => $card->code]))
@section('page', __('Carte').' '.$card->code)

@section('content')

<div class="card" style="margin-bottom:18px">
    <div class="h-row">
        <h2><i class="fa-solid fa-credit-card"></i> {{ $card->code }}</h2>
        <a href="{{ route('tagtoa.cards.print', $card->id) }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-print"></i> {{ __('Imprimer la carte') }}</a>
        <a href="{{ route('tagtoa.cards.index') }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:24px;align-items:center">
        <div>
            <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em">{{ __('Solde') }}</div>
            <div style="font-family:var(--fh);font-size:28px;font-weight:700">{{ $card->balance_label }}</div>
        </div>
        <div>
            <div style="font-size:12px;color:var(--muted)">{{ __('Titulaire') }}</div>
            <div>{{ $card->holder_name ?: '—' }} <span style="color:var(--muted)">{{ $card->holder_phone }}</span></div>
        </div>
        <div>
            <div style="font-size:12px;color:var(--muted)">{{ __('Statut') }}</div>
            @if($card->status==='active')<span class="pill ok">{{ __('Active') }}</span>
            @elseif($card->status==='blocked')<span class="pill n">{{ __('Bloquée') }}</span>
            @else<span class="pill n" style="opacity:.7">{{ __('Perdue') }}</span>@endif
        </div>
    </div>
    @php $rechargeUrl = route('tagtoa.card.recharge').'?code='.urlencode($card->code); @endphp
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line,#eee)">
        <div style="font-size:12px;color:var(--muted);margin-bottom:6px">{{ __('Lien de recharge en ligne (à partager avec le client)') }}</div>
        <div style="display:flex;gap:8px;align-items:center">
            <input class="inp" value="{{ $rechargeUrl }}" readonly onclick="this.select()" style="flex:1">
            <a href="{{ $rechargeUrl }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-up-right-from-square"></i></a>
        </div>
    </div>
</div>

<div class="card">
    <div class="h-row"><h2>{{ __('Historique des mouvements') }}</h2></div>
    @if($txns->isEmpty())
        <div class="empty"><i class="fa-solid fa-clock-rotate-left"></i>{{ __('Aucun mouvement.') }}</div>
    @else
        <table>
            <thead><tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Montant') }}</th>
                <th>{{ __('Solde après') }}</th>
                <th>{{ __('Contexte') }}</th>
            </tr></thead>
            <tbody>
            @foreach($txns as $t)
                <tr>
                    <td style="white-space:nowrap;color:var(--muted)">{{ optional($t->created_at)->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($t->type==='topup')<span class="pill ok">{{ __('Recharge') }}</span>
                        @elseif($t->type==='charge')<span class="pill n">{{ __('Paiement') }}</span>
                        @elseif($t->type==='refund')<span class="pill">{{ __('Remboursement') }}</span>
                        @else<span class="pill">{{ __('Ajustement') }}</span>@endif
                    </td>
                    <td><b>{{ $t->amount_label }}</b></td>
                    <td>{{ \Modules\Tagtoa\App\Support\Money::formatMinor((int) $t->balance_after_minor, $t->currency) }}</td>
                    <td style="color:var(--muted)">{{ $t->context_type ? $t->context_type.($t->context_id ? ' #'.$t->context_id : '') : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:16px">{{ $txns->links() }}</div>
    @endif
</div>
@endsection

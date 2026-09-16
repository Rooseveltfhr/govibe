@extends('tagtoa::layouts.dashboard')
@section('title', __('Invités VIP'))
@section('page', $event->title)

@section('content')
<a href="{{ route('tagtoa.event.dashboard.orders',$event->id) }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>

<div class="card" style="margin-top:14px">
    <div class="h-row"><h2><i class="fa-solid fa-star"></i> {{ __('Inviter un invité') }}</h2></div>
    <p style="color:var(--muted);font-size:12.5px;margin:0 0 10px">
        {{ __('Billet offert, hors panier — ne compte pas dans le chiffre d\'affaires, mais compte dans la place disponible du type choisi.') }}
    </p>
    @if($event->ticketTypes->isEmpty())
        <div class="empty"><i class="fa-regular fa-ticket"></i>{{ __('Créez d\'abord un type de billet (onglet Modifier) avant d\'inviter un invité.') }}</div>
    @else
        <form method="POST" action="{{ route('tagtoa.event.dashboard.guests.invite',$event->id) }}" class="row" style="align-items:flex-end;flex-wrap:wrap;gap:10px">
            @csrf
            <div>
                <label class="lbl">{{ __('Type de billet') }}</label>
                <select class="inp" name="ticket_type_id" required>
                    @foreach($event->ticketTypes as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}{{ $t->is_vip ? ' ⭐' : '' }}{{ $t->remaining !== null ? ' — '.$t->remaining.' '.__('restantes') : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:2"><label class="lbl">{{ __('Nom de l\'invité') }}</label><input class="inp" name="name" required></div>
            <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="phone"></div>
            <div><label class="lbl">{{ __('E-mail') }}</label><input class="inp" type="email" name="email"></div>
            <button class="btn btn-p" style="flex:0">{{ __('Inviter') }}</button>
        </form>
    @endif
</div>

<div class="card" style="margin-top:14px">
    <div class="h-row"><h2>{{ __('Invités') }}</h2></div>
    @if($guests->isEmpty())
        <div class="empty"><i class="fa-regular fa-star"></i>{{ __('Aucun invité pour le moment.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Nom') }}</th><th>{{ __('Type de billet') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Invité le') }}</th></tr></thead>
            <tbody>
            @foreach($guests as $g)
                <tr>
                    <td><b>{{ $g->buyer_name }}</b></td>
                    <td>
                        @foreach($g->tickets as $t)
                            <span class="pill {{ optional($t->ticketType)->is_vip ? 'a' : 'n' }}">{{ optional($t->ticketType)->name }}{{ optional($t->ticketType)->is_vip ? ' ⭐' : '' }}</span>
                        @endforeach
                    </td>
                    <td style="color:var(--muted)">{{ $g->buyer_phone }} {{ $g->buyer_email }}</td>
                    <td style="color:var(--muted)">{{ $g->created_at->format('d/m/y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:14px">{{ $guests->links() }}</div>
    @endif
</div>
@endsection

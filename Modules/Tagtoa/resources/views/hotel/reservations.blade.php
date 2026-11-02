@extends('tagtoa::layouts.dashboard')
@section('title', __('Réservations'))
@section('page', __('Réservations').' — '.$hotel->name)

@section('content')
<div class="h-row">
    <h2 style="flex:1"><i class="fa-solid fa-calendar-check"></i> {{ __('Réservations') }}
        <span class="pill n">{{ array_sum($compte) }}</span>
    </h2>
</div>
<p style="color:var(--muted);font-size:13px">{{ __('Les demandes envoyées depuis la page de votre hôtel. Confirmez ou refusez : le client le voit sur sa page de suivi. Aucun paiement en ligne — il règle sur place.') }}</p>

<div style="display:flex;gap:6px;flex-wrap:wrap;margin:8px 0">
    <a class="btn btn-sm {{ $filtre ? 'btn-o' : '' }}" href="{{ route('tagtoa.hotel.reservations') }}">{{ __('Toutes') }}</a>
    @foreach($statuses as $k => $lbl)
        <a class="btn btn-sm {{ $filtre === $k ? '' : 'btn-o' }}" href="{{ route('tagtoa.hotel.reservations', ['status' => $k]) }}">{{ __($lbl) }} <span class="pill n">{{ $compte[$k] ?? 0 }}</span></a>
    @endforeach
</div>

@if($liste->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-calendar-check"></i>{{ __('Aucune demande pour le moment.') }}</div></div>
@else
    <div class="card" style="padding:0;overflow-x:auto">
        <table class="rcards" style="width:100%;border-collapse:collapse;font-size:13.5px">
            <thead><tr style="color:var(--muted);text-align:left">
                <th style="padding:8px 10px">{{ __('Client') }}</th>
                <th style="padding:8px 10px">{{ __('Chambre') }}</th>
                <th style="padding:8px 10px">{{ __('Séjour') }}</th>
                <th style="padding:8px 10px">{{ __('Total') }}</th>
                <th style="padding:8px 10px">{{ __('Statut') }}</th>
                <th style="padding:8px 10px"></th>
            </tr></thead>
            <tbody>
            @foreach($liste as $r)
                <tr style="border-top:1px solid var(--bd,#eee)">
                    <td data-l="{{ __('Client') }}" style="padding:7px 10px">
                        <b>{{ $r->guest_name ?? '—' }}</b>
                        @if($r->guest_phone)
                            <div><a href="tel:{{ $r->guest_phone }}">{{ $r->guest_phone }}</a> · <a href="https://wa.me/{{ $r->guest_phone }}" rel="noopener noreferrer" target="_blank">WhatsApp</a></div>
                        @endif
                        <div style="color:var(--muted);font-size:12px">{{ $r->reference }}</div>
                        @if($r->note)<div style="font-size:12.5px;white-space:pre-line">{{ $r->note }}</div>@endif
                    </td>
                    <td data-l="{{ __('Chambre') }}" style="padding:7px 10px"><b>{{ $r->room_type_name }}</b><div style="color:var(--muted)">{{ $r->plan_label }}</div></td>
                    <td data-l="{{ __('Séjour') }}" style="padding:7px 10px">
                        {{ $r->starts_on?->format('d/m/Y') }}@if($r->start_time) {{ substr((string) $r->start_time, 0, 5) }}@endif
                        @if($r->plan_kind === 'night')<div style="color:var(--muted)">{{ trans_choice(':n nuit|:n nuits', $r->nights, ['n' => $r->nights]) }}</div>@endif
                        @if($r->guests)<div style="color:var(--muted)">{{ trans_choice(':n personne|:n personnes', $r->guests, ['n' => $r->guests]) }}</div>@endif
                    </td>
                    <td data-l="{{ __('Total') }}" style="padding:7px 10px">
                        {{ \Modules\Tagtoa\App\Support\Money::format((float) $r->total, $r->currency) }}
                        @if($r->inclusionItems())<div style="color:var(--muted);font-size:12px">{{ collect($r->inclusionItems())->pluck('label')->implode(' · ') }}</div>@endif
                    </td>
                    <td data-l="{{ __('Statut') }}" style="padding:7px 10px">
                        <span class="pill {{ $r->status === 'confirmed' ? 'g' : ($r->status === 'new' ? 'a' : 'n') }}">{{ __($r->status_label) }}</span>
                        @if($r->reason)<div style="color:var(--muted);font-size:12px">{{ $r->reason }}</div>@endif
                    </td>
                    <td class="acts" style="padding:7px 10px">
                        @if($r->status === 'new')
                            <form method="POST" action="{{ route('tagtoa.hotel.reservations.status', $r->id) }}" style="display:inline">
                                @csrf <input type="hidden" name="to" value="confirmed">
                                <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Confirmer') }}</button>
                            </form>
                        @endif
                        @if(in_array($r->status, ['new', 'confirmed'], true))
                            <details style="display:inline-block">
                                <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex;color:var(--red)">{{ __('Refuser') }}</summary>
                                <form method="POST" action="{{ route('tagtoa.hotel.reservations.status', $r->id) }}" style="margin-top:6px;min-width:220px">
                                    @csrf <input type="hidden" name="to" value="refused">
                                    <input class="inp" name="reason" maxlength="200" placeholder="{{ __('Motif (visible du client)') }}">
                                    <button class="btn btn-o btn-sm" style="margin-top:6px;color:var(--red)">{{ __('Refuser cette demande') }}</button>
                                </form>
                            </details>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if(array_sum($compte) > $max)<p style="color:var(--muted);font-size:12.5px">{{ __(':n premières demandes affichées — utilisez les filtres.', ['n' => $max]) }}</p>@endif
@endif
@endsection

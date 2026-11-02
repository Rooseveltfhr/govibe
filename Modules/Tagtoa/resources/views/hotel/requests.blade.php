@extends('tagtoa::layouts.dashboard')
@section('title', __('Demandes'))
@section('page', __('Demandes des invités').' — '.$hotel->name)

@section('content')
<div class="h-row">
    <h2 style="flex:1"><i class="fa-solid fa-bell-concierge"></i> {{ __('Demandes') }}</h2>
    <a class="btn btn-o btn-sm" href="{{ route('tagtoa.hotel.requests', ['statut' => $filtre === 'toutes' ? 'ouvertes' : 'toutes']) }}">
        {{ $filtre === 'toutes' ? __('Voir les ouvertes') : __('Voir toutes') }}
    </a>
</div>

@if($requests->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-bell-concierge"></i>{{ __('Aucune demande pour le moment.') }}</div></div>
@else
    <form method="POST" action="{{ route('tagtoa.hotel.requests.bulk') }}" id="bulkForm">@csrf</form>
    <div class="card" style="padding:0;overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13.5px">
            <thead><tr style="color:var(--muted);text-align:left">
                <th style="padding:8px 10px"></th>
                <th style="padding:8px 10px">{{ __('Chambre') }}</th>
                <th style="padding:8px 10px">{{ __('Demande') }}</th>
                <th style="padding:8px 10px">{{ __('Statut') }}</th>
                <th style="padding:8px 10px">{{ __('Reçue') }}</th>
                <th style="padding:8px 10px"></th>
            </tr></thead>
            <tbody>
            @foreach($requests as $r)
                <tr style="border-top:1px solid var(--bd,#eee)">
                    <td style="padding:7px 10px">@if($r->isOpen())<input type="checkbox" name="ids[]" value="{{ $r->id }}" form="bulkForm">@endif</td>
                    <td style="padding:7px 10px"><b>{{ optional($rooms->get($r->room_id))->number ?? '—' }}</b></td>
                    <td style="padding:7px 10px">{{ $r->service_name }}@if($r->note)<div style="color:var(--muted);font-size:12.5px;white-space:pre-line">{{ $r->note }}</div>@endif<div style="color:var(--muted);font-size:12px">{{ $r->reference }}</div></td>
                    <td style="padding:7px 10px"><span class="pill {{ $r->status === 'done' ? 'g' : 'n' }}">{{ __($statuses[$r->status] ?? $r->status) }}</span>@if($r->status === 'rejected' && $r->reason)<div style="color:var(--muted);font-size:12.5px">{{ $r->reason }}</div>@endif</td>
                    <td style="padding:7px 10px;white-space:nowrap">{{ $r->created_at?->format('d/m H:i') }}</td>
                    <td style="padding:7px 10px;white-space:nowrap">
                        @foreach(($next[$r->status] ?? []) as $to)
                            @if($to === 'rejected')
                                <details style="display:inline-block">
                                    <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex">{{ __($statuses[$to]) }}</summary>
                                    <form method="POST" action="{{ route('tagtoa.hotel.requests.status', $r->id) }}" style="margin-top:6px">
                                        @csrf <input type="hidden" name="status" value="rejected">
                                        <input class="inp" name="reason" maxlength="200" placeholder="{{ __('Motif (visible par l\'invité)') }}">
                                        <button class="btn btn-o btn-sm" style="margin-top:6px">{{ __('Confirmer') }}</button>
                                    </form>
                                </details>
                            @else
                                <form method="POST" action="{{ route('tagtoa.hotel.requests.status', $r->id) }}" style="display:inline">
                                    @csrf <input type="hidden" name="status" value="{{ $to }}">
                                    <button class="btn btn-o btn-sm">{{ __($statuses[$to]) }}</button>
                                </form>
                            @endif
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <button class="btn btn-o btn-sm" form="bulkForm" style="margin-top:10px" onclick="return confirm(@json(__('Clôturer les demandes cochées ?'), JSON_HEX_APOS | JSON_HEX_QUOT))"><i class="fa-solid fa-broom"></i> {{ __('Clôturer la sélection') }}</button>
    <div style="margin-top:10px">{{ $requests->links() }}</div>
@endif
@endsection

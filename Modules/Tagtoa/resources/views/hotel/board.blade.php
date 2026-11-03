@extends('tagtoa::layouts.dashboard')
@section('title', __('Écran du personnel'))
@section('page', __('Écran du personnel').' — '.$hotel->name)

@section('content')
<div class="h-row">
    <h2 style="flex:1"><i class="fa-solid fa-bell-concierge"></i> {{ __('À traiter') }}
        <span class="pill n">{{ $totalOuvertes + $totalResas }}</span></h2>
    <span style="color:var(--muted);font-size:13px">{{ $me->name }} · {{ __($me->role === 'hotel_staff' ? ($me->hotel_team ? \Modules\Tagtoa\App\Support\Hotel\HotelServiceRules::STAFF_TEAMS[$me->hotel_team] ?? '' : '') : $me->role_label) }}</span>
    <form method="POST" action="{{ route('tagtoa.hotel.board.logout') }}" style="display:inline">@csrf<button class="btn btn-o btn-sm">{{ __('Changer de personne') }}</button></form>
</div>

@if($totalOuvertes > $requests->count())
    <div class="card" style="border-left:4px solid var(--amber,#E0A100);font-size:13.5px">{{ __(':n demandes plus anciennes ne sont pas affichées. Clôturez-les depuis l\'écran « Demandes ».', ['n' => $totalOuvertes - $requests->count()]) }}</div>
@endif

@if($resas->isNotEmpty())
    <h3 style="margin:14px 0 6px"><i class="fa-solid fa-calendar-check"></i> {{ __('Réservations à traiter') }} <span class="pill n">{{ $totalResas }}</span></h3>
    @if($totalResas > $resas->count())
        <div class="card" style="border-left:4px solid var(--amber,#E0A100);font-size:13.5px">{{ __(':n réservations plus anciennes ne sont pas affichées. Traitez-les depuis « Réservations ».', ['n' => $totalResas - $resas->count()]) }}</div>
    @endif
    @foreach($resas as $r)
        <div class="card" style="margin-bottom:10px">
            <div style="display:flex;gap:10px;align-items:baseline">
                <b style="font-size:18px;flex:1">{{ $r->room_type_name }} · {{ $r->plan_label }}</b>
                <span class="pill a">{{ __($r->status_label) }}</span>
            </div>
            <div style="margin-top:4px"><b>{{ $r->guest_name ?? '—' }}</b>
                @if($r->guest_phone) · <a href="tel:{{ $r->guest_phone }}">{{ $r->guest_phone }}</a>@endif</div>
            <div style="color:var(--muted);font-size:13.5px">
                {{ $r->starts_on?->format('d/m/Y') }}@if($r->start_time) {{ substr((string) $r->start_time, 0, 5) }}@endif
                @if($r->plan_kind === 'night') · {{ trans_choice(':n nuit|:n nuits', $r->nights, ['n' => $r->nights]) }}@endif
                @if($r->guests) · {{ trans_choice(':n personne|:n personnes', $r->guests, ['n' => $r->guests]) }}@endif
                · {{ \Modules\Tagtoa\App\Support\Money::format((float) $r->total, $r->currency) }}
            </div>
            @if($r->note)<div style="font-size:13px;white-space:pre-line;margin-top:4px">{{ $r->note }}</div>@endif
            <div style="color:var(--muted);font-size:12px;margin-top:4px">{{ $r->reference }} · {{ $r->created_at?->format('d/m H:i') }}</div>
            <div style="margin-top:8px">
                <form method="POST" action="{{ route('tagtoa.hotel.board.reservation.status', $r->id) }}" style="display:inline">
                    @csrf <input type="hidden" name="to" value="confirmed">
                    <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Confirmer') }}</button>
                </form>
                <details style="display:inline-block">
                    <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex">{{ __('Refuser') }}</summary>
                    <form method="POST" action="{{ route('tagtoa.hotel.board.reservation.status', $r->id) }}" style="margin-top:6px">
                        @csrf <input type="hidden" name="to" value="refused">
                        <input class="inp" name="reason" maxlength="200" placeholder="{{ __('Motif (visible du client)') }}">
                        <button class="btn btn-o btn-sm" style="margin-top:6px">{{ __('Refuser cette demande') }}</button>
                    </form>
                </details>
            </div>
        </div>
    @endforeach
    @if($requests->isNotEmpty())<h3 style="margin:14px 0 6px"><i class="fa-solid fa-bell-concierge"></i> {{ __('Demandes des chambres') }}</h3>@endif
@endif

@if($requests->isEmpty())
    @if($resas->isEmpty())<div class="card"><div class="empty"><i class="fa-solid fa-circle-check"></i>{{ __('Rien à traiter pour le moment.') }}</div></div>@endif
@else
    @foreach($requests as $r)
        <div class="card" style="margin-bottom:10px">
            <div style="display:flex;gap:10px;align-items:baseline">
                <b style="font-size:22px">{{ optional($rooms->get($r->room_id))->number ?? '—' }}</b>
                <span style="flex:1"><b>{{ $r->service_name }}</b></span>
                <span class="pill {{ $r->status === 'new' ? 'g' : 'n' }}">{{ __($statuses[$r->status] ?? $r->status) }}</span>
            </div>
            @if($r->note)<div style="color:var(--muted);font-size:13.5px;white-space:pre-line;margin-top:4px">{{ $r->note }}</div>@endif
            <div style="color:var(--muted);font-size:12px;margin-top:4px">{{ $r->reference }} · {{ $r->created_at?->format('d/m H:i') }}</div>
            <div style="margin-top:8px">
                @foreach(($next[$r->status] ?? []) as $to)
                    @if($to === 'rejected')
                        <details style="display:inline-block">
                            <summary class="btn btn-o btn-sm" style="cursor:pointer;display:inline-flex">{{ __($statuses[$to]) }}</summary>
                            <form method="POST" action="{{ route('tagtoa.hotel.board.status', $r->id) }}" style="margin-top:6px">
                                @csrf <input type="hidden" name="status" value="rejected">
                                <input class="inp" name="reason" maxlength="200" placeholder="{{ __('Motif (visible par l\'invité)') }}">
                                <button class="btn btn-o btn-sm" style="margin-top:6px">{{ __('Confirmer') }}</button>
                            </form>
                        </details>
                    @else
                        <form method="POST" action="{{ route('tagtoa.hotel.board.status', $r->id) }}" style="display:inline">
                            @csrf <input type="hidden" name="status" value="{{ $to }}">
                            <button class="btn {{ $to === 'done' ? 'btn-p' : 'btn-o' }} btn-sm">{{ __($statuses[$to]) }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
@endif

<script>
(function(){
    var dernier = {{ (int) $requests->max('id') }};
    var dernierResa = {{ (int) $resas->max('id') }};
    function verifier(){
        fetch(@json(route('tagtoa.hotel.board.alerts')), {headers:{'Accept':'application/json'}, credentials:'same-origin'})
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(j){
                if (!j) return;
                if (j.latest_id > dernier || (j.resa_latest_id || 0) > dernierResa) { if (window.TagtoaSound) { TagtoaSound.alerte(8); } location.reload(); }
            }).catch(function(){});
    }
    setInterval(verifier, 15000);
})();
</script>
@endsection

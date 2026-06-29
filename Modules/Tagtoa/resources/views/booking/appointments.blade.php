@extends('tagtoa::layouts.dashboard')
@php use Modules\Tagtoa\App\Support\Money; @endphp
@section('title', __('Rendez-vous'))
@section('page', __('Rendez-vous') . ' — ' . $page->name)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.booking.dashboard.edit',$page->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Rendez-vous') }} @if($pending)<span class="pill a">{{ $pending }} {{ __('en attente') }}</span>@endif</h2>
    <a href="{{ url('/book/'.$page->alias) }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-eye"></i> {{ __('Voir') }}</a>
</div>

@if($bookings->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-calendar-day"></i>{{ __('Aucun rendez-vous.') }}</div></div>
@else
    @foreach($bookings as $b)
        @php $sm = $b->status_meta; @endphp
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
                <div>
                    <b style="font-family:var(--fh);font-size:15px">{{ $b->reference }}</b>
                    <span class="pill {{ $sm['pill'] }}">{{ __($sm['label']) }}</span>
                    <div style="color:var(--muted);font-size:13px;margin-top:4px">
                        <i class="fa-solid fa-clock"></i> {{ optional($b->starts_at)->format('d/m/Y H:i') }}
                        @if($b->service) · {{ $b->service->name }}@endif
                    </div>
                    <div style="color:var(--muted);font-size:13px;margin-top:2px">
                        {{ $b->customer_name }}
                        @if($b->customer_phone) · {{ $b->customer_phone }}@endif
                        @if($b->customer_email) · {{ $b->customer_email }}@endif
                    </div>
                </div>
                @if((float) $b->price > 0)<b style="font-family:var(--fh);font-size:17px;color:var(--blue)">{{ Money::format($b->price, $b->currency) }}</b>@endif
            </div>
            @if($b->note)<p style="color:var(--muted);font-size:13px;margin-top:8px"><i class="fa-solid fa-note-sticky"></i> {{ $b->note }}</p>@endif

            <div class="row" style="margin-top:14px;gap:8px;align-items:flex-end">
                <form method="POST" action="{{ route('tagtoa.booking.dashboard.bookings.status',$b->id) }}" style="display:flex;gap:8px;align-items:center;flex:1">
                    @csrf
                    <select class="sel" name="status" style="max-width:200px">
                        @foreach(\Modules\Tagtoa\App\Models\Booking\Booking::STATUS_META as $k=>$m)
                            <option value="{{ $k }}" @selected($b->status===$k)>{{ __($m['label']) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-rotate"></i> {{ __('Mettre à jour') }}</button>
                </form>
            </div>
        </div>
    @endforeach
    <div style="margin-top:16px">{{ $bookings->links() }}</div>
@endif
@endsection

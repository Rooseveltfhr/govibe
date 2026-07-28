@extends('tagtoa::layouts.dashboard')
@section('title', __('Réservations'))
@section('page', __('Réservations'))

@section('content')
<div class="h-row">
    <h2>{{ __('Vos pages de réservation') }}</h2>
    <a href="{{ route('tagtoa.booking.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Nouvelle page') }}</a>
</div>

@if($pages->isEmpty())
    <div class="card"><div class="empty">
        <i class="fa-solid fa-calendar-check"></i>
        {{ __('Aucune page de réservation. Laissez vos clients prendre rendez-vous en ligne par NFC/QR — salon, clinique, coach, consultant.') }}
        <div style="margin-top:16px"><a href="{{ route('tagtoa.booking.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Créer ma page') }}</a></div>
    </div></div>
@else
    <div class="grid g2">
        @foreach($pages as $p)
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start">
                    <div style="display:flex;gap:12px;align-items:center">
                        <div class="ic" style="width:44px;height:44px;border-radius:12px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:18px"><i class="fa-solid fa-calendar-check"></i></div>
                        <div>
                            <b style="font-family:var(--fh);font-size:16px;display:block">{{ $p->name }}</b>
                            @if($p->tagline)<span style="font-size:12.5px;color:var(--muted)">{{ $p->tagline }}</span>@endif
                        </div>
                    </div>
                    <span class="pill {{ $p->is_active ? 'g' : 'n' }}">{{ $p->is_active ? __('Actif') : __('Inactif') }}</span>
                </div>
                <a href="{{ url('/book/'.$p->alias) }}" target="_blank" style="color:var(--blue);font-size:13px;display:inline-block;margin-top:12px">tagtoa.com/book/{{ $p->alias }} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <div style="display:flex;gap:16px;margin-top:10px;color:var(--muted);font-size:13px">
                    <span><i class="fa-solid fa-list-check"></i> {{ $p->services_count }} {{ __('prestations') }}</span>
                    <span><i class="fa-solid fa-calendar-day"></i> {{ $p->bookings_count }} {{ __('rendez-vous') }}</span>
                    <span><i class="fa-solid fa-eye"></i> {{ $p->views }}</span>
                </div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.booking.dashboard.bookings',$p->id) }}" class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-calendar-days"></i> {{ __('Rendez-vous') }}</a>
                    <a href="{{ route('tagtoa.booking.dashboard.edit',$p->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                    <a href="{{ url('/book/'.$p->alias) }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-eye"></i> {{ __('Voir') }}</a>
                    <form method="POST" action="{{ route('tagtoa.booking.dashboard.destroy',$p->id) }}" onsubmit="return confirm('{{ __('Supprimer cette page ?') }}')" style="flex:0">@csrf @method('DELETE')<button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px">{{ $pages->links() }}</div>
@endif
@endsection

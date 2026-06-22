@extends('tagtoa::layouts.dashboard')
@section('title', __('Menu'))
@section('page', __('Menu'))

@section('content')
<div class="h-row">
    <h2>{{ __('Vos menus digitaux') }}</h2>
    <a href="{{ route('tagtoa.menu.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Nouveau menu') }}</a>
</div>

@if($menus->isEmpty())
    <div class="card"><div class="empty">
        <i class="fa-solid fa-utensils"></i>
        {{ __('Aucun menu. Créez le menu digital de votre restaurant, club, lounge ou hôtel — vendez vos produits & services par NFC/QR.') }}
        <div style="margin-top:16px"><a href="{{ route('tagtoa.menu.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Créer mon menu') }}</a></div>
    </div></div>
@else
    <div class="grid g2">
        @foreach($menus as $m)
            @php $tm = $m->type_meta; @endphp
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start">
                    <div style="display:flex;gap:12px;align-items:center">
                        <div class="ic" style="width:44px;height:44px;border-radius:12px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:18px"><i class="{{ $tm['icon'] }}"></i></div>
                        <div>
                            <b style="font-family:var(--fh);font-size:16px;display:block">{{ $m->name }}</b>
                            <span style="font-size:12.5px;color:var(--muted)">{{ __($tm['label']) }}</span>
                        </div>
                    </div>
                    <span class="pill {{ $m->is_active ? 'g' : 'n' }}">{{ $m->is_active ? __('Actif') : __('Inactif') }}</span>
                </div>
                <a href="{{ url('/menu/'.$m->alias) }}" target="_blank" style="color:var(--blue);font-size:13px;display:inline-block;margin-top:12px">tagtoa.com/menu/{{ $m->alias }} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <div style="display:flex;gap:16px;margin-top:10px;color:var(--muted);font-size:13px">
                    <span><i class="fa-solid fa-layer-group"></i> {{ $m->categories_count }} {{ __('cat.') }}</span>
                    <span><i class="fa-solid fa-bowl-food"></i> {{ $m->items_count }} {{ __('produits') }}</span>
                    <span><i class="fa-solid fa-eye"></i> {{ $m->views }}</span>
                </div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.menu.dashboard.edit',$m->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                    <a href="{{ url('/menu/'.$m->alias) }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-eye"></i> {{ __('Voir') }}</a>
                    <form method="POST" action="{{ route('tagtoa.menu.dashboard.destroy',$m->id) }}" onsubmit="return confirm('{{ __('Supprimer ce menu ?') }}')" style="flex:0">@csrf @method('DELETE')<button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px">{{ $menus->links() }}</div>
@endif
@endsection

@extends('tagtoa::layouts.dashboard')
@section('title', __('Site web'))
@section('page', __('Site web'))

@section('content')
<div class="h-row">
    <h2>{{ __('Vos sites web') }}</h2>
    <a href="{{ route('tagtoa.site.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Nouveau site') }}</a>
</div>

@if($sites->isEmpty())
    <div class="card"><div class="empty">
        <i class="fa-solid fa-globe"></i>
        {{ __('Aucun site. Créez le site web professionnel de votre business — vitrine, services, contact, par abonnement.') }}
        <div style="margin-top:16px"><a href="{{ route('tagtoa.site.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Créer mon site') }}</a></div>
    </div></div>
@else
    <div class="grid g2">
        @foreach($sites as $s)
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
                    <b style="font-family:var(--fh);font-size:16px">{{ $s->name }}</b>
                    <span class="pill {{ $s->is_published ? 'g' : 'n' }}">{{ $s->is_published ? __('Publié') : __('Brouillon') }}</span>
                </div>
                @if($s->tagline)<div style="color:var(--muted);font-size:13.5px;margin-top:2px">{{ $s->tagline }}</div>@endif
                <a href="{{ url('/site/'.$s->alias) }}" target="_blank" style="color:var(--blue);font-size:13px;display:inline-block;margin-top:10px">tagtoa.com/site/{{ $s->alias }} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <div style="display:flex;gap:16px;margin-top:10px;color:var(--muted);font-size:13px"><span><i class="fa-solid fa-eye"></i> {{ $s->views }}</span></div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.site.dashboard.edit',$s->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                    <a href="{{ url('/site/'.$s->alias) }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-eye"></i> {{ __('Voir') }}</a>
                    <form method="POST" action="{{ route('tagtoa.site.dashboard.destroy',$s->id) }}" onsubmit="return confirm('{{ __('Supprimer ce site ?') }}')" style="flex:0">@csrf @method('DELETE')<button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px">{{ $sites->links() }}</div>
@endif
@endsection

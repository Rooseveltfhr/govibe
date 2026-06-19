@extends('tagtoa::layouts.dashboard')
@section('title', __('Liens'))
@section('page', __('Liens'))

@section('content')
<div class="h-row">
    <h2>{{ __('Vos pages de liens') }}</h2>
    <a href="{{ route('tagtoa.links.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Nouvelle page') }}</a>
</div>
@if($pages->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-link"></i>{{ __('Aucune page de liens. Créez votre page style Linktree.') }}</div></div>
@else
    <div class="grid g2">
        @foreach($pages as $p)
            <div class="card">
                <div style="display:flex;justify-content:space-between"><b style="font-family:var(--fh);font-size:16px">{{ $p->title ?: $p->alias }}</b><span class="pill {{ $p->is_active ? 'g' : 'n' }}">{{ $p->is_active ? __('Active') : __('Inactive') }}</span></div>
                <a href="{{ url('/links/'.$p->alias) }}" target="_blank" style="color:var(--blue);font-size:13px">tagtoa.com/links/{{ $p->alias }} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <div style="display:flex;gap:16px;margin-top:12px;color:var(--muted);font-size:13px"><span><i class="fa-solid fa-link"></i> {{ $p->links_count }}</span><span><i class="fa-solid fa-eye"></i> {{ $p->views }}</span></div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.links.dashboard.edit',$p->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                    <form method="POST" action="{{ route('tagtoa.links.dashboard.destroy',$p->id) }}" onsubmit="return confirm('{{ __('Supprimer?') }}')" style="flex:0">@csrf @method('DELETE')<button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button></form>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px">{{ $pages->links() }}</div>
@endif
@endsection

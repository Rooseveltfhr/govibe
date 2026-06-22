@extends('tagtoa::layouts.dashboard')
@section('title', __('Paiements'))
@section('page', __('Paiements'))

@section('content')
<div class="h-row">
    <h2>{{ __('Vos pages de paiement') }}</h2>
    <a href="{{ route('tagtoa.pay.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Nouvelle page') }}</a>
</div>

@if($pages->isEmpty())
    <div class="card"><div class="empty"><i class="fa-regular fa-credit-card"></i>{{ __('Aucune page. Créez la première pour recevoir des paiements (MonCash, NatCash, Zelle…).') }}</div></div>
@else
    <div class="grid g2">
        @foreach($pages as $p)
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:start">
                    <b style="font-family:var(--fh);font-size:16px">{{ $p->title ?: $p->alias }}</b>
                    <span class="pill {{ $p->is_active ? 'g' : 'n' }}">{{ $p->is_active ? __('Active') : __('Inactive') }}</span>
                </div>
                <a href="{{ url('/pay/'.$p->alias) }}" target="_blank" style="color:var(--blue);font-size:13px">tagtoa.com/pay/{{ $p->alias }} <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                <div style="display:flex;gap:16px;margin-top:12px;color:var(--muted);font-size:13px">
                    <span><i class="fa-solid fa-wallet"></i> {{ $p->methods_count }} {{ __('méthodes') }}</span>
                    <span><i class="fa-solid fa-eye"></i> {{ $p->views }}</span>
                    @if($p->proofs_count)<span style="color:var(--blue-deep);font-weight:700"><i class="fa-solid fa-receipt"></i> {{ $p->proofs_count }}</span>@endif
                </div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.pay.dashboard.edit',$p->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                    <a href="{{ route('tagtoa.pay.dashboard.proofs',$p->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-receipt"></i> {{ __('Preuves') }}</a>
                    <form method="POST" action="{{ route('tagtoa.pay.dashboard.destroy',$p->id) }}" onsubmit="return confirm('{{ __('Supprimer?') }}')" style="flex:0">
                        @csrf @method('DELETE')<button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px">{{ $pages->links() }}</div>
@endif
@endsection

@extends('tagtoa::layouts.dashboard')
@section('title', __('Preuves'))
@section('page', __('Preuves').' — '.($page->title ?: $page->alias))

@section('content')
<a href="{{ route('tagtoa.pay.dashboard.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>

@if($proofs->isEmpty())
    <div class="card" style="margin-top:14px"><div class="empty"><i class="fa-regular fa-receipt"></i>{{ __('Aucune preuve reçue.') }}</div></div>
@else
    <div style="margin-top:14px;display:flex;flex-direction:column;gap:12px">
    @foreach($proofs as $pr)
        @php $cls=[0=>'a',1=>'g',2=>'r'][$pr->status] ?? 'n'; @endphp
        <div class="card">
            <div style="display:flex;justify-content:space-between;gap:14px">
                <div>
                    <b style="font-family:var(--fh)">{{ $pr->payer_name }}</b> <span class="pill {{ $cls }}">{{ $pr->status_label }}</span>
                    <div style="color:var(--muted);font-size:13px;margin-top:4px">
                        <i class="fa-solid {{ optional($pr->method)->icon ?? 'fa-money-check-dollar' }}"></i> {{ optional($pr->method)->display_label }}
                        @if($pr->payer_phone) · <i class="fa-brands fa-whatsapp"></i> {{ $pr->payer_phone }}@endif
                    </div>
                    <div style="font-size:14px;margin-top:4px">
                        @if($pr->amount)<b>{{ number_format($pr->amount,2) }} {{ $pr->currency }}</b>@endif
                        @if($pr->reference) · {{ __('Réf') }}: {{ $pr->reference }}@endif · {{ $pr->created_at->diffForHumans() }}
                    </div>
                    @if($pr->note)<div style="color:var(--muted);font-size:13px;margin-top:4px"><i class="fa-solid fa-note-sticky"></i> {{ $pr->note }}</div>@endif
                </div>
                @if($pr->image_url)<a href="{{ $pr->image_url }}" target="_blank"><img src="{{ $pr->image_url }}" loading="lazy" style="width:84px;height:84px;object-fit:cover;border-radius:10px;border:1px solid var(--bd)"></a>@endif
            </div>
            @if($pr->isPending())
                <div class="row" style="margin-top:12px;gap:8px">
                    <form method="POST" action="{{ route('tagtoa.pay.dashboard.proofs.approve',$pr->id) }}" style="flex:0">@csrf<button class="btn btn-sm" style="background:var(--green);color:#fff"><i class="fa-solid fa-check"></i> {{ __('Approuver') }}</button></form>
                    <form method="POST" action="{{ route('tagtoa.pay.dashboard.proofs.reject',$pr->id) }}" style="display:flex;gap:8px;flex:1">@csrf<input name="note" class="inp" placeholder="{{ __('Raison (optionnel)') }}"><button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-xmark"></i> {{ __('Rejeter') }}</button></form>
                </div>
            @endif
        </div>
    @endforeach
    </div>
    <div style="margin-top:16px">{{ $proofs->links() }}</div>
@endif
@endsection

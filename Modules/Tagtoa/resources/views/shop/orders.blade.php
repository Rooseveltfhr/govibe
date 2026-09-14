@extends('tagtoa::layouts.dashboard')
@section('title', __('Mes commandes'))
@section('page', __('Mes commandes TAGTOA'))

@push('head')
<style>
.cmd{padding:14px 0}
.cmd + .cmd{border-top:1px solid var(--bd)}
.cmd .tete{display:flex;gap:11px;align-items:center;flex-wrap:wrap}
.cmd .tete b{font:700 14.5px var(--fh)}
.cmd .li{font-size:13px;color:var(--muted);margin-top:6px}
.cmd .rep{font-size:13px;background:var(--blue-pale);color:#1a4d05;border-radius:10px;padding:9px 11px;margin-top:9px}
</style>
@endpush

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.shop.index') }}" class="btn btn-p btn-sm">
        <i class="fa-solid fa-cart-shopping"></i> {{ __('Commander du matériel') }}
    </a>
</div>

<div class="card">
    @forelse($orders as $o)
        <div class="cmd">
            <div class="tete">
                <b>{{ strtoupper(substr($o->reference, 0, 8)) }}</b>
                <span class="pill {{ $o->status === 'completed' ? 'g' : ($o->status === 'cancelled' ? 'r' : 'a') }}">
                    {{ __($o->status_label) }}
                </span>
                <span style="color:var(--muted);font-size:12.5px">{{ optional($o->placed_at)->format('d/m/Y') }}</span>
                <span style="flex:1"></span>
                <b style="font:700 15px var(--fh)">
                    {{ \Modules\Tagtoa\App\Support\Money::format($o->total, $o->currency) }}
                </b>
            </div>
            <div class="li">
                @foreach($o->items as $i)
                    {{ $i->name }} × {{ $i->qty }}@if(! $loop->last) · @endif
                @endforeach
                @if((float) $o->shipping > 0)
                    <br>{{ __('dont transport') }} {{ \Modules\Tagtoa\App\Support\Money::format($o->shipping, $o->currency) }}
                @endif
            </div>
            @if($o->reply)
                <div class="rep"><i class="fa-solid fa-comment-dots"></i> {{ $o->reply }}</div>
            @endif
        </div>
    @empty
        <div class="empty" style="padding:36px 16px">
            <i class="fa-solid fa-clipboard-list"></i>
            {{ __('Aucune commande. Le matériel TAGTOA se commande depuis la boutique.') }}
        </div>
    @endforelse
    @if($orders->hasPages())<div style="margin-top:14px">{{ $orders->links() }}</div>@endif
</div>
@endsection

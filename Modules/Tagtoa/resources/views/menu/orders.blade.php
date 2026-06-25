@extends('tagtoa::layouts.dashboard')
@php use Modules\Tagtoa\App\Support\Money; @endphp
@section('title', __('Commandes'))
@section('page', __('Commandes') . ' — ' . $menu->name)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.menu.dashboard.edit',$menu->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Commandes') }} @if($pending)<span class="pill a">{{ $pending }} {{ __('en attente') }}</span>@endif</h2>
    <a href="{{ url('/menu/'.$menu->alias) }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-eye"></i> {{ __('Voir') }}</a>
</div>

@if($orders->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-receipt"></i>{{ __('Aucune commande.') }}</div></div>
@else
    @foreach($orders as $o)
        @php $sm = $o->status_meta; @endphp
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
                <div>
                    <b style="font-family:var(--fh);font-size:15px">{{ $o->reference }}</b>
                    <span class="pill {{ $sm['pill'] }}">{{ __($sm['label']) }}</span>
                    <span class="pill {{ $o->isPaid() ? 'g' : 'n' }}">{{ $o->isPaid() ? __('Payé') : __('En attente') }}</span>
                    <div style="color:var(--muted);font-size:13px;margin-top:4px">
                        {{ optional($o->placed_at)->format('d/m/Y H:i') }}
                        @if($o->customer_name) · {{ $o->customer_name }}@endif
                        @if($o->customer_phone) · {{ $o->customer_phone }}@endif
                        @if($o->table_label) · {{ __('N° table (optionnel)') }} {{ $o->table_label }}@endif
                    </div>
                </div>
                <b style="font-family:var(--fh);font-size:17px;color:var(--blue)">{{ Money::format($o->total, $o->currency) }}</b>
            </div>

            <table style="margin-top:12px">
                <tbody>
                @foreach($o->items as $it)
                    <tr><td style="border:0;padding:4px 0">{{ $it->qty }}× {{ $it->name }}</td><td style="border:0;padding:4px 0;text-align:right;color:var(--muted)">{{ Money::format($it->line_total, $o->currency) }}</td></tr>
                @endforeach
                </tbody>
            </table>
            @if($o->note)<p style="color:var(--muted);font-size:13px;margin-top:8px"><i class="fa-solid fa-note-sticky"></i> {{ $o->note }}</p>@endif

            <div class="row" style="margin-top:14px;gap:8px;align-items:flex-end">
                <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.status',$o->id) }}" style="display:flex;gap:8px;align-items:center;flex:1">
                    @csrf
                    <select class="sel" name="status" style="max-width:200px">
                        @foreach(\Modules\Tagtoa\App\Models\Menu\Order::STATUS_META as $k=>$m)
                            <option value="{{ $k }}" @selected($o->status===$k)>{{ __($m['label']) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-rotate"></i> {{ __('Confirmer') }}</button>
                </form>
                @unless($o->isPaid())
                    <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.paid',$o->id) }}" style="flex:0">
                        @csrf
                        <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Encaisser') }}</button>
                    </form>
                @endunless
            </div>
        </div>
    @endforeach
    <div style="margin-top:16px">{{ $orders->links() }}</div>
@endif
@endsection

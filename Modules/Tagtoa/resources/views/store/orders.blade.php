@extends('tagtoa::layouts.dashboard')
@section('title', __('Commandes boutique'))
@section('page', $store->name)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.store.dashboard.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
</div>

<div class="grid g3">
    <div class="stat"><div class="ic"><i class="fa-solid fa-sack-dollar"></i></div><div class="v">{{ number_format($analytics['revenue'],2) }}</div><div class="k">{{ __('Revenu') }} ({{ $store->currency }})</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-receipt"></i></div><div class="v">{{ $analytics['orders'] }}</div><div class="k">{{ __('Commandes') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-circle-check"></i></div><div class="v">{{ $analytics['paid'] }}</div><div class="k">{{ __('Payées') }}</div></div>
</div>

<div class="card" style="margin-top:16px">
    @if($orders->isEmpty())
        <div class="empty"><i class="fa-regular fa-receipt"></i>{{ __('Aucune commande.') }}</div>
    @else
    <div style="overflow-x:auto"><table>
        <tr><th>{{ __('Réf') }}</th><th>{{ __('Client') }}</th><th>{{ __('Total') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Paiement') }}</th><th>{{ __('Date') }}</th><th></th></tr>
        @foreach($orders as $o)
        <tr>
            <td><b>{{ $o->reference }}</b><div style="color:var(--muted);font-size:12px">{{ $o->items_count }} {{ __('article(s)') }}</div></td>
            <td>{{ $o->customer_name }}<div style="color:var(--muted);font-size:12px">{{ $o->customer_phone }}</div>@if($o->customer_address)<div style="color:var(--muted);font-size:12px"><i class="fa-solid fa-truck"></i> {{ $o->customer_address }}</div>@endif</td>
            <td>{{ number_format($o->total,2) }}</td>
            <td>
                <form method="POST" action="{{ route('tagtoa.store.dashboard.orders.status',$o->id) }}" style="display:flex;gap:6px">@csrf
                    <select name="status" class="sel" style="padding:6px 8px;font-size:13px" onchange="this.form.submit()">
                        @foreach(\Modules\Tagtoa\App\Models\Store\Order::STATUSES as $st)
                            <option value="{{ $st }}" @selected($o->status===$st)>{{ \Modules\Tagtoa\App\Models\Store\Order::STATUS_META[$st]['label'] ?? $st }}</option>
                        @endforeach
                    </select>
                </form>
            </td>
            <td>{!! $o->isPaid() ? '<span class="pill g">'.__('Payée').'</span>' : '<span class="pill a">'.__('Non payée').'</span>' !!}</td>
            <td style="color:var(--muted)">{{ $o->created_at->format('d/m/y H:i') }}</td>
            <td>@unless($o->isPaid())<form method="POST" action="{{ route('tagtoa.store.dashboard.orders.paid',$o->id) }}">@csrf<button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Encaisser') }}</button></form>@endunless</td>
        </tr>
        @endforeach
    </table></div>
    <div style="margin-top:14px">{{ $orders->links() }}</div>
    @endif
</div>
@endsection

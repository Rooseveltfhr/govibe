@extends('tagtoa::layouts.dashboard')
@section('title', __('Super-admin TAGTOA'))
@section('page', __('Super-admin — vue plateforme'))

@section('content')
{{-- Vue CROSS-TENANT réservée au fondateur (super_admin). Lecture seule. --}}

{{-- Compteurs de tête --}}
<div class="grid g3" style="margin-bottom:16px">
    <div class="stat"><div class="ic" style="background:#eef2ff;color:#3344aa"><i class="fa-solid fa-store"></i></div><div class="v">{{ $totals['merchants'] }}</div><div class="k">{{ __('Marchands') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-circle-check"></i></div><div class="v">{{ $totals['active_subscriptions'] }}</div><div class="k">{{ __('Abonnements actifs') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-layer-group"></i></div><div class="v">{{ count($byModule) }}</div><div class="k">{{ __('Modules générateurs') }}</div></div>
</div>

{{-- Revenu par devise --}}
<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2>{{ __('Revenu global par devise') }}</h2></div>
    @forelse($revenue as $r)
        <div class="kv"><span>{{ $r['currency'] }} · {{ $r['count'] }} {{ __('opérations') }}</span><b>{{ number_format($r['gross'], 2) }} {{ $r['currency'] }} <span style="color:#888;font-weight:400">· {{ __('commission') }} {{ number_format($r['commission'], 2) }}</span></b></div>
    @empty
        <p style="color:var(--muted,#888);font-size:14px">{{ __('Aucun revenu enregistré pour le moment.') }}</p>
    @endforelse
</div>

<div class="grid g2" style="gap:16px;margin-bottom:16px">
    {{-- Commissions à régler / réglées --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Commissions') }}</h2></div>
        @forelse($commission as $c)
            <div class="kv"><span>{{ $c['label'] }} ({{ $c['currency'] }})</span><b>{{ number_format($c['amount'], 2) }} {{ $c['currency'] }}</b></div>
        @empty
            <p style="color:#888;font-size:14px">{{ __('Aucune commission.') }}</p>
        @endforelse
    </div>

    {{-- Abonnements par forfait --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Forfaits') }}</h2></div>
        @foreach($plans as $p)
            <div class="kv"><span>{{ ucfirst($p['plan']) }}</span><b>{{ $p['active'] }} {{ __('actifs') }} <span style="color:#888;font-weight:400">/ {{ $p['total'] }}</span></b></div>
        @endforeach
    </div>
</div>

{{-- Revenu par module --}}
<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2>{{ __('Revenu par module') }}</h2></div>
    @forelse($byModule as $m)
        <div class="kv"><span><i class="fa-solid fa-cube" style="opacity:.5"></i> {{ ucfirst($m['module']) }} · {{ $m['count'] }}</span><b>{{ number_format($m['gross'], 2) }}</b></div>
    @empty
        <p style="color:#888;font-size:14px">{{ __('Aucune donnée.') }}</p>
    @endforelse
</div>

{{-- Top marchands --}}
<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2>{{ __('Top marchands') }}</h2></div>
    @forelse($topMerchants as $i => $t)
        <div class="kv"><span><b style="font-family:var(--fh)">#{{ $i + 1 }}</b> · {{ __('Tenant') }} {{ \Illuminate\Support\Str::limit($t['tenant_id'], 14) }} · {{ $t['count'] }}</span><b>{{ number_format($t['gross'], 2) }}</b></div>
    @empty
        <p style="color:#888;font-size:14px">{{ __('Aucun marchand avec revenu.') }}</p>
    @endforelse
</div>

{{-- Liste des abonnements --}}
<div class="card">
    <div class="h-row"><h2>{{ __('Abonnements marchands') }}</h2></div>
    <table style="width:100%;border-collapse:collapse;font-size:14px">
        <thead><tr style="text-align:left;color:#888"><th style="padding:8px 6px">{{ __('Tenant') }}</th><th>{{ __('Forfait') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Expire') }}</th></tr></thead>
        <tbody>
        @forelse($merchants as $s)
            <tr style="border-top:1px solid rgba(0,0,0,.06)">
                <td style="padding:8px 6px;font-family:var(--fh)">{{ \Illuminate\Support\Str::limit($s->tenant_id, 18) }}</td>
                <td>{{ ucfirst($s->plan) }}</td>
                <td>{{ $s->status === 'active' ? '🟢 '.__('Actif') : '⚪ '.$s->status }}</td>
                <td style="color:#888">{{ $s->expires_at ? \Illuminate\Support\Carbon::parse($s->expires_at)->format('d/m/Y') : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="padding:20px;text-align:center;color:#888">{{ __('Aucun abonnement.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:12px">{{ $merchants->links() }}</div>
</div>
@endsection

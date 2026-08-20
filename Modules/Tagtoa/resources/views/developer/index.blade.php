@extends('tagtoa::layouts.dashboard')
@section('title', __('Développeur'))
@section('page', __('API développeur'))

@section('content')
<p style="color:var(--muted);font-size:13.5px;margin-top:-8px;margin-bottom:16px">
    {{ __('Encaissez avec TAGTOA depuis votre propre site : créez une clé, appelez l\'API, redirigez votre client vers la page de paiement. Toutes vos méthodes de paiement TAGTOA fonctionnent automatiquement.') }}
</p>

@if(session('new_api_token'))
    <div class="card" style="border:2px solid #2cb809;margin-bottom:16px">
        <b style="font-family:var(--fh,sans-serif)"><i class="fa-solid fa-key" style="color:#2cb809"></i> {{ __('Votre nouvelle clé') }}</b>
        <p style="color:var(--muted);font-size:13px;margin:6px 0 10px">{{ __('Copiez-la maintenant : pour votre sécurité, elle ne sera plus jamais affichée.') }}</p>
        <div style="display:flex;gap:8px;align-items:center">
            <input class="inp" id="newtok" readonly value="{{ session('new_api_token') }}" style="font-family:monospace;font-size:13px">
            <button type="button" class="btn btn-d btn-sm" style="flex:0" onclick="copyTok()"><i class="fa-solid fa-copy"></i> {{ __('Copier') }}</button>
        </div>
    </div>
@endif

{{-- ---------- Clés ---------- --}}
<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2>{{ __('Vos clés API') }}</h2></div>

    @forelse($keys as $k)
        <div style="display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--bd)">
            <div style="flex:1;min-width:0">
                <b>{{ $k->name }}</b>
                <div style="color:var(--muted);font-size:12.5px;font-family:monospace">{{ $k->masked_key }}</div>
                <div style="color:var(--muted);font-size:12px">
                    {{ __('Créée le') }} {{ $k->created_at?->format('d/m/Y') }}
                    @if($k->last_used_at) · {{ __('dernier appel') }} {{ $k->last_used_at->diffForHumans() }}
                    @else · {{ __('jamais utilisée') }} @endif
                </div>
            </div>
            @if($k->isActive())
                <span class="pill g">{{ __('Active') }}</span>
                <form method="POST" action="{{ route('tagtoa.developer.revoke', $k->id) }}" style="flex:0"
                      onsubmit="return confirm('{{ __('Révoquer cette clé ? Les intégrations qui l\'utilisent cesseront de fonctionner immédiatement.') }}')">
                    @csrf
                    <button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-ban"></i> {{ __('Révoquer') }}</button>
                </form>
            @else
                <span class="pill r">{{ __('Révoquée') }}</span>
            @endif
        </div>
    @empty
        <p style="color:var(--muted);font-size:14px">{{ __('Aucune clé pour le moment.') }}</p>
    @endforelse

    <form method="POST" action="{{ route('tagtoa.developer.store') }}" style="margin-top:14px">
        @csrf
        <div class="row">
            <div><label class="lbl">{{ __('Nom de la clé') }}</label><input class="inp" name="name" maxlength="120" placeholder="{{ __('Ex. Mon site e-commerce') }}"></div>
            <div><label class="lbl">{{ __('URL de notification') }} <span style="font-weight:400;color:var(--muted)">({{ __('optionnel') }})</span></label><input class="inp" name="webhook_url" type="url" placeholder="https://monsite.com/tagtoa/webhook"></div>
        </div>
        <button class="btn btn-p btn-sm" style="margin-top:10px"><i class="fa-solid fa-plus"></i> {{ __('Créer une clé') }}</button>
    </form>
</div>

{{-- ---------- Documentation ---------- --}}
@php
    $sample = $keys->first()?->key_prefix.'_…' ?: 'tag_live_xxxxxxxx_…';
@endphp
<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2>{{ __('Intégration en 3 étapes') }}</h2></div>

    <p style="font-size:14px"><b>{{ __('1. Créer un paiement') }}</b> — {{ __('depuis votre serveur, à la validation du panier.') }}</p>
<pre style="background:#0d140c;color:#d6f5cc;padding:14px;border-radius:11px;overflow-x:auto;font-size:12.5px;line-height:1.6"><code>curl -X POST {{ $baseUrl }}/payments \
  -H "Authorization: Bearer {{ $sample }}" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 2500,
    "currency": "HTG",
    "external_id": "commande-1042",
    "description": "Commande #1042",
    "return_url": "https://monsite.com/merci",
    "callback_url": "https://monsite.com/tagtoa/webhook"
  }'</code></pre>

    <p style="font-size:14px;margin-top:14px"><b>{{ __('2. Rediriger le client') }}</b> — {{ __('la réponse contient checkout_url.') }}</p>
<pre style="background:#0d140c;color:#d6f5cc;padding:14px;border-radius:11px;overflow-x:auto;font-size:12.5px;line-height:1.6"><code>{
  "ok": true,
  "payment": {
    "reference": "API-XXXXXXXXXXXX",
    "status": "pending",
    "checkout_url": "{{ rtrim(url('/pay/i'), '/') }}/API-XXXXXXXXXXXX"
  }
}</code></pre>

    <p style="font-size:14px;margin-top:14px"><b>{{ __('3. Confirmer le paiement') }}</b> — {{ __('deux options, au choix.') }}</p>
    <ul style="font-size:14px;color:var(--muted,#555);padding-left:20px;line-height:1.8">
        <li>{{ __('Interroger') }} <code>GET {{ $baseUrl }}/payments/{reference}</code> {{ __('jusqu\'à ce que status vaille « paid ».') }}</li>
        <li>{{ __('Ou recevoir la notification sur votre callback_url : TAGTOA envoie le même objet en POST, signé dans l\'en-tête') }} <code>X-Tagtoa-Signature</code> (HMAC-SHA256).</li>
    </ul>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="h-row"><h2>{{ __('Tous les endpoints') }}</h2></div>
    <div style="overflow-x:auto">
    <table style="width:100%;min-width:560px;font-size:13.5px">
        <thead><tr><th style="text-align:left">{{ __('Méthode') }}</th><th style="text-align:left">{{ __('Endpoint') }}</th><th style="text-align:left">{{ __('Rôle') }}</th></tr></thead>
        <tbody>
            <tr><td><b>GET</b></td><td><code>/ping</code></td><td>{{ __('Vérifier la clé') }}</td></tr>
            <tr><td><b>GET</b></td><td><code>/payment-methods</code></td><td>{{ __('Méthodes que verra le client') }}</td></tr>
            <tr><td><b>POST</b></td><td><code>/payments</code></td><td>{{ __('Créer un paiement') }}</td></tr>
            <tr><td><b>GET</b></td><td><code>/payments/{reference}</code></td><td>{{ __('État d\'un paiement') }}</td></tr>
        </tbody>
    </table>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin-top:10px">
        {{ __('Base') }} : <code>{{ $baseUrl }}</code> · {{ __('Authentification') }} : <code>Authorization: Bearer VOTRE_CLÉ</code> ·
        {{ __('Limite : 120 appels par minute et par clé.') }}
    </p>
    <p style="color:#7a5200;font-size:12.5px;margin-top:6px">
        <i class="fa-solid fa-triangle-exclamation"></i>
        {{ __('Rappel : appelez l\'API depuis votre SERVEUR uniquement. Une clé placée dans du code JavaScript est visible par tout le monde.') }}
    </p>
</div>

{{-- ---------- Paiements récents ---------- --}}
<div class="card">
    <div class="h-row"><h2>{{ __('Paiements API récents') }}</h2></div>
    @forelse($payments as $p)
        <div class="kv">
            <span>
                <b style="font-family:monospace;font-size:12.5px">{{ $p->reference }}</b>
                @if($p->external_id)<span style="color:var(--muted);font-size:12px"> · {{ $p->external_id }}</span>@endif
            </span>
            <b>
                {{ \Modules\Tagtoa\App\Support\Money::format($p->amount, $p->currency) }}
                <span class="pill {{ $p->isPaid() ? 'g' : 'a' }}">{{ $p->isPaid() ? __('Payé') : __('En attente') }}</span>
            </b>
        </div>
    @empty
        <p style="color:var(--muted);font-size:14px">{{ __('Aucun paiement créé via l\'API pour le moment.') }}</p>
    @endforelse
</div>
@push('scripts')
<script>
    function copyTok(){
        var el = document.getElementById('newtok');
        el.select(); el.setSelectionRange(0, 99999);
        try { document.execCommand('copy'); } catch (e) {}
    }
</script>
@endpush
@endsection

@extends('tagtoa::layouts.dashboard')
@section('title', __('Partager le lien'))
@section('page', __('Votre lien de paiement'))

@php
    $url  = url('/pay/'.$page->alias);
    $isDon = $page->type === \Modules\Tagtoa\App\Models\Pay\PaymentPage::TYPE_DONATION;
    $amountLabel = $page->hasFixedAmount()
        ? \Modules\Tagtoa\App\Support\Money::format($page->amount, $page->default_currency)
        : __('Montant libre');
    $waText = rawurlencode(($page->title ?: __('Paiement'))."\n".$amountLabel."\n".$url);
@endphp

@section('content')
<div class="card" style="border-left:4px solid #2cb809">
    <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
            <span class="pill {{ $isDon ? 'a' : 'g' }}">{{ __($isDon ? 'Don / soutien' : 'Facture / paiement') }}</span>
            <h2 style="font-family:var(--fh,sans-serif);font-size:19px;margin:8px 0 2px">{{ $page->title ?: __('Sans titre') }}</h2>
            <div style="color:var(--muted);font-size:14px">{{ $amountLabel }}</div>
            @if($page->description)
                <p style="color:var(--muted);font-size:13.5px;margin-top:8px">{{ $page->description }}</p>
            @endif
        </div>
        @if(! $page->is_active)
            <span class="pill r">{{ __('Lien désactivé') }}</span>
        @endif
    </div>

    <label class="lbl">{{ __('Lien à partager') }}</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input class="inp" id="paylink" readonly value="{{ $url }}" style="flex:1;min-width:220px;font-family:monospace;font-size:13px">
        <button type="button" class="btn btn-d" onclick="copyLink(this)"><i class="fa-solid fa-copy"></i> {{ __('Copier') }}</button>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
        <a class="btn btn-p" href="https://wa.me/?text={{ $waText }}" target="_blank" rel="noopener">
            <i class="fa-brands fa-whatsapp"></i> {{ __('Envoyer sur WhatsApp') }}
        </a>
        <a class="btn btn-o" href="{{ $url }}" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> {{ __('Voir la page') }}</a>
        <a class="btn btn-o" href="{{ route('tagtoa.pay.dashboard.edit',$page->id) }}"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
        <a class="btn btn-o" href="{{ route('tagtoa.pay.dashboard.proofs',$page->id) }}"><i class="fa-solid fa-receipt"></i> {{ __('Paiements reçus') }}</a>
    </div>
</div>

@if(\Illuminate\Support\Facades\Route::has('tagtoa.qr.index'))
    <div class="card">
        <div class="h-row"><h2>{{ __('QR Code') }}</h2></div>
        <p style="color:var(--muted);font-size:13.5px;margin-top:-8px">
            {{ __('Imprimez-le sur une affiche, une table ou une facture : le client scanne et paie.') }}
        </p>
        <a class="btn btn-o btn-sm" href="{{ route('tagtoa.qr.index') }}"><i class="fa-solid fa-qrcode"></i> {{ __('Générer le QR de ce lien') }}</a>
    </div>
@endif

<div class="card">
    <div class="h-row"><h2>{{ __('Moyens de paiement proposés') }}</h2></div>
    <p style="color:var(--muted);font-size:13.5px;margin-top:-8px">
        {{ __('Ce lien propose automatiquement les moyens que vous avez configurés une fois pour toutes.') }}
    </p>
    <a class="btn btn-o btn-sm" href="{{ route('tagtoa.pay.methods') }}"><i class="fa-solid fa-sliders"></i> {{ __('Mes moyens de paiement') }}</a>
</div>

@push('scripts')
<script>
    function copyLink(btn){
        var el = document.getElementById('paylink');
        el.select(); el.setSelectionRange(0, 99999);
        try {
            document.execCommand('copy');
            var old = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> {{ __('Copié') }}';
            setTimeout(function(){ btn.innerHTML = old; }, 1800);
        } catch (e) {}
    }
</script>
@endpush
@endsection

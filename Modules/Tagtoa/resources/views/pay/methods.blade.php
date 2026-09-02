@extends('tagtoa::layouts.dashboard')
@section('title', __('Mes moyens de paiement'))
@section('page', __('Mes moyens de paiement'))

@section('content')
<div class="h-row" style="gap:8px;margin-bottom:14px">
    <a href="{{ route('tagtoa.pay.dashboard.index') }}" class="btn btn-o btn-sm"><i class="fa-solid fa-arrow-left"></i> {{ __('Mes liens') }}</a>
</div>

<div class="card" style="border-left:4px solid #2cb809;margin-bottom:16px">
    <b style="font-family:var(--fh,sans-serif)"><i class="fa-solid fa-circle-info" style="color:#2cb809"></i> {{ __('À configurer une seule fois') }}</b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px">
        {{ __('Ces moyens de paiement s\'appliquent à TOUS vos liens. Vous n\'aurez plus à ressaisir vos coordonnées à chaque nouveau lien — et si vous changez un numéro ici, il change partout.') }}
    </p>
</div>

<form method="POST" action="{{ route('tagtoa.pay.methods.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    {{-- ---------- AUTOMATIQUE : clés gérées par TAGTOA ---------- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Paiement automatique') }}</h2></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">
            {{ __('Le client paie en ligne et le paiement est confirmé tout seul. Les identifiants API sont gérés par TAGTOA — vous choisissez simplement ce que vous voulez proposer.') }}
        </p>
        @foreach($catalog['auto'] as $type => $meta)
            @include('tagtoa::pay.partials.method-row', ['type' => $type, 'meta' => $meta, 'm' => $saved->get($type)])
        @endforeach
    </div>

    {{-- ---------- MANUEL : coordonnées du marchand ---------- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Paiement manuel (vos comptes)') }}</h2></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">
            {{ __('Le client paie directement sur votre compte puis envoie une preuve que vous approuvez. L\'argent ne passe jamais par TAGTOA.') }}
        </p>
        @foreach($catalog['manual'] as $type => $meta)
            @include('tagtoa::pay.partials.method-row', ['type' => $type, 'meta' => $meta, 'm' => $saved->get($type)])
        @endforeach
    </div>

    <button class="btn btn-p" style="margin-top:4px"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer mes moyens de paiement') }}</button>
</form>

<style>
    .gwrow{border:1px solid var(--bd);border-radius:12px;margin-top:10px;overflow:hidden}
    .gwhead{display:flex;align-items:center;gap:12px;padding:12px 14px;cursor:pointer;margin:0}
    .gwicon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;flex:0 0 34px}
    .gwname{font-weight:700;font-size:14.5px;flex:1}
    .gwtag{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.03em}
    .gwtag.ok{background:#eafaf3;color:#0e5f44}
    .gwtag.off{background:#fff5e6;color:#7a5200}
    .gwbody{padding:0 14px 14px;border-top:1px solid var(--bd)}
    .sw{position:relative;width:46px;height:26px;flex:0 0 46px}
    .sw input{opacity:0;width:0;height:0;position:absolute}
    .sw span{position:absolute;inset:0;background:#c9d2c6;border-radius:999px;transition:.18s}
    .sw span::before{content:"";position:absolute;width:20px;height:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.18s}
    .sw input:checked + span{background:#2cb809}
    .sw input:checked + span::before{transform:translateX(20px)}
</style>
@push('scripts')
<script>
    function gwToggle(cb){
        var body = cb.closest('.gwrow').querySelector('.gwbody');
        if (body) { body.style.display = cb.checked ? '' : 'none'; }
    }
</script>
@endpush
@endsection

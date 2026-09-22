@extends('tagtoa::layouts.dashboard')
@php $editing = $menu->exists; @endphp
@section('title', $editing ? __('Modifier le menu') : __('Nouveau menu'))
@section('page', $editing ? __('Modifier le menu') : __('Nouveau menu'))

@section('content')
@if($editing)
    {{-- Partager — vit ici, jamais dans l'assistant de création : ce n'est
         qu'APRÈS l'enregistrement que le menu a un alias, donc un lien réel
         à partager. Même schéma que pay/dashboard/share.blade.php : lien +
         copier, boutons de partage partagés (partials.share-buttons), le
         générateur de QR déjà commun à toutes les pages publiques TAGTOA
         (tagtoa.qr.index — rien de neuf à construire pour le QR/l'affiche),
         et un code d'intégration <iframe> pour un site externe. --}}
    <div class="card" style="border-left:4px solid #2cb809">
        <div class="h-row"><h2>{{ __('Partager ce menu') }}</h2></div>
        <label class="lbl">{{ __('Lien public') }}</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input class="inp" id="menuPublicUrl" readonly value="{{ $menu->public_url }}" style="flex:1;min-width:220px;font-family:monospace;font-size:13px">
            <button type="button" class="btn btn-d" onclick="copierLienMenu(this)"><i class="fa-solid fa-copy"></i> {{ __('Copier') }}</button>
        </div>
        <div style="margin-top:12px">
            @include('tagtoa::partials.share-buttons', ['url' => $menu->public_url, 'title' => $menu->name])
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
            <a class="btn btn-o btn-sm" href="{{ $menu->public_url }}" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> {{ __('Voir mon menu') }}</a>
            @if(\Illuminate\Support\Facades\Route::has('tagtoa.qr.index'))
                <a class="btn btn-o btn-sm" href="{{ route('tagtoa.qr.index') }}"><i class="fa-solid fa-qrcode"></i> {{ __('QR code & affiche') }}</a>
            @endif
            <button type="button" class="btn btn-o btn-sm" onclick="var b=document.getElementById('menuEmbedBox');b.style.display=b.style.display==='none'?'block':'none'">
                <i class="fa-solid fa-code"></i> {{ __('Intégrer sur mon site web') }}
            </button>
        </div>
        <div id="menuEmbedBox" style="display:none;margin-top:12px;padding-top:12px;border-top:1px dashed var(--bd)">
            <label class="lbl">{{ __('Code à coller sur votre site') }}</label>
            <textarea class="inp" id="menuEmbedCode" readonly rows="2" style="font-family:monospace;font-size:12px" onclick="this.select()"><iframe src="{{ $menu->public_url }}" style="width:100%;max-width:480px;height:640px;border:0;border-radius:12px" loading="lazy"></iframe></textarea>
            <button type="button" class="btn btn-o btn-sm" style="margin-top:6px" onclick="copierEmbedMenu(this)"><i class="fa-solid fa-copy"></i> {{ __('Copier le code') }}</button>
        </div>
    </div>
    @push('scripts')
    <script>
        function copierLienMenu(btn){
            var el = document.getElementById('menuPublicUrl');
            el.select(); el.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                var old = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> {{ __('Copié') }}';
                setTimeout(function(){ btn.innerHTML = old; }, 1800);
            } catch (e) {}
        }
        function copierEmbedMenu(btn){
            var el = document.getElementById('menuEmbedCode');
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
@endif
@include('tagtoa::menu._form-body')
@endsection

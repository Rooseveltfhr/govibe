{{-- TAGTOA — boutons de partage réutilisables (dashboard).
     Paramètres : $url (lien public), $title (texte). --}}
@php
    $shareUrl   = $url ?? '';
    $shareTitle = $title ?? 'TAGTOA';
    $enc     = urlencode($shareUrl);
    $encText = urlencode($shareTitle.' — '.$shareUrl);
    $encTtl  = urlencode($shareTitle);
@endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
    <a class="btn btn-o btn-sm" style="flex:0" target="_blank" rel="noopener" href="https://wa.me/?text={{ $encText }}"><i class="fa-brands fa-whatsapp" style="color:#25D366"></i> WhatsApp</a>
    <a class="btn btn-o btn-sm" style="flex:0" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ $enc }}"><i class="fa-brands fa-facebook" style="color:#1877F2"></i> Facebook</a>
    <a class="btn btn-o btn-sm" style="flex:0" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text={{ $encTtl }}&url={{ $enc }}"><i class="fa-brands fa-x-twitter"></i> X</a>
    <a class="btn btn-o btn-sm" style="flex:0" target="_blank" rel="noopener" href="https://t.me/share/url?url={{ $enc }}&text={{ $encTtl }}"><i class="fa-brands fa-telegram" style="color:#0088cc"></i> Telegram</a>
    <a class="btn btn-o btn-sm" style="flex:0" href="mailto:?subject={{ $encTtl }}&body={{ $encText }}"><i class="fa-solid fa-envelope"></i> {{ __('E-mail') }}</a>
    <button type="button" class="btn btn-o btn-sm" style="flex:0" data-copy="{{ $shareUrl }}"
            onclick="var u=this.getAttribute('data-copy');if(navigator.clipboard)navigator.clipboard.writeText(u);var o=this.innerHTML;var b=this;this.innerHTML='<i class=&quot;fa-solid fa-check&quot;></i>';setTimeout(function(){b.innerHTML=o},1200)">
        <i class="fa-solid fa-copy"></i> {{ __('Copier') }}
    </button>
</div>

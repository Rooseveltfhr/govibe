{{-- TAGTOA — sélecteur de langue (Kreyòl / Français / English / Español).
     Autonome : styles inclus, pas de JS (élément <details>). Réutilisable partout. --}}
@php
    $tgCur  = \Modules\Tagtoa\App\Support\Locale::current();
    $tgLocs = \Modules\Tagtoa\App\Support\Locale::all();
    $tgMeta = $tgLocs[$tgCur] ?? ['flag' => '🌐', 'label' => strtoupper($tgCur)];
@endphp
<details class="tg-lang">
    <summary aria-label="{{ __('Langue') }}"><i class="fa-solid fa-globe fl"></i><span class="lb">{{ $tgMeta['label'] }}</span><i class="fa-solid fa-chevron-down ch"></i></summary>
    <div class="tg-lang-menu">
        @foreach($tgLocs as $code => $m)
            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="{{ $code === $tgCur ? 'on' : '' }}">
                {{ $m['label'] }}
                @if($code === $tgCur)<i class="fa-solid fa-check ck"></i>@endif
            </a>
        @endforeach
    </div>
</details>
<style>
    .tg-lang{position:relative;display:inline-block}
    .tg-lang>summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border:1px solid rgba(128,128,128,.28);border-radius:999px;font:600 13px 'Space Grotesk',sans-serif;background:rgba(127,127,127,.06);user-select:none}
    .tg-lang>summary::-webkit-details-marker{display:none}
    .tg-lang>summary .ch{font-size:10px;opacity:.6}
    .tg-lang[open]>summary .ch{transform:rotate(180deg)}
    .tg-lang-menu{position:absolute;right:0;top:calc(100% + 6px);min-width:170px;background:#fff;color:#0A0A0A;border:1px solid rgba(0,0,0,.1);border-radius:12px;box-shadow:0 12px 34px rgba(0,0,0,.16);padding:6px;z-index:200}
    .tg-lang-menu a{display:flex;align-items:center;gap:9px;padding:9px 11px;border-radius:9px;font:600 14px 'Space Grotesk',sans-serif;color:#0A0A0A;white-space:nowrap}
    .tg-lang-menu a:hover{background:rgba(44,184,9,.08)}
    .tg-lang-menu a.on{color:#2cb809}
    .tg-lang-menu a .ck{margin-left:auto;font-size:11px;color:#2cb809}
    .tg-lang-menu .fl,.tg-lang>summary .fl{font-size:15px;line-height:1}
    @media(max-width:520px){.tg-lang>summary .lb{display:none}}
</style>

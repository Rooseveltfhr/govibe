{{-- TAGTOA Links — page publique Linktree. Variables : $page, $links --}}
@php
    $themes = [
        'dark'=>['bg'=>'#0A0A0A','fg'=>'#fff','card'=>'rgba(255,255,255,.08)','cfg'=>'#fff','mut'=>'rgba(255,255,255,.6)'],
        'light'=>['bg'=>'#F5F5F3','fg'=>'#0A0A0A','card'=>'#fff','cfg'=>'#0A0A0A','mut'=>'#888'],
        'blue'=>['bg'=>'linear-gradient(160deg,#239406,#0A0A0A)','fg'=>'#fff','card'=>'rgba(255,255,255,.12)','cfg'=>'#fff','mut'=>'rgba(255,255,255,.7)'],
    ];
    $t = $themes[$page->theme] ?? $themes['dark'];
    $social = ['facebook','instagram','tiktok','youtube','twitter','linkedin','telegram','whatsapp','snapchat','twitch','pinterest','discord','spotify'];
    $buttons = $links->filter(fn($l)=>$l->is_featured || ! in_array($l->platform,$social));
    $socials = $links->filter(fn($l)=>! $l->is_featured && in_array($l->platform,$social));
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ $page->title ?: $page->alias }} — TAGTOA</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blue:#2cb809;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif;--bg:{{ $t['bg'] }};--fg:{{ $t['fg'] }};--card:{{ $t['card'] }};--cfg:{{ $t['cfg'] }};--mut:{{ $t['mut'] }}}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--fg);min-height:100vh}
        .wrap{max-width:480px;margin:0 auto;padding:40px 20px 60px;min-height:100vh}
        .av,.ph{width:96px;height:96px;border-radius:50%;margin:0 auto 16px;display:block;object-fit:cover;border:3px solid var(--blue)}
        .ph{display:flex;align-items:center;justify-content:center;background:var(--blue);color:#fff;font:700 36px var(--fh);border:0}
        h1{font:700 22px var(--fh);text-align:center}
        .bio{text-align:center;color:var(--mut);font-size:14.5px;margin:8px auto 0;max-width:340px}
        .links{margin-top:28px;display:flex;flex-direction:column;gap:12px}
        .lnk{display:flex;align-items:center;gap:14px;background:var(--card);color:var(--cfg);border:1px solid rgba(128,128,128,.12);border-radius:15px;padding:15px 18px;font:600 15px var(--fh);transition:transform .2s,box-shadow .2s}
        .lnk:active{transform:scale(.98)}.lnk:hover{box-shadow:0 6px 22px rgba(44,184,9,.18)}
        .lnk i{font-size:20px;width:24px;text-align:center;color:var(--blue)}
        .lnk.feat{background:var(--blue);color:#fff;border-color:transparent}.lnk.feat i{color:#fff}
        .lnk .chev{margin-left:auto;opacity:.4;font-size:13px}
        .don{margin-top:22px;text-align:center}
        .don a{display:inline-flex;align-items:center;gap:9px;border:2px solid var(--blue);color:var(--blue);font:700 15px var(--fh);padding:13px 26px;border-radius:999px}
        .social{display:flex;justify-content:center;gap:18px;margin-top:30px}
        .social a{color:var(--mut);font-size:22px}.social a:hover{color:var(--blue)}
        .foot{text-align:center;margin-top:36px;color:var(--mut);font-size:12px}.foot b{font-family:var(--fh);color:var(--fg)}
        .share{position:fixed;top:16px;right:16px;width:42px;height:42px;border-radius:50%;border:0;background:var(--card);color:var(--cfg);font-size:16px;cursor:pointer}
        a{text-decoration:none}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
    <div style="position:fixed;top:16px;left:16px;z-index:50">@include('tagtoa::partials.lang')</div>
    <button class="share" onclick="sh()"><i class="fa-solid fa-share-nodes"></i></button>
    <div class="wrap">
        @if($page->avatar_url)<img class="av" src="{{ $page->avatar_url }}" alt="">
        @else<div class="ph">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($page->title ?: $page->alias,0,1)) }}</div>@endif
        <h1>{{ $page->title ?: '@'.$page->alias }}</h1>
        @if($page->bio)<p class="bio">{{ $page->bio }}</p>@endif

        @if($buttons->isNotEmpty())
            <div class="links">
                @foreach($buttons as $l)
                    <a class="lnk {{ $l->is_featured ? 'feat' : '' }}" href="{{ route('tagtoa.links.go',$l->id) }}" rel="noopener"><i class="{{ $l->icon }}"></i><span>{{ $l->label }}</span><i class="fa-solid fa-chevron-right chev"></i></a>
                @endforeach
            </div>
        @endif

        @if($page->payPage)
            <div class="don"><a href="{{ url('/pay/'.$page->payPage->alias) }}"><i class="fa-solid fa-heart"></i> {{ $page->donation_label ?: __('Faire un don') }}</a></div>
        @endif

        @if($socials->isNotEmpty())
            <div class="social">@foreach($socials as $l)<a href="{{ route('tagtoa.links.go',$l->id) }}" rel="noopener" aria-label="{{ $l->label }}"><i class="{{ $l->icon }}"></i></a>@endforeach</div>
        @endif

        <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
    </div>
    <script>function sh(){if(navigator.share){navigator.share({title:document.title,url:location.href}).catch(function(){});}else{navigator.clipboard&&navigator.clipboard.writeText(location.href);alert('{{ __('Lien copié') }}');}}</script>
</body>
</html>

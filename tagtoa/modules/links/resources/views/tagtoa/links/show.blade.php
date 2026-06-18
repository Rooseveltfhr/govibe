{{-- ============================================================
     TAGTOA LINKS — Page publique Linktree-style (NFC / QR)
     Standalone HTML · mobile-first · vanilla JS · optimisé 3G
     Variables : $page (TaGtoaLinkPage), $links (Collection)
     ============================================================ --}}
@php
    $themes = [
        'dark'  => ['bg' => '#0A0A0A', 'fg' => '#FFFFFF', 'card' => 'rgba(255,255,255,.08)', 'cardfg' => '#FFFFFF', 'muted' => 'rgba(255,255,255,.6)'],
        'light' => ['bg' => '#F5F5F3', 'fg' => '#0A0A0A', 'card' => '#FFFFFF', 'cardfg' => '#0A0A0A', 'muted' => '#888'],
        'blue'  => ['bg' => 'linear-gradient(160deg,#0040CC,#0A0A0A)', 'fg' => '#FFFFFF', 'card' => 'rgba(255,255,255,.12)', 'cardfg' => '#FFFFFF', 'muted' => 'rgba(255,255,255,.7)'],
    ];
    $t = $themes[$page->theme] ?? $themes['dark'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ $page->title ?: $page->alias }} — TAGTOA</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($page->bio), 150) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root{
            --tagtoa-blue:#0055FF; --fh:'Space Grotesk',sans-serif; --fb:'Nunito',-apple-system,sans-serif;
            --bg:{{ $t['bg'] }}; --fg:{{ $t['fg'] }}; --card:{{ $t['card'] }}; --cardfg:{{ $t['cardfg'] }}; --muted:{{ $t['muted'] }};
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--fg);line-height:1.5;min-height:100vh;-webkit-font-smoothing:antialiased}
        .wrap{max-width:480px;margin:0 auto;padding:40px 20px 60px;min-height:100vh}
        .avatar{width:96px;height:96px;border-radius:50%;object-fit:cover;margin:0 auto 16px;display:block;border:3px solid var(--tagtoa-blue)}
        .ph{width:96px;height:96px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;background:var(--tagtoa-blue);color:#fff;font-family:var(--fh);font-weight:700;font-size:36px}
        h1{font-family:var(--fh);font-weight:700;font-size:22px;text-align:center}
        .bio{text-align:center;color:var(--muted);font-size:14.5px;margin:8px auto 0;max-width:340px}
        .links{margin-top:28px;display:flex;flex-direction:column;gap:12px}
        .lnk{display:flex;align-items:center;gap:14px;background:var(--card);color:var(--cardfg);
            border:1px solid rgba(128,128,128,.12);border-radius:15px;padding:15px 18px;text-decoration:none;
            font-family:var(--fh);font-weight:600;font-size:15px;transition:transform .2s cubic-bezier(.4,0,.2,1),box-shadow .2s}
        .lnk:active{transform:scale(.98)}
        .lnk:hover{box-shadow:0 6px 22px rgba(0,85,255,.18)}
        .lnk i{font-size:20px;width:24px;text-align:center;color:var(--tagtoa-blue)}
        .lnk.feat{background:var(--tagtoa-blue);color:#fff;border-color:transparent}
        .lnk.feat i{color:#fff}
        .lnk .chev{margin-left:auto;opacity:.4;font-size:13px}
        .don{margin-top:22px;text-align:center}
        .don a{display:inline-flex;align-items:center;gap:9px;background:transparent;border:2px solid var(--tagtoa-blue);
            color:var(--tagtoa-blue);font-family:var(--fh);font-weight:700;font-size:15px;padding:13px 26px;border-radius:999px;text-decoration:none;transition:all .2s}
        .don a:active{transform:scale(.97)}
        .social{display:flex;justify-content:center;gap:18px;margin-top:30px}
        .social a{color:var(--muted);font-size:22px;text-decoration:none;transition:color .2s}
        .social a:hover{color:var(--tagtoa-blue)}
        .foot{text-align:center;margin-top:36px;color:var(--muted);font-size:12px}
        .foot b{font-family:var(--fh);color:var(--fg)}
        .share{position:fixed;top:16px;right:16px;width:42px;height:42px;border-radius:50%;border:0;
            background:var(--card);color:var(--cardfg);font-size:16px;cursor:pointer;backdrop-filter:blur(8px)}
        @media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
    </style>
</head>
<body>
    <button class="share" onclick="tlShare()" aria-label="{{ __('Partager') }}"><i class="fa-solid fa-share-nodes"></i></button>
    <div class="wrap">
        @if($page->avatar_url)
            <img class="avatar" src="{{ $page->avatar_url }}" alt="{{ $page->title }}">
        @else
            <div class="ph">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($page->title ?: $page->alias, 0, 1)) }}</div>
        @endif

        <h1>{{ $page->title ?: '@' . $page->alias }}</h1>
        @if($page->bio)<p class="bio">{{ $page->bio }}</p>@endif

        @php
            // Liens "boutons" (featured ou website/custom) vs icônes sociales en bas.
            $socialPlatforms = ['facebook','instagram','tiktok','youtube','twitter','linkedin','telegram','whatsapp','snapchat','twitch','pinterest','discord','spotify'];
            $buttons = $links->filter(fn($l) => $l->is_featured || ! in_array($l->platform, $socialPlatforms));
            $socials = $links->filter(fn($l) => ! $l->is_featured && in_array($l->platform, $socialPlatforms));
        @endphp

        @if($buttons->isNotEmpty())
            <div class="links">
                @foreach($buttons as $l)
                    <a class="lnk {{ $l->is_featured ? 'feat' : '' }}"
                       href="{{ route('tagtoa.links.go', $l->id) }}" rel="noopener">
                        <i class="{{ $l->icon }}"></i>
                        <span>{{ $l->label }}</span>
                        <i class="fa-solid fa-chevron-right chev"></i>
                    </a>
                @endforeach
            </div>
        @endif

        @if($page->payPage)
            <div class="don">
                <a href="{{ url('/pay/' . $page->payPage->alias) }}">
                    <i class="fa-solid fa-heart"></i> {{ $page->donation_label ?: __('Faire un don') }}
                </a>
            </div>
        @endif

        @if($socials->isNotEmpty())
            <div class="social">
                @foreach($socials as $l)
                    <a href="{{ route('tagtoa.links.go', $l->id) }}" aria-label="{{ $l->label }}" rel="noopener">
                        <i class="{{ $l->icon }}"></i>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
    </div>

    <script>
    function tlShare(){
        var d={title:document.title,url:location.href};
        if(navigator.share){navigator.share(d).catch(function(){});}
        else if(navigator.clipboard){navigator.clipboard.writeText(location.href);alert('{{ __('Lien copié') }}');}
    }
    </script>
</body>
</html>

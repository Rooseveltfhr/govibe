{{-- TAGTOA Event — annuaire public de TOUS les événements publiés. Variable : $events (paginator) --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Événements') }} — TAGTOA</title>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blk:#0A0A0A;--green:#2cb809;--ink:#0d140c;--bg:#f5f9f2;--card:#fff;--bd:rgba(13,20,12,.10);--mut:#5d6b5a;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}body{font-family:var(--fb);background:var(--bg);color:var(--ink)}
        .top{background:var(--ink);color:#fff;padding:26px 18px;text-align:center}
        .top h1{font:400 30px 'Anton',sans-serif;letter-spacing:.02em}
        .top p{opacity:.8;font-size:14px;margin-top:6px}
        .wrap{max-width:1000px;margin:0 auto;padding:20px 16px 40px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
        .card{background:var(--card);border:1px solid var(--bd);border-radius:16px;overflow:hidden;text-decoration:none;color:inherit;display:flex;flex-direction:column;transition:transform .12s,box-shadow .12s}
        .card:hover{transform:translateY(-3px);box-shadow:0 10px 26px rgba(0,0,0,.10)}
        .cover{height:150px;background:linear-gradient(135deg,#2cb809,#1D9E75);display:flex;align-items:center;justify-content:center;color:#fff;font-size:34px;position:relative}
        .cover img{width:100%;height:100%;object-fit:cover}
        .badge{position:absolute;top:10px;left:10px;background:rgba(0,0,0,.62);color:#fff;font:600 12px var(--fh);padding:5px 10px;border-radius:999px}
        .body{padding:14px;flex:1;display:flex;flex-direction:column;gap:6px}
        .body h2{font:600 16px var(--fh);line-height:1.25}
        .meta{font-size:13px;color:var(--mut);display:flex;align-items:center;gap:6px}
        .price{margin-top:auto;font:700 14px var(--fh);color:var(--green)}
        .empty{text-align:center;padding:60px 20px;color:var(--mut)}
        .pag{display:flex;justify-content:center;gap:8px;margin-top:24px}
        .pag a,.pag span{padding:8px 13px;border-radius:10px;border:1px solid var(--bd);background:#fff;text-decoration:none;color:var(--ink);font:600 13px var(--fh)}
        .pag .cur{background:var(--ink);color:#fff}
        .foot{text-align:center;padding:24px;color:#9aa;font-size:12px}.foot b{font-family:var(--fh);color:var(--mut)}
    </style>
</head>
<body>
    <div class="top">
        <h1>{{ __('Événements') }}</h1>
        <p>{{ __('Découvrez et réservez vos billets') }}</p>
    </div>
    <div class="wrap">
        @forelse($events as $e)
            @php
                $minPrice = $e->activeTicketTypes->min('price');
                if ($e->is_free || $minPrice === null || (float) $minPrice <= 0) {
                    $priceLabel = __('Gratuit');
                } else {
                    $priceLabel = __('À partir de').' '.number_format((float) $minPrice, 2).' '.$e->currency;
                }
                $dateLabel = optional($e->starts_at)->format('d/m/Y · H:i');
            @endphp
        @if($loop->first)<div class="grid">@endif
            <a class="card" href="{{ route('tagtoa.event.show', $e->alias) }}">
                <div class="cover">
                    @if($e->cover_url)<img src="{{ $e->cover_url }}" alt="{{ $e->title }}">@else<i class="fa-solid fa-ticket"></i>@endif
                    @if($e->type)<span class="badge">{{ ucfirst($e->type) }}</span>@endif
                </div>
                <div class="body">
                    <h2>{{ $e->title }}</h2>
                    @if($dateLabel)<div class="meta"><i class="fa-regular fa-calendar"></i> {{ $dateLabel }}</div>@endif
                    @if($e->venue)<div class="meta"><i class="fa-solid fa-location-dot"></i> {{ $e->venue }}</div>@endif
                    <div class="price">{{ $priceLabel }}</div>
                </div>
            </a>
        @if($loop->last)</div>@endif
        @empty
            <div class="empty"><i class="fa-solid fa-calendar-xmark" style="font-size:40px;opacity:.4"></i><p style="margin-top:12px">{{ __('Aucun événement pour le moment.') }}</p></div>
        @endforelse

        @if($events->hasPages())
            <div class="pag">
                @if($events->onFirstPage())<span>‹</span>@else<a href="{{ $events->previousPageUrl() }}">‹</a>@endif
                <span class="cur">{{ $events->currentPage() }} / {{ $events->lastPage() }}</span>
                @if($events->hasMorePages())<a href="{{ $events->nextPageUrl() }}">›</a>@else<span>›</span>@endif
            </div>
        @endif
    </div>
    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b></div>
</body>
</html>

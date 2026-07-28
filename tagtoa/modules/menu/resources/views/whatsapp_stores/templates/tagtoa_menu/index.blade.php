<!DOCTYPE html>
{{--
    TAGTOA MENU — Digital Menu Template
    Aesthetic  : Black / White / Electric Blue — TAGTOA brand system
    Typography : Space Grotesk (display) + Inter-fallback Nunito (body)
    Optimisé   : NFC tap · QR scan · mobile-first · connexion lente Haïti
                 (Font Awesome CDN only, lazy images, no heavy JS frameworks)

    Variables attendues (compatibles avec WhatsappStoreController::show) :
      $whatsappStore        — WhatsappStore model
      $businessDaysTime     — array [day_of_week => "HH:MM - HH:MM" | null]
      $business_hours       — bool, afficher horaires
      $hide_sticky_bar      — bool
      $whatsappStoreUrl     — string, URL publique
      $discount             — float|null, remise globale boutique

    Champs additionnels optionnels (ajoutés par migration TAGTOA MENU) :
      $whatsappStore->business_type        — restaurant|hotel|bar|lounge|cafe|club|fastfood
      $whatsappStore->delivery_available    — bool
      $product->discount_price              — float|null
      $product->prep_time                   — int (minutes)
      $product->featured                    — bool
      $product->is_available                — bool
      $product->dine_in / takeout / delivery — bool
--}}
<html lang="{{ getLocalLanguage() ?? 'en' }}" dir="{{ getLocalLanguage() == 'ar' || getLocalLanguage() == 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0A0A0A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    @if (checkFeature('seo') && $whatsappStore->site_title && $whatsappStore->home_title)
        <title>{{ $whatsappStore->home_title }} | {{ $whatsappStore->site_title }}</title>
    @else
        <title>{{ $whatsappStore->store_name }} | TAGTOA MENU</title>
    @endif

    @if (checkFeature('seo'))
        @if ($whatsappStore->meta_description)
            <meta name="description" content="{{ $whatsappStore->meta_description }}">
        @endif
        @if ($whatsappStore->meta_keyword)
            <meta name="keywords" content="{{ $whatsappStore->meta_keyword }}">
        @endif
    @endif

    <link rel="icon" href="{{ $whatsappStore->logo_url }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ $whatsappStore->logo_url ?: asset('logo.png') }}">
    <link rel="manifest" href="{{ asset('pwa/1.json') }}">

    {{-- Space Grotesk + Nunito --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    @if ($whatsappStore->font_family || $whatsappStore->custom_css)
        <style>
            @if ($whatsappStore->font_family)
                body { font-family: '{{ $whatsappStore->font_family }}', 'Nunito', sans-serif; }
            @endif
            @if ($whatsappStore->custom_css)
                {!! $whatsappStore->custom_css !!}
            @endif
        </style>
    @endif

    <style>
        /* ============================================================
           RESET & TOKENS — TAGTOA brand
        ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --black:        #0A0A0A;
            --ink:          #141414;
            --surface:      #FFFFFF;
            --surface-2:    #F5F5F3;
            --surface-3:    #EFEFEC;
            --line:         rgba(0,0,0,0.08);
            --line-dark:    rgba(255,255,255,0.10);
            --gray:         #8A8A8A;
            --gray-2:       #5C5C5C;
            --blue:         #0055FF;
            --blue-deep:    #0040CC;
            --blue-pale:    rgba(0,85,255,0.08);
            --blue-border:  rgba(0,85,255,0.22);
            --green:        #1D9E75;
            --red:          #E0473E;
            --r-sm:         10px;
            --r-md:         16px;
            --r-lg:         24px;
            --r-xl:         32px;
            --font-head:    'Space Grotesk', sans-serif;
            --font-body:    'Nunito', -apple-system, sans-serif;
            --ease:         cubic-bezier(0.4, 0, 0.2, 1);
            --safe-bottom:  env(safe-area-inset-bottom, 0px);
        }

        html { scroll-behavior: smooth; background: var(--black); }

        body {
            background: var(--surface-2);
            color: var(--ink);
            font-family: var(--font-body);
            font-size: 15px;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            overflow-x: hidden;
            padding-bottom: 90px;
        }

        .tm-page { max-width: 480px; margin: 0 auto; position: relative; }

        img { display: block; max-width: 100%; }

        /* ============================================================
           HEADER — black, sticky brand bar
        ============================================================ */
        .tm-topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px;
            background: var(--black);
            border-bottom: 1px solid var(--line-dark);
        }

        .tm-brand {
            font-family: var(--font-head);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.16em;
            color: rgba(255,255,255,0.55);
            text-transform: uppercase;
        }

        .tm-brand strong { color: #fff; }

        .tm-nfc {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.08);
            border: 1px solid var(--line-dark);
            border-radius: 20px;
            padding: 4px 11px;
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            letter-spacing: 0.06em;
            font-family: var(--font-head);
            text-transform: uppercase;
        }

        .tm-nfc i {
            color: var(--blue);
            font-size: 11px;
            animation: tm-blink 2.4s ease infinite;
        }

        @keyframes tm-blink { 0%,100%{opacity:1} 50%{opacity:.25} }

        /* ============================================================
           HERO — cover + business identity
        ============================================================ */
        .tm-hero {
            position: relative;
            background: var(--black);
            padding-bottom: 22px;
        }

        .tm-hero-cover {
            height: 160px;
            overflow: hidden;
            position: relative;
        }

        .tm-hero-cover img,
        .tm-hero-cover iframe {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.6;
        }

        .tm-hero-cover::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(10,10,10,0.1) 0%, var(--black) 100%);
        }

        .tm-hero-id {
            display: flex;
            align-items: flex-end;
            gap: 14px;
            padding: 0 18px;
            margin-top: -38px;
            position: relative;
            z-index: 2;
        }

        .tm-logo {
            width: 76px;
            height: 76px;
            border-radius: var(--r-md);
            object-fit: cover;
            border: 3px solid var(--black);
            background: var(--surface);
            flex-shrink: 0;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.08);
        }

        .tm-hero-text { flex: 1; padding-bottom: 4px; min-width: 0; }

        .tm-store-name {
            font-family: var(--font-head);
            font-size: 21px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
            letter-spacing: -0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tm-store-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 600;
            color: var(--blue);
            background: var(--blue-pale);
            border: 1px solid var(--blue-border);
            border-radius: 6px;
            padding: 2px 8px;
            margin-top: 5px;
            text-transform: capitalize;
            font-family: var(--font-head);
            letter-spacing: 0.02em;
        }

        /* Status row: open/closed + address + whatsapp */
        .tm-status-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 14px 18px 0;
        }

        .tm-status-chip {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: rgba(255,255,255,0.75);
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--line-dark);
            border-radius: 20px;
            padding: 6px 12px;
        }

        .tm-status-chip.open  { color: var(--green); border-color: rgba(29,158,117,0.3); background: rgba(29,158,117,0.1); }
        .tm-status-chip.closed{ color: var(--red); border-color: rgba(224,71,62,0.3); background: rgba(224,71,62,0.1); }

        .tm-status-chip i { font-size: 11px; }

        .tm-status-chip a { color: inherit; text-decoration: none; }

        /* ============================================================
           ANNOUNCEMENT MARQUEE
        ============================================================ */
        .tm-marquee {
            background: var(--blue);
            overflow: hidden;
            white-space: nowrap;
            padding: 7px 0;
        }

        .tm-marquee-track {
            display: inline-flex;
            animation: tm-scroll 22s linear infinite;
        }

        .tm-marquee-track span {
            font-size: 12px;
            font-weight: 600;
            color: #fff;
            letter-spacing: 0.04em;
            padding: 0 28px;
            font-family: var(--font-head);
            text-transform: uppercase;
        }

        @keyframes tm-scroll {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        /* ============================================================
           CATEGORY TABS — sticky horizontal scroll
        ============================================================ */
        .tm-cats-wrap {
            position: sticky;
            top: 49px;
            z-index: 40;
            background: var(--surface-2);
            border-bottom: 1px solid var(--line);
            padding: 12px 0;
        }

        .tm-cats {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 0 18px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .tm-cats::-webkit-scrollbar { display: none; }

        .tm-cat-chip {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            border-radius: 20px;
            background: var(--surface);
            border: 1px solid var(--line);
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-2);
            text-decoration: none;
            font-family: var(--font-head);
            transition: all 0.18s var(--ease);
            scroll-snap-align: start;
        }

        .tm-cat-chip img {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            object-fit: cover;
        }

        .tm-cat-chip.active,
        .tm-cat-chip:hover {
            background: var(--black);
            border-color: var(--black);
            color: #fff;
        }

        .tm-cat-chip.active { box-shadow: 0 4px 14px rgba(0,85,255,0.25); }
        .tm-cat-chip.active::after {
            content: '';
            display: inline-block;
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--blue);
            margin-left: 2px;
        }

        /* ============================================================
           SECTION TITLES
        ============================================================ */
        .tm-section { padding: 20px 18px 0; }

        .tm-section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .tm-section-title {
            font-family: var(--font-head);
            font-size: 17px;
            font-weight: 700;
            color: var(--black);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tm-section-count {
            font-size: 12px;
            color: var(--gray);
            font-weight: 500;
        }

        /* ============================================================
           FEATURED CAROUSEL ("Chef's picks")
        ============================================================ */
        .tm-featured-scroll {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 4px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .tm-featured-scroll::-webkit-scrollbar { display: none; }

        .tm-feat-card {
            flex-shrink: 0;
            width: 168px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--r-md);
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: transform 0.18s var(--ease);
        }

        .tm-feat-card:active { transform: scale(0.97); }

        .tm-feat-img {
            height: 110px;
            position: relative;
            background: var(--surface-3);
            overflow: hidden;
        }

        .tm-feat-img img { width: 100%; height: 100%; object-fit: cover; }

        .tm-feat-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--blue);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: var(--font-head);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .tm-feat-body { padding: 10px 12px 12px; }

        .tm-feat-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--black);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .tm-feat-price {
            font-family: var(--font-head);
            font-size: 14px;
            font-weight: 700;
            color: var(--blue);
        }

        /* ============================================================
           MENU CATEGORY GROUP + ITEM CARDS
        ============================================================ */
        .tm-cat-group { margin-bottom: 6px; }

        .tm-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .tm-item {
            display: flex;
            gap: 12px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--r-md);
            padding: 12px;
            position: relative;
            transition: border-color 0.18s var(--ease);
        }

        .tm-item.sold-out { opacity: 0.55; }

        .tm-item-img {
            width: 84px;
            height: 84px;
            border-radius: var(--r-sm);
            object-fit: cover;
            flex-shrink: 0;
            background: var(--surface-3);
        }

        .tm-item-body {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .tm-item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
        }

        .tm-item-name {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--black);
            line-height: 1.3;
        }

        .tm-item-name .tm-featured-icon {
            color: var(--blue);
            font-size: 11px;
            margin-right: 4px;
        }

        .tm-item-desc {
            font-size: 12px;
            color: var(--gray);
            line-height: 1.5;
            margin-top: 3px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .tm-item-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            gap: 8px;
        }

        .tm-item-price-row {
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .tm-item-price {
            font-family: var(--font-head);
            font-size: 15px;
            font-weight: 700;
            color: var(--black);
        }

        .tm-item-price.discounted { color: var(--blue); }

        .tm-item-price-old {
            font-size: 12px;
            color: var(--gray);
            text-decoration: line-through;
        }

        .tm-item-meta {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .tm-tag {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            font-weight: 600;
            color: var(--gray-2);
            background: var(--surface-2);
            border: 1px solid var(--line);
            border-radius: 5px;
            padding: 2px 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .tm-tag.sold-out-tag {
            color: var(--red);
            border-color: rgba(224,71,62,0.25);
            background: rgba(224,71,62,0.06);
        }

        .tm-tag i { font-size: 9px; }

        .tm-add-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--black);
            color: #fff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.18s var(--ease);
        }

        .tm-add-btn:hover { background: var(--blue); transform: scale(1.06); }
        .tm-add-btn:disabled { background: var(--line); color: var(--gray); cursor: not-allowed; }

        /* ============================================================
           ORDER MODE TOGGLE (dine-in / takeout / delivery)
        ============================================================ */
        .tm-order-mode {
            display: flex;
            gap: 8px;
            padding: 16px 18px 4px;
            overflow-x: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .tm-order-mode::-webkit-scrollbar { display: none; }

        .tm-mode-btn {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 10px 16px;
            border-radius: var(--r-md);
            border: 1px solid var(--line);
            background: var(--surface);
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-2);
            font-family: var(--font-head);
            cursor: pointer;
            transition: all 0.18s var(--ease);
        }

        .tm-mode-btn.active {
            background: var(--black);
            border-color: var(--black);
            color: #fff;
        }

        .tm-mode-btn.active i { color: var(--blue); }

        /* ============================================================
           BUSINESS HOURS
        ============================================================ */
        .tm-hours-grid {
            display: flex;
            flex-direction: column;
            gap: 6px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--r-md);
            padding: 6px;
        }

        .tm-hours-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-radius: var(--r-sm);
            font-size: 13px;
        }

        .tm-hours-row.today {
            background: var(--blue-pale);
            border: 1px solid var(--blue-border);
        }

        .tm-hours-day {
            font-weight: 600;
            color: var(--black);
            font-family: var(--font-head);
        }

        .tm-hours-time {
            color: var(--gray-2);
            font-weight: 500;
        }

        .tm-hours-time.closed { color: var(--red); }

        /* ============================================================
           QR CARD
        ============================================================ */
        .tm-qr-card {
            background: var(--black);
            border-radius: var(--r-lg);
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px;
        }

        .tm-qr-img {
            width: 92px;
            height: 92px;
            background: #fff;
            border-radius: var(--r-sm);
            padding: 8px;
            flex-shrink: 0;
        }

        .tm-qr-img svg { width: 76px; height: 76px; }

        .tm-qr-text-title {
            font-family: var(--font-head);
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }

        .tm-qr-text-sub {
            font-size: 12px;
            color: rgba(255,255,255,0.55);
            line-height: 1.5;
        }

        /* ============================================================
           FOOTER
        ============================================================ */
        .tm-footer {
            text-align: center;
            padding: 28px 18px 24px;
            font-size: 11px;
            color: var(--gray);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-family: var(--font-head);
        }

        .tm-footer span { color: var(--blue); }

        /* ============================================================
           STICKY CART BAR
        ============================================================ */
        .tm-cart-bar {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%) translateY(110%);
            width: 100%;
            max-width: 480px;
            z-index: 90;
            padding: 0 16px calc(16px + var(--safe-bottom));
            transition: transform 0.28s var(--ease);
        }

        .tm-cart-bar.visible { transform: translateX(-50%) translateY(0); }

        .tm-cart-inner {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--black);
            border-radius: var(--r-lg);
            padding: 12px 12px 12px 18px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.25);
        }

        .tm-cart-info { flex: 1; color: #fff; }

        .tm-cart-count {
            font-size: 11px;
            color: rgba(255,255,255,0.55);
            font-weight: 500;
        }

        .tm-cart-total {
            font-family: var(--font-head);
            font-size: 17px;
            font-weight: 700;
        }

        .tm-cart-cta {
            background: var(--blue);
            color: #fff;
            border: none;
            border-radius: var(--r-md);
            padding: 13px 22px;
            font-size: 14px;
            font-weight: 700;
            font-family: var(--font-head);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.18s var(--ease);
        }

        .tm-cart-cta:hover { background: var(--blue-deep); }

        /* Floating share / contact bar — shown when cart empty */
        .tm-bottom-bar {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            z-index: 80;
            padding: 0 16px calc(16px + var(--safe-bottom));
            transition: opacity 0.2s var(--ease);
        }

        .tm-bottom-bar.hidden { opacity: 0; pointer-events: none; }

        .tm-bottom-inner {
            display: flex;
            gap: 10px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--r-lg);
            padding: 8px;
            box-shadow: 0 6px 32px rgba(0,0,0,0.1);
        }

        .tm-bottom-action {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: var(--r-md);
            border: none;
            background: var(--surface-2);
            color: var(--black);
            font-size: 13px;
            font-weight: 700;
            font-family: var(--font-head);
            text-decoration: none;
            cursor: pointer;
            transition: all 0.18s var(--ease);
        }

        .tm-bottom-action.primary {
            background: var(--black);
            color: #fff;
        }

        .tm-bottom-action.whatsapp {
            background: #25D366;
            color: #fff;
        }

        .tm-bottom-action:hover { opacity: 0.88; }

        /* ============================================================
           CART MODAL (lightweight, no framework)
        ============================================================ */
        .tm-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(10,10,10,0.55);
            backdrop-filter: blur(4px);
            z-index: 200;
            display: none;
            align-items: flex-end;
            justify-content: center;
        }

        .tm-modal-overlay.open { display: flex; }

        .tm-modal-sheet {
            background: var(--surface);
            width: 100%;
            max-width: 480px;
            max-height: 80vh;
            border-radius: var(--r-xl) var(--r-xl) 0 0;
            overflow-y: auto;
            animation: tm-sheet-up 0.28s var(--ease);
        }

        @keyframes tm-sheet-up { from { transform: translateY(100%); } to { transform: translateY(0); } }

        .tm-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 18px 12px;
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            background: var(--surface);
        }

        .tm-modal-title {
            font-family: var(--font-head);
            font-size: 17px;
            font-weight: 700;
        }

        .tm-modal-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--surface-2);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--gray-2);
        }

        .tm-cart-list { padding: 8px 18px; }

        .tm-cart-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
        }

        .tm-cart-row:last-child { border-bottom: none; }

        .tm-cart-row-img {
            width: 50px;
            height: 50px;
            border-radius: var(--r-sm);
            object-fit: cover;
            flex-shrink: 0;
            background: var(--surface-3);
        }

        .tm-cart-row-info { flex: 1; min-width: 0; }

        .tm-cart-row-name {
            font-size: 13.5px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tm-cart-row-price {
            font-size: 12px;
            color: var(--gray);
            font-family: var(--font-head);
            font-weight: 600;
            margin-top: 2px;
        }

        .tm-qty-control {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--surface-2);
            border-radius: 20px;
            padding: 4px;
        }

        .tm-qty-btn {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: none;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            color: var(--black);
        }

        .tm-qty-val {
            font-size: 13px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            font-family: var(--font-head);
        }

        .tm-modal-footer {
            padding: 16px 18px calc(16px + var(--safe-bottom));
            border-top: 1px solid var(--line);
            position: sticky;
            bottom: 0;
            background: var(--surface);
        }

        .tm-summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: var(--gray-2);
            margin-bottom: 6px;
        }

        .tm-summary-row.grand {
            font-size: 16px;
            font-weight: 700;
            color: var(--black);
            font-family: var(--font-head);
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
        }

        .tm-checkout-btn {
            width: 100%;
            margin-top: 14px;
            padding: 15px;
            background: var(--blue);
            color: #fff;
            border: none;
            border-radius: var(--r-md);
            font-family: var(--font-head);
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.18s var(--ease);
        }

        .tm-checkout-btn:hover { background: var(--blue-deep); }
        .tm-checkout-btn:disabled { background: var(--line); color: var(--gray); cursor: not-allowed; }

        .tm-empty-cart {
            text-align: center;
            padding: 40px 18px;
            color: var(--gray);
            font-size: 13px;
        }

        .tm-empty-cart i { font-size: 32px; margin-bottom: 10px; display: block; color: var(--line); }

        /* ============================================================
           CHECKOUT FORM MODAL
        ============================================================ */
        .tm-field { margin-bottom: 12px; }

        .tm-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray-2);
            margin-bottom: 6px;
            font-family: var(--font-head);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .tm-field input,
        .tm-field select,
        .tm-field textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: var(--r-sm);
            border: 1px solid var(--line);
            background: var(--surface-2);
            font-size: 14px;
            font-family: var(--font-body);
            color: var(--ink);
        }

        .tm-field input:focus,
        .tm-field select:focus,
        .tm-field textarea:focus {
            outline: none;
            border-color: var(--blue);
            background: var(--surface);
        }

        .tm-payment-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .tm-payment-opt {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: var(--r-sm);
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.15s var(--ease);
        }

        .tm-payment-opt.selected {
            border-color: var(--blue);
            background: var(--blue-pale);
        }

        .tm-payment-opt input { width: auto; }

        .tm-payment-opt i { font-size: 16px; color: var(--blue); width: 20px; text-align: center; }

        /* ============================================================
           ANIMATIONS
        ============================================================ */
        .tm-in {
            opacity: 0;
            animation: tm-rise 0.42s var(--ease) forwards;
        }

        .tm-in:nth-of-type(1) { animation-delay: 0s; }
        .tm-in:nth-of-type(2) { animation-delay: 0.05s; }
        .tm-in:nth-of-type(3) { animation-delay: 0.10s; }
        .tm-in:nth-of-type(4) { animation-delay: 0.15s; }
        .tm-in:nth-of-type(5) { animation-delay: 0.20s; }

        @keyframes tm-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }

        @media (prefers-reduced-motion: reduce) {
            .tm-in { animation: none; opacity: 1; }
            .tm-nfc i, .tm-marquee-track { animation: none; }
        }

        /* Toast */
        .tm-toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--black);
            color: #fff;
            padding: 10px 20px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 600;
            z-index: 300;
            opacity: 0;
            transition: all 0.25s var(--ease);
            display: flex;
            align-items: center;
            gap: 8px;
            pointer-events: none;
        }

        .tm-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .tm-toast i { color: var(--blue); }
    </style>
</head>
<body>

<div class="tm-page">

    {{-- ================================================================
         TOP BAR
    ================================================================ --}}
    <div class="tm-topbar">
        <div class="tm-brand">TAG<strong>TOA</strong> MENU</div>
        <div class="tm-nfc">
            <i class="fa-solid fa-rss"></i>
            NFC / QR
        </div>
    </div>

    {{-- ================================================================
         HERO — cover, logo, identity
    ================================================================ --}}
    <div class="tm-hero">
        <div class="tm-hero-cover">
            @if ($whatsappStore->slider_video_banner && YoutubeID($whatsappStore->slider_video_banner))
                <iframe
                    src="https://www.youtube-nocookie.com/embed/{{ YoutubeID($whatsappStore->slider_video_banner) }}?autoplay=1&mute=1&loop=1&playlist={{ YoutubeID($whatsappStore->slider_video_banner) }}&controls=0&modestbranding=1&showinfo=0&rel=0"
                    frameborder="0" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
            @elseif ($whatsappStore->cover_url)
                <img src="{{ $whatsappStore->cover_url }}" alt="{{ $whatsappStore->store_name }}" loading="eager">
            @else
                <div style="width:100%;height:100%;background:linear-gradient(135deg,#0A0A0A 0%,#1a1a2e 60%,#0055FF 140%)"></div>
            @endif
        </div>

        <div class="tm-hero-id">
            <img src="{{ $whatsappStore->logo_url ?: asset('assets/images/default_service.png') }}"
                 alt="{{ $whatsappStore->store_name }}" class="tm-logo" loading="eager">
            <div class="tm-hero-text">
                <div class="tm-store-name">{{ $whatsappStore->store_name }}</div>
                @if (!empty($whatsappStore->business_type))
                    <div class="tm-store-type">
                        @php
                            $bizIcons = [
                                'restaurant' => 'fa-utensils',
                                'hotel'      => 'fa-bed',
                                'bar'        => 'fa-martini-glass',
                                'lounge'     => 'fa-couch',
                                'cafe'       => 'fa-mug-saucer',
                                'club'       => 'fa-music',
                                'fastfood'   => 'fa-burger',
                            ];
                            $bizIcon = $bizIcons[$whatsappStore->business_type] ?? 'fa-store';
                        @endphp
                        <i class="fa-solid {{ $bizIcon }}"></i>
                        {{ $whatsappStore->business_type }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Status row : open/closed, address, whatsapp, delivery --}}
        <div class="tm-status-row">
            @php
                $todayIndex = (int) \Carbon\Carbon::now()->dayOfWeekIso; // 1=Mon ... 7=Sun
                $todaySchedule = $businessDaysTime[$todayIndex] ?? null;
                $isOpenNow = false;
                if ($todaySchedule) {
                    [$openTime, $closeTime] = array_map('trim', explode('-', $todaySchedule));
                    $now = \Carbon\Carbon::now()->format('H:i');
                    $isOpenNow = $now >= $openTime && $now <= $closeTime;
                }
            @endphp

            @if ($business_hours)
                @if ($isOpenNow)
                    <div class="tm-status-chip open">
                        <i class="fa-solid fa-circle"></i> {{ __('messages.common.open') ?? 'Open now' }}
                    </div>
                @else
                    <div class="tm-status-chip closed">
                        <i class="fa-solid fa-circle"></i> {{ __('messages.common.closed') ?? 'Closed' }}
                    </div>
                @endif
            @endif

            @if ($whatsappStore->address)
                <a href="https://maps.google.com/?q={{ urlencode($whatsappStore->address) }}" target="_blank" class="tm-status-chip">
                    <i class="fa-solid fa-location-dot"></i>
                    <span style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $whatsappStore->address }}</span>
                </a>
            @endif

            @if ($whatsappStore->whatsapp_no)
                <a href="https://wa.me/{{ $whatsappStore->region_code }}{{ $whatsappStore->whatsapp_no }}" target="_blank" class="tm-status-chip">
                    <i class="fa-brands fa-whatsapp" style="color:#25D366"></i>
                    WhatsApp
                </a>
            @endif

            @if (!empty($whatsappStore->delivery_available))
                <div class="tm-status-chip">
                    <i class="fa-solid fa-motorcycle" style="color:var(--blue)"></i>
                    Delivery
                </div>
            @endif
        </div>
    </div>

    {{-- ================================================================
         ANNOUNCEMENT MARQUEE
    ================================================================ --}}
    @if (!empty($whatsappStore->store_announcement))
        <div class="tm-marquee">
            <div class="tm-marquee-track">
                @for ($i = 0; $i < 2; $i++)
                    @for ($j = 0; $j < 6; $j++)
                        <span>{{ $whatsappStore->store_announcement }}</span>
                    @endfor
                @endfor
            </div>
        </div>
    @endif

    {{-- ================================================================
         ORDER MODE — dine-in / takeout / delivery
    ================================================================ --}}
    <div class="tm-order-mode">
        <button class="tm-mode-btn active" data-mode="dine_in">
            <i class="fa-solid fa-utensils"></i> {{ __('messages.menu.dine_in') ?? 'Dine-in' }}
        </button>
        <button class="tm-mode-btn" data-mode="takeout">
            <i class="fa-solid fa-bag-shopping"></i> {{ __('messages.menu.takeout') ?? 'Takeout' }}
        </button>
        @if (!empty($whatsappStore->delivery_available))
            <button class="tm-mode-btn" data-mode="delivery">
                <i class="fa-solid fa-motorcycle"></i> {{ __('messages.menu.delivery') ?? 'Delivery' }}
            </button>
        @endif
    </div>

    {{-- ================================================================
         CATEGORY TABS
    ================================================================ --}}
    @php
        $categories = $whatsappStore->categories()->orderByRaw('sort IS NULL, sort ASC')->orderByDesc('created_at')->get();
    @endphp

    @if ($categories->count())
        <div class="tm-cats-wrap">
            <div class="tm-cats">
                <a href="#tm-cat-all" class="tm-cat-chip active" data-cat="all">
                    <i class="fa-solid fa-border-all" style="font-size:11px"></i> All
                </a>
                @foreach ($categories as $category)
                    <a href="#tm-cat-{{ $category->id }}" class="tm-cat-chip" data-cat="{{ $category->id }}">
                        @if ($category->image_url)
                            <img src="{{ $category->image_url }}" alt="{{ $category->name }}" loading="lazy">
                        @endif
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ================================================================
         FEATURED ("Chef's picks")
    ================================================================ --}}
    @php
        $allProducts = $whatsappStore->products()->orderByRaw('sort IS NULL, sort ASC')->latest()->get();
        $featuredProducts = $allProducts->filter(fn($p) => !empty($p->featured ?? false));
    @endphp

    @if ($featuredProducts->count())
        <div class="tm-section">
            <div class="tm-section-head">
                <div class="tm-section-title">
                    <i class="fa-solid fa-star" style="color:var(--blue);font-size:14px"></i>
                    {{ __('messages.menu.featured') ?? "Chef's Picks" }}
                </div>
                <div class="tm-section-count">{{ $featuredProducts->count() }} {{ __('messages.menu.items') ?? 'items' }}</div>
            </div>
            <div class="tm-featured-scroll">
                @foreach ($featuredProducts as $product)
                    <a href="{{ route('whatsapp.store.product.details', [$whatsappStore->url_alias, $product->id]) }}" class="tm-feat-card">
                        <div class="tm-feat-img">
                            <img src="{{ $product->images_url[0] ?? asset('assets/images/default_service.png') }}" alt="{{ $product->name }}" loading="lazy">
                            <div class="tm-feat-badge"><i class="fa-solid fa-star" style="font-size:8px"></i> Featured</div>
                        </div>
                        <div class="tm-feat-body">
                            <div class="tm-feat-name">{{ $product->name }}</div>
                            <div class="tm-feat-price">{{ currencyFormat($product->discount_price ?? $product->selling_price, 2, $product->currency->currency_code ?? '') }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ================================================================
         MENU BY CATEGORY
    ================================================================ --}}
    <div id="tm-cat-all">
        @if ($categories->count())
            @foreach ($categories as $category)
                @php
                    $categoryProducts = $allProducts->where('category_id', $category->id);
                @endphp
                @if ($categoryProducts->count())
                    <div class="tm-section tm-cat-group" id="tm-cat-{{ $category->id }}">
                        <div class="tm-section-head">
                            <div class="tm-section-title">{{ $category->name }}</div>
                            <div class="tm-section-count">{{ $categoryProducts->count() }} {{ __('messages.menu.items') ?? 'items' }}</div>
                        </div>
                        <div class="tm-items">
                            @foreach ($categoryProducts as $product)
                                @include('whatsapp_stores.templates.tagtoa_menu.partials.item-card', ['product' => $product])
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        @else
            <div class="tm-section">
                @if ($allProducts->count())
                    <div class="tm-items">
                        @foreach ($allProducts as $product)
                            @include('whatsapp_stores.templates.tagtoa_menu.partials.item-card', ['product' => $product])
                        @endforeach
                    </div>
                @else
                    <div class="tm-empty-cart">
                        <i class="fa-solid fa-utensils"></i>
                        {{ __('messages.whatsapp_stores_templates.item_not_added') ?? 'Menu coming soon' }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ================================================================
         BUSINESS HOURS
    ================================================================ --}}
    @if ($business_hours && !empty($businessDaysTime))
        <div class="tm-section">
            <div class="tm-section-head">
                <div class="tm-section-title">
                    <i class="fa-solid fa-clock" style="color:var(--blue);font-size:14px"></i>
                    {{ __('messages.business.business_hours') ?? 'Hours' }}
                </div>
            </div>
            <div class="tm-hours-grid">
                @foreach ($businessDaysTime as $dayKey => $dayTime)
                    <div class="tm-hours-row {{ $dayKey == $todayIndex ? 'today' : '' }}">
                        <div class="tm-hours-day">{{ __('messages.business.' . \App\Models\BusinessHour::DAY_OF_WEEK[$dayKey]) }}</div>
                        @if ($dayTime)
                            <div class="tm-hours-time">{{ $dayTime }}</div>
                        @else
                            <div class="tm-hours-time closed">{{ __('messages.common.closed') ?? 'Closed' }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ================================================================
         QR CODE
    ================================================================ --}}
    @if (!empty($whatsappStore->enable_download_qr_code))
        <div class="tm-section">
            <div class="tm-qr-card">
                <div class="tm-qr-img">
                    {!! QrCode::format('svg')->size(76)->color(0,0,0)->generate($whatsappStoreUrl) !!}
                </div>
                <div>
                    <div class="tm-qr-text-title">Scan this menu</div>
                    <div class="tm-qr-text-sub">Ouvre le menu TAGTOA directement sur ton téléphone, sans contact.</div>
                </div>
            </div>
        </div>
    @endif

    {{-- ================================================================
         FOOTER
    ================================================================ --}}
    <div class="tm-footer">Powered by <span>TAGTOA</span> MENU</div>

</div>

{{-- ================================================================
     STICKY BOTTOM BAR (default: share + whatsapp + call)
================================================================ --}}
<div class="tm-bottom-bar" id="tm-bottom-bar">
    <div class="tm-bottom-inner">
        @if ($whatsappStore->whatsapp_no)
            <a href="https://wa.me/{{ $whatsappStore->region_code }}{{ $whatsappStore->whatsapp_no }}" target="_blank" class="tm-bottom-action whatsapp">
                <i class="fa-brands fa-whatsapp"></i> WhatsApp
            </a>
        @endif
        @if ($whatsappStore->address)
            <a href="https://maps.google.com/?q={{ urlencode($whatsappStore->address) }}" target="_blank" class="tm-bottom-action">
                <i class="fa-solid fa-location-dot"></i> Map
            </a>
        @endif
        <button class="tm-bottom-action primary" id="tm-share-btn">
            <i class="fa-solid fa-share-nodes"></i> Share
        </button>
    </div>
</div>

{{-- ================================================================
     STICKY CART BAR (shows when cart has items)
================================================================ --}}
<div class="tm-cart-bar" id="tm-cart-bar">
    <div class="tm-cart-inner">
        <div class="tm-cart-info">
            <div class="tm-cart-count" id="tm-cart-count">0 items</div>
            <div class="tm-cart-total" id="tm-cart-total-display">0.00</div>
        </div>
        <button class="tm-cart-cta" id="tm-view-cart-btn">
            <i class="fa-solid fa-bag-shopping"></i> View Cart
        </button>
    </div>
</div>

{{-- ================================================================
     CART MODAL
================================================================ --}}
<div class="tm-modal-overlay" id="tm-cart-modal">
    <div class="tm-modal-sheet">
        <div class="tm-modal-head">
            <div class="tm-modal-title">Your Order</div>
            <button class="tm-modal-close" data-close="tm-cart-modal"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="tm-cart-list" id="tm-cart-list">
            <div class="tm-empty-cart">
                <i class="fa-solid fa-bag-shopping"></i>
                Your cart is empty
            </div>
        </div>
        <div class="tm-modal-footer">
            <div class="tm-summary-row"><span>Subtotal</span><span id="tm-subtotal">0.00</span></div>
            @if (!empty($discount))
                <div class="tm-summary-row"><span>Discount ({{ $discount }}%)</span><span id="tm-discount">- 0.00</span></div>
            @endif
            <div class="tm-summary-row grand"><span>Total</span><span id="tm-grandtotal">0.00</span></div>
            <button class="tm-checkout-btn" id="tm-checkout-btn">
                <i class="fa-solid fa-arrow-right"></i> Checkout
            </button>
        </div>
    </div>
</div>

{{-- ================================================================
     CHECKOUT MODAL
================================================================ --}}
<div class="tm-modal-overlay" id="tm-checkout-modal">
    <div class="tm-modal-sheet">
        <div class="tm-modal-head">
            <div class="tm-modal-title">Checkout</div>
            <button class="tm-modal-close" data-close="tm-checkout-modal"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:18px">
            <div class="tm-field">
                <label>Full Name</label>
                <input type="text" id="tm-cust-name" placeholder="Jean Baptiste" required>
            </div>
            <div class="tm-field">
                <label>Phone</label>
                <input type="tel" id="tm-cust-phone" placeholder="+509 ..." required>
            </div>
            <div class="tm-field" id="tm-address-field">
                <label>Delivery Address</label>
                <textarea id="tm-cust-address" rows="2" placeholder="Adresse, kartye, vil..."></textarea>
            </div>
            <div class="tm-field">
                <label>Payment Method — TAGTOA PAY</label>
                <div class="tm-payment-options" id="tm-payment-options">
                    <label class="tm-payment-opt selected">
                        <input type="radio" name="tm-payment" value="cash" checked>
                        <i class="fa-solid fa-money-bill-wave"></i> Cash
                    </label>
                    <label class="tm-payment-opt">
                        <input type="radio" name="tm-payment" value="cod">
                        <i class="fa-solid fa-box-open"></i> Cash on Delivery
                    </label>
                    <label class="tm-payment-opt">
                        <input type="radio" name="tm-payment" value="moncash">
                        <i class="fa-solid fa-mobile-screen-button"></i> MonCash
                    </label>
                    <label class="tm-payment-opt">
                        <input type="radio" name="tm-payment" value="natcash">
                        <i class="fa-solid fa-mobile-screen-button"></i> NatCash
                    </label>
                </div>
            </div>
            <div class="tm-field" id="tm-proof-field" style="display:none">
                <label>Upload Payment Proof</label>
                <input type="file" id="tm-payment-proof" accept="image/*,.pdf">
            </div>
        </div>
        <div class="tm-modal-footer">
            <div class="tm-summary-row grand"><span>Total to pay</span><span id="tm-checkout-total">0.00</span></div>
            <button class="tm-checkout-btn" id="tm-place-order-btn">
                <i class="fa-brands fa-whatsapp"></i> Send Order via WhatsApp
            </button>
        </div>
    </div>
</div>

{{-- TOAST --}}
<div class="tm-toast" id="tm-toast"><i class="fa-solid fa-circle-check"></i> <span id="tm-toast-text">Added to cart</span></div>

<script>
(function () {
    'use strict';

    /* ============================================================
       STATE
    ============================================================ */
    var cart = {};            // { productId: { id, name, price, qty, img } }
    var currencySymbol = @json($whatsappStore->products()->first()->currency->currency_code ?? '');
    var storeWhatsapp  = @json(($whatsappStore->region_code ?? '') . ($whatsappStore->whatsapp_no ?? ''));
    var storeName      = @json($whatsappStore->store_name);
    var storeUrl       = @json($whatsappStoreUrl ?? '');
    var discountPct    = @json($discount ?? 0);

    /* ============================================================
       HELPERS
    ============================================================ */
    function fmt(n) {
        return Number(n).toFixed(2) + (currencySymbol ? ' ' + currencySymbol : '');
    }

    function toast(msg) {
        var t = document.getElementById('tm-toast');
        document.getElementById('tm-toast-text').textContent = msg;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 1800);
    }

    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(btn.dataset.close); });
    });
    document.querySelectorAll('.tm-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    /* ============================================================
       CATEGORY TABS — scroll to section + active state
    ============================================================ */
    document.querySelectorAll('.tm-cat-chip').forEach(function (chip) {
        chip.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.tm-cat-chip').forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            var target = document.querySelector(chip.getAttribute('href'));
            if (target) {
                var offset = 110;
                window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
            }
        });
    });

    /* ============================================================
       ORDER MODE TOGGLE
    ============================================================ */
    var currentMode = 'dine_in';
    document.querySelectorAll('.tm-mode-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.tm-mode-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            currentMode = btn.dataset.mode;

            // toggle address field visibility
            var addressField = document.getElementById('tm-address-field');
            if (addressField) {
                addressField.style.display = (currentMode === 'delivery') ? 'block' : 'none';
            }

            // filter items by fulfilment capability (only if data attrs present)
            document.querySelectorAll('.tm-item').forEach(function (item) {
                var supports = item.dataset[currentMode];
                if (supports === '0') {
                    item.style.display = 'none';
                } else {
                    item.style.display = 'flex';
                }
            });
        });
    });

    /* ============================================================
       CART LOGIC
    ============================================================ */
    document.querySelectorAll('.tm-add-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (btn.disabled) return;

            var id    = btn.dataset.id;
            var name  = btn.dataset.name;
            var price = parseFloat(btn.dataset.price);
            var img   = btn.dataset.img;

            if (!cart[id]) {
                cart[id] = { id: id, name: name, price: price, qty: 0, img: img };
            }
            cart[id].qty += 1;

            renderCart();
            toast(name + ' added to cart');
        });
    });

    function renderCart() {
        var list = document.getElementById('tm-cart-list');
        var items = Object.values(cart).filter(function (i) { return i.qty > 0; });
        var subtotal = 0;
        var totalQty = 0;

        if (items.length === 0) {
            list.innerHTML = '<div class="tm-empty-cart"><i class="fa-solid fa-bag-shopping"></i>Your cart is empty</div>';
        } else {
            list.innerHTML = items.map(function (item) {
                subtotal += item.price * item.qty;
                totalQty += item.qty;
                return '<div class="tm-cart-row">' +
                    '<img class="tm-cart-row-img" src="' + item.img + '" alt="' + item.name + '">' +
                    '<div class="tm-cart-row-info">' +
                        '<div class="tm-cart-row-name">' + item.name + '</div>' +
                        '<div class="tm-cart-row-price">' + fmt(item.price) + '</div>' +
                    '</div>' +
                    '<div class="tm-qty-control">' +
                        '<button class="tm-qty-btn" data-action="dec" data-id="' + item.id + '"><i class="fa-solid fa-minus"></i></button>' +
                        '<div class="tm-qty-val">' + item.qty + '</div>' +
                        '<button class="tm-qty-btn" data-action="inc" data-id="' + item.id + '"><i class="fa-solid fa-plus"></i></button>' +
                    '</div>' +
                '</div>';
            }).join('');
        }

        var discountAmt = subtotal * (discountPct / 100);
        var grandTotal = subtotal - discountAmt;

        document.getElementById('tm-subtotal').textContent = fmt(subtotal);
        var discountEl = document.getElementById('tm-discount');
        if (discountEl) discountEl.textContent = '- ' + fmt(discountAmt);
        document.getElementById('tm-grandtotal').textContent = fmt(grandTotal);
        document.getElementById('tm-checkout-total').textContent = fmt(grandTotal);

        document.getElementById('tm-cart-count').textContent = totalQty + (totalQty === 1 ? ' item' : ' items');
        document.getElementById('tm-cart-total-display').textContent = fmt(grandTotal);

        var cartBar = document.getElementById('tm-cart-bar');
        var bottomBar = document.getElementById('tm-bottom-bar');
        if (totalQty > 0) {
            cartBar.classList.add('visible');
            bottomBar.classList.add('hidden');
        } else {
            cartBar.classList.remove('visible');
            bottomBar.classList.remove('hidden');
        }

        // re-bind qty buttons
        list.querySelectorAll('.tm-qty-btn').forEach(function (b) {
            b.addEventListener('click', function () {
                var id = b.dataset.id;
                if (b.dataset.action === 'inc') {
                    cart[id].qty += 1;
                } else {
                    cart[id].qty = Math.max(0, cart[id].qty - 1);
                }
                renderCart();
            });
        });
    }

    document.getElementById('tm-view-cart-btn').addEventListener('click', function () {
        openModal('tm-cart-modal');
    });

    /* ============================================================
       CHECKOUT FLOW
    ============================================================ */
    document.getElementById('tm-checkout-btn').addEventListener('click', function () {
        var hasItems = Object.values(cart).some(function (i) { return i.qty > 0; });
        if (!hasItems) { toast('Your cart is empty'); return; }
        closeModal('tm-cart-modal');
        openModal('tm-checkout-modal');
    });

    // Payment method selection
    document.querySelectorAll('input[name="tm-payment"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.tm-payment-opt').forEach(function (opt) { opt.classList.remove('selected'); });
            radio.closest('.tm-payment-opt').classList.add('selected');

            var proofField = document.getElementById('tm-proof-field');
            if (radio.value === 'moncash' || radio.value === 'natcash') {
                proofField.style.display = 'block';
            } else {
                proofField.style.display = 'none';
            }
        });
    });

    document.getElementById('tm-place-order-btn').addEventListener('click', function () {
        var name = document.getElementById('tm-cust-name').value.trim();
        var phone = document.getElementById('tm-cust-phone').value.trim();
        var address = document.getElementById('tm-cust-address').value.trim();
        var payment = document.querySelector('input[name="tm-payment"]:checked').value;

        if (!name || !phone) { toast('Please fill name and phone'); return; }
        if (currentMode === 'delivery' && !address) { toast('Please add a delivery address'); return; }

        var items = Object.values(cart).filter(function (i) { return i.qty > 0; });
        var subtotal = items.reduce(function (sum, i) { return sum + i.price * i.qty; }, 0);
        var discountAmt = subtotal * (discountPct / 100);
        var grandTotal = subtotal - discountAmt;

        var modeLabel = { dine_in: 'Dine-in', takeout: 'Takeout', delivery: 'Delivery' }[currentMode] || currentMode;
        var paymentLabel = { cash: 'Cash', cod: 'Cash on Delivery', moncash: 'MonCash', natcash: 'NatCash' }[payment] || payment;

        var msg = '*New Order — ' + storeName + '*\n\n';
        msg += '👤 ' + name + '\n📞 ' + phone + '\n';
        if (currentMode === 'delivery') msg += '📍 ' + address + '\n';
        msg += '🍽️ Mode: ' + modeLabel + '\n💳 Payment: ' + paymentLabel + '\n\n';
        msg += items.map(function (i) { return '• ' + i.qty + 'x ' + i.name + ' — ' + fmt(i.price * i.qty); }).join('\n');
        msg += '\n\n*Total: ' + fmt(grandTotal) + '*';
        msg += '\n\n' + storeUrl;

        var waUrl = 'https://wa.me/' + storeWhatsapp + '?text=' + encodeURIComponent(msg);
        window.open(waUrl, '_blank');

        closeModal('tm-checkout-modal');
        toast('Order sent via WhatsApp!');
    });

    /* ============================================================
       SHARE BUTTON
    ============================================================ */
    document.getElementById('tm-share-btn').addEventListener('click', function () {
        var url = window.location.href;
        if (navigator.share) {
            navigator.share({ title: storeName + ' — TAGTOA MENU', url: url }).catch(function () {});
        } else {
            navigator.clipboard.writeText(url).then(function () { toast('Link copied!'); });
        }
    });

    /* ============================================================
       SCROLL-SPY — highlight active category tab
    ============================================================ */
    var sections = document.querySelectorAll('.tm-cat-group');
    if (sections.length && 'IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var id = entry.target.id.replace('tm-cat-', '');
                    document.querySelectorAll('.tm-cat-chip').forEach(function (c) {
                        c.classList.toggle('active', c.dataset.cat === id);
                    });
                }
            });
        }, { rootMargin: '-120px 0px -60% 0px', threshold: 0 });

        sections.forEach(function (s) { observer.observe(s); });
    }
})();
</script>

</body>
</html>

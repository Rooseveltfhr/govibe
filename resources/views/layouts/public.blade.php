<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'GOVIBE Innovation Hub — Haïti')</title>
  <meta name="description" content="@yield('description', 'GOVIBE – Écosystème d\'innovation en Haïti. Coworking, Startup Lab, Call Center, IA, Formation, Crédit Digital.')">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Anton&family=Barlow+Condensed:wght@700;900&family=DM+Sans:wght@400;500;600;700&family=Orbitron:wght@700;900&family=Exo+2:wght@400;600;700;900&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    /* ===== RESET & BASE ===== */
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:#fff; color:#0f172a; overflow-x:hidden; line-height:1.5; }
    a { text-decoration:none; color:inherit; }
    img { max-width:100%; display:block; }
    button { font-family:'DM Sans',sans-serif; }

    /* ===== BRAND COLORS ===== */
    :root {
      --gv-primary: #DC2626;
      --gv-primary-dark: #991b1b;
      --gv-green: #10B981;
      --gv-green-dark: #059669;
      --gv-yellow: #ffe800;
      --gv-blue: #2563EB;
      --gv-red: #EF4444;
      --gv-red-dark: #DC2626;
    }

    /* ===== TYPOGRAPHY — Anton for all headings ===== */
    h1,h2,h3,h4,h5,.gv-h2,.gv-title,.about-heading,.gv-card-title {
      font-family:'Anton',sans-serif;
      letter-spacing:0.02em;
    }

    /* ===== UTILITIES ===== */
    .gv-wrap  { max-width:1160px; margin:0 auto; padding:0 20px; }
    .gv-pad   { padding:80px 20px; }
    .gv-shadow    { box-shadow:0 4px 24px rgba(0,0,0,.08); }
    .gv-shadow-lg { box-shadow:0 20px 60px rgba(0,0,0,.14); }
    .gv-radius    { border-radius:12px; }
    .gv-radius-lg { border-radius:20px; }

    /* Section badge */
    .gv-stag {
      display:inline-flex; align-items:center; gap:.45rem;
      font-size:.72rem; font-weight:700; letter-spacing:.22em;
      text-transform:uppercase; padding:.28rem .88rem;
      border-radius:50px; color:var(--gv-primary);
      background:rgba(220,38,38,.08); border:1px solid rgba(220,38,38,.18);
      margin-bottom:.85rem;
    }
    .gv-h2 {
      font-size:clamp(1.85rem,4.5vw,2.85rem);
      line-height:1.1; margin-bottom:.75rem; color:#0F172A;
    }
    .gv-h2-light { color:#fff; }
    .gv-sub {
      font-size:.97rem; color:#64748B;
      max-width:540px; line-height:1.72;
    }
    .gv-sub-light { color:rgba(255,255,255,.6); }
    .gv-head.center { text-align:center; }
    .gv-head.center .gv-sub { margin:0 auto; }

    /* Buttons */
    .gv-btn-prim {
      display:inline-flex; align-items:center; gap:.4rem;
      background:linear-gradient(135deg,var(--gv-primary),var(--gv-primary-dark));
      color:#fff; padding:.82rem 1.75rem; border-radius:50px;
      font-weight:700; font-size:.93rem; transition:all .3s;
      border:none; cursor:pointer;
    }
    .gv-btn-prim:hover { transform:translateY(-3px); box-shadow:0 14px 30px rgba(220,38,38,.4); color:#fff; }
    .gv-btn-prim.green { background:linear-gradient(135deg,var(--gv-green),var(--gv-green-dark)); }
    .gv-btn-prim.green:hover { box-shadow:0 14px 30px rgba(16,185,129,.4); }
    .gv-btn-outline {
      display:inline-flex; align-items:center; gap:.4rem;
      background:transparent; color:var(--gv-primary);
      padding:.82rem 1.75rem; border-radius:50px;
      border:2px solid var(--gv-primary); font-weight:700;
      font-size:.93rem; transition:all .3s; cursor:pointer;
    }
    .gv-btn-outline:hover { background:var(--gv-primary); color:#fff; }

    /* ===== SLIDE-UP ANIMATION ===== */
    .slide-up {
      opacity:0; transform:translateY(40px);
      transition:opacity .7s ease, transform .7s ease;
    }
    .slide-up.visible { opacity:1; transform:translateY(0); }
    .slide-up.delay-1 { transition-delay:.1s; }
    .slide-up.delay-2 { transition-delay:.2s; }
    .slide-up.delay-3 { transition-delay:.3s; }
    .slide-up.delay-4 { transition-delay:.4s; }

    /* ===== NAVBAR ===== */
    .gv-navbar {
      position:sticky; top:0;
      background:rgba(10,15,30,.96); backdrop-filter:blur(12px);
      z-index:1000; border-bottom:1px solid rgba(255,255,255,.08);
      padding:.85rem 2rem;
    }
    .gv-nav-container {
      max-width:1280px; margin:0 auto;
      display:flex; align-items:center; justify-content:space-between;
      gap:1rem; flex-wrap:wrap;
    }
    .gv-logo {
      display:flex; align-items:center;
      text-decoration:none; flex-shrink:0;
    }
    .gv-logo img { height:42px; width:auto; }
    .gv-logo-fallback {
      font-family:'Anton',sans-serif; font-size:1.8rem;
      background:linear-gradient(135deg,var(--gv-primary),#ff6b6b);
      -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
    }
    .gv-nav-menu { display:flex; list-style:none; gap:1.6rem; margin:0; padding:0; }
    .gv-nav-menu li { position:relative; }
    .gv-nav-menu > li > a {
      color:rgba(255,255,255,.85); font-weight:500; font-size:.92rem;
      padding:.3rem 0; display:flex; align-items:center; gap:.3rem;
      transition:color .2s;
    }
    .gv-nav-menu > li > a:hover { color:#fff; }
    /* Dropdowns */
    .gv-dropdown-menu, .gv-dropdown-submenu {
      position:absolute; top:calc(100% + 8px); left:0;
      background:#111827; list-style:none; padding:.5rem 0;
      border-radius:10px; box-shadow:0 12px 30px rgba(0,0,0,.3);
      opacity:0; visibility:hidden;
      transform:translateY(8px); transition:all .25s ease;
      z-index:200; min-width:220px;
      border:1px solid rgba(255,255,255,.06);
    }
    .gv-dropdown-menu li, .gv-dropdown-submenu li { padding:0; }
    .gv-dropdown-menu a, .gv-dropdown-submenu a {
      display:block; padding:.55rem 1.1rem;
      color:rgba(255,255,255,.75); font-size:.88rem; white-space:nowrap;
      transition:color .2s, background .2s;
    }
    .gv-dropdown-menu a:hover, .gv-dropdown-submenu a:hover {
      color:#fff; background:rgba(255,255,255,.07);
    }
    .gv-dropdown:hover > .gv-dropdown-menu,
    .gv-dropdown-sub:hover > .gv-dropdown-submenu {
      opacity:1; visibility:visible; transform:translateY(0);
    }
    .gv-dropdown-sub { position:relative; }
    .gv-dropdown-submenu { top:0; left:100%; }
    /* Nav right */
    .gv-nav-right { display:flex; align-items:center; gap:.6rem; flex-shrink:0; }
    .gv-nav-btn {
      background:linear-gradient(135deg,var(--gv-primary),var(--gv-primary-dark));
      padding:.52rem 1.15rem; border-radius:40px; color:#fff;
      font-weight:700; font-size:.85rem; transition:all .3s;
      display:inline-flex; align-items:center; gap:.4rem;
    }
    .gv-nav-btn:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(220,38,38,.35); color:#fff; }
    .gv-nav-call {
      background:linear-gradient(135deg,var(--gv-green),var(--gv-green-dark));
      padding:.52rem 1.15rem; border-radius:40px; color:#fff;
      font-weight:700; font-size:.85rem; transition:all .3s;
      display:inline-flex; align-items:center; gap:.4rem;
    }
    .gv-nav-call:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(16,185,129,.4); color:#fff; }
    /* Language switcher */
    .gv-lang-switcher { display:flex; gap:.3rem; }
    .lang-btn {
      background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);
      color:rgba(255,255,255,.6); padding:.28rem .6rem; border-radius:6px;
      font-size:.72rem; font-weight:700; cursor:pointer; transition:all .2s;
      letter-spacing:.05em;
    }
    .lang-btn:hover { background:rgba(255,255,255,.15); color:#fff; }
    .lang-btn.active { background:var(--gv-primary); border-color:var(--gv-primary); color:#fff; }
    /* Mobile toggle */
    .gv-nav-toggle { display:none; flex-direction:column; cursor:pointer; gap:4px; }
    .gv-nav-toggle span { width:24px; height:2px; background:#fff; border-radius:2px; transition:.3s; }

    /* ===== FOOTER ===== */
    .gv-footer { background:#050a14; padding:3.5rem 2rem 1.5rem; }
    .gv-footer-grid {
      max-width:1200px; margin:0 auto;
      display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:2.5rem;
    }
    .gv-footer-col h4 {
      font-family:'Anton',sans-serif; color:#fff;
      margin-bottom:1.1rem; font-size:1rem; letter-spacing:.05em;
    }
    .gv-footer-col p { color:#94a3b8; font-size:.88rem; line-height:1.7; }
    .gv-footer-col ul { list-style:none; padding:0; }
    .gv-footer-col li { margin-bottom:.55rem; }
    .gv-footer-col a { color:#94a3b8; font-size:.88rem; transition:color .2s; display:flex; align-items:center; gap:.4rem; }
    .gv-footer-col a:hover { color:var(--gv-primary); }
    .gv-footer-bottom {
      max-width:1200px; margin:2rem auto 0;
      padding-top:1.5rem; border-top:1px solid rgba(255,255,255,.08);
      display:flex; flex-wrap:wrap; justify-content:space-between;
      align-items:center; gap:1rem; font-size:.82rem; color:#64748b;
    }
    .gv-footer-social { display:flex; gap:.8rem; }
    .gv-footer-social a {
      width:36px; height:36px; border-radius:50%;
      background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
      display:flex; align-items:center; justify-content:center;
      color:#94a3b8; font-size:.85rem; transition:all .2s;
    }
    .gv-footer-social a:hover { background:var(--gv-primary); border-color:var(--gv-primary); color:#fff; }
    .gv-footer-badge {
      display:inline-flex; align-items:center; gap:.4rem;
      background:rgba(220,38,38,.12); border:1px solid rgba(220,38,38,.25);
      border-radius:50px; padding:.25rem .75rem; font-size:.72rem;
      color:var(--gv-primary); font-weight:700; letter-spacing:.1em;
    }

    /* ===== PAGE HERO (for inner pages) ===== */
    .page-hero {
      background:linear-gradient(135deg,#0a0000 0%,#1a0004 50%,#050505 100%);
      padding:100px 2rem 70px; text-align:center; position:relative; overflow:hidden;
    }
    .page-hero::before {
      content:''; position:absolute; inset:0;
      background-image:linear-gradient(rgba(220,38,38,.05) 1px,transparent 1px),
        linear-gradient(90deg,rgba(220,38,38,.05) 1px,transparent 1px);
      background-size:50px 50px; pointer-events:none;
    }
    .page-hero-inner { position:relative; z-index:2; }
    .page-hero h1 { color:#fff; font-size:clamp(2rem,6vw,3.5rem); margin:.8rem 0; }
    .page-hero p { color:rgba(255,255,255,.65); max-width:600px; margin:0 auto 1.5rem; font-size:1rem; line-height:1.75; }
    .page-breadcrumb {
      display:flex; align-items:center; justify-content:center; gap:.5rem;
      font-size:.82rem; color:rgba(255,255,255,.45); margin-bottom:.5rem;
    }
    .page-breadcrumb a { color:var(--gv-primary); }
    .page-breadcrumb a:hover { text-decoration:underline; }

    /* ===== RESPONSIVE NAVBAR ===== */
    @media (max-width:900px) {
      .gv-nav-menu {
        position:fixed; top:65px; left:-100%; width:100%;
        height:calc(100vh - 65px); background:#0a0f1e;
        flex-direction:column; align-items:flex-start;
        padding:1.5rem 1.5rem; gap:0; overflow-y:auto;
        transition:left .3s;
      }
      .gv-nav-menu.active { left:0; }
      .gv-nav-toggle { display:flex; }
      .gv-nav-menu > li { width:100%; border-bottom:1px solid rgba(255,255,255,.06); padding:.5rem 0; }
      .gv-nav-menu > li > a { padding:.5rem 0; width:100%; }
      .gv-dropdown-menu,.gv-dropdown-submenu {
        position:static; background:#1e293b; box-shadow:none;
        opacity:1; visibility:visible; transform:none; display:none;
        border:none; border-radius:8px; margin-top:.3rem; margin-left:.5rem;
      }
      .gv-dropdown.active .gv-dropdown-menu,
      .gv-dropdown-sub.active .gv-dropdown-submenu { display:block; }
      .gv-nav-right { display:none; }
      .gv-lang-switcher { order:-1; }
    }
    @media (max-width:576px) {
      .gv-navbar { padding:.75rem 1rem; }
      .gv-footer-bottom { flex-direction:column; text-align:center; }
    }
  </style>

  @yield('head')
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="gv-navbar" id="gv-navbar">
  <div class="gv-nav-container">
    <a href="{{ route('home') }}" class="gv-logo">
      <img src="{{ asset('images/logo.govibe.png') }}" alt="GOVIBE Innovation Hub"
           onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
      <span class="gv-logo-fallback" style="display:none;">GOVIBE</span>
    </a>

    <div class="gv-nav-toggle" id="nav-toggle" aria-label="Menu">
      <span></span><span></span><span></span>
    </div>

    <ul class="gv-nav-menu" id="nav-menu">
      <li><a href="{{ route('home') }}" data-i18n="nav_home">Accueil</a></li>
      <li><a href="{{ route('about') }}" data-i18n="nav_about">À propos</a></li>

      <li class="gv-dropdown">
        <a href="{{ route('services') }}" class="gv-dropdown-toggle" data-i18n="nav_all_services">Nos Services <i class="fas fa-chevron-down fa-xs"></i></a>
        <ul class="gv-dropdown-menu">
          <li><a href="{{ route('services') }}#media"><i class="fas fa-bullhorn"></i> Media & Digital</a></li>
          <li><a href="{{ route('services') }}#dev"><i class="fas fa-code"></i> Développement & IA</a></li>
          <li><a href="{{ route('services') }}#academy-cat"><i class="fas fa-graduation-cap"></i> Academy & Formations</a></li>
          <li><a href="{{ route('services') }}#coworking-cat"><i class="fas fa-building"></i> Coworking Space</a></li>
          <li><a href="{{ route('services') }}#startup"><i class="fas fa-rocket"></i> Accompagnement Startups</a></li>
          <li><a href="{{ route('services') }}#solutions"><i class="fas fa-layer-group"></i> Nos Solutions SaaS</a></li>
        </ul>
      </li>

      <li class="gv-dropdown">
        <a href="#" class="gv-dropdown-toggle" data-i18n="nav_ecosystem">Écosystème <i class="fas fa-chevron-down fa-xs"></i></a>
        <ul class="gv-dropdown-menu">
          <li><a href="{{ route('coworking') }}"><i class="fas fa-building"></i> Coworking Space</a></li>
          <li><a href="{{ route('startup-lab') }}"><i class="fas fa-flask"></i> Startup Lab</a></li>
          <li><a href="{{ route('academy') }}"><i class="fas fa-graduation-cap"></i> GOVIBE Academy</a></li>
          <li><a href="https://1207.3cx.cloud/supporttechnical" target="_blank" rel="noopener"><i class="fas fa-headset"></i> Call Center</a></li>
        </ul>
      </li>

      <li class="gv-dropdown">
        <a href="#" class="gv-dropdown-toggle" data-i18n="nav_saas">Plateformes SaaS <i class="fas fa-chevron-down fa-xs"></i></a>
        <ul class="gv-dropdown-menu">
          <li><a href="{{ route('erp.login') }}"><i class="fas fa-chart-bar"></i> GOVIBE ERP</a></li>
          <li><a href="{{ route('academy') }}"><i class="fas fa-graduation-cap"></i> GOVIBE Academy</a></li>
          <li><a href="#"><i class="fas fa-hospital"></i> Hospify</a></li>
          <li><a href="#"><i class="fas fa-store"></i> Tchekela</a></li>
        </ul>
      </li>

      <li class="gv-dropdown">
        <a href="#" class="gv-dropdown-toggle" data-i18n="nav_programmes">Programmes <i class="fas fa-chevron-down fa-xs"></i></a>
        <ul class="gv-dropdown-menu">
          <li><a href="{{ route('home') }}#programmes"><i class="fas fa-seedling"></i> Incubation Startup</a></li>
          <li><a href="{{ route('inscription.create') }}"><i class="fas fa-book"></i> S'inscrire à une formation</a></li>
          <li><a href="{{ route('home') }}#programmes"><i class="fas fa-credit-card"></i> Crédit Digital</a></li>
        </ul>
      </li>

      <li><a href="{{ route('home') }}#reservation" data-i18n="nav_contact">Contact</a></li>
    </ul>

    <div class="gv-nav-right">
      <div class="gv-lang-switcher">
        <button class="lang-btn active" onclick="setLang('fr')">FR</button>
        <button class="lang-btn" onclick="setLang('en')">EN</button>
        <button class="lang-btn" onclick="setLang('cr')">CR</button>
        <button class="lang-btn" onclick="setLang('es')">ES</button>
      </div>
      <a href="https://1207.3cx.cloud/supporttechnical" class="gv-nav-call" target="_blank" rel="noopener">
        <i class="fas fa-headset"></i> <span data-i18n="nav_callcenter">Call Center</span>
      </a>
      <a href="{{ route('home') }}#reservation" class="gv-nav-btn">
        <i class="fas fa-calendar-alt"></i> <span data-i18n="nav_start">Commencer</span>
      </a>
    </div>
  </div>
</nav>

@yield('content')

<!-- ===== FOOTER ===== -->
<footer class="gv-footer">
  <div class="gv-footer-grid">
    <div class="gv-footer-col">
      <h4><i class="fas fa-rocket"></i> GOVIBE</h4>
      <p>GOVIBE Innovation Hub — Écosystème d'innovation haïtien combinant coworking, technologie, IA et formation depuis 2020.</p>
      <div style="margin-top:1rem; display:flex; gap:.6rem; flex-wrap:wrap;">
        <span class="gv-footer-badge">GOVIBE STARTUP LLC</span>
        <span class="gv-footer-badge">MCI Haïti</span>
      </div>
    </div>
    <div class="gv-footer-col">
      <h4><i class="fas fa-cog"></i> Nos Services</h4>
      <ul>
        <li><a href="{{ route('services') }}"><i class="fas fa-th-large fa-xs"></i> Tous les services</a></li>
        <li><a href="{{ route('services') }}#media"><i class="fas fa-bullhorn fa-xs"></i> Media & Digital</a></li>
        <li><a href="{{ route('services') }}#dev"><i class="fas fa-code fa-xs"></i> Développement & IA</a></li>
        <li><a href="{{ route('coworking') }}"><i class="fas fa-building fa-xs"></i> Coworking Space</a></li>
        <li><a href="{{ route('startup-lab') }}"><i class="fas fa-flask fa-xs"></i> Startup Lab</a></li>
        <li><a href="https://1207.3cx.cloud/supporttechnical" target="_blank" rel="noopener"><i class="fas fa-headset fa-xs"></i> Call Center</a></li>
      </ul>
    </div>
    <div class="gv-footer-col">
      <h4><i class="fas fa-graduation-cap"></i> Academy & Programmes</h4>
      <ul>
        <li><a href="{{ route('academy') }}"><i class="fas fa-book fa-xs"></i> GOVIBE Academy</a></li>
        <li><a href="{{ route('inscription.create') }}"><i class="fas fa-user-plus fa-xs"></i> S'inscrire</a></li>
        <li><a href="{{ route('home') }}#programmes"><i class="fas fa-seedling fa-xs"></i> Incubation</a></li>
        <li><a href="{{ route('home') }}#programmes"><i class="fas fa-credit-card fa-xs"></i> Crédit Digital</a></li>
        <li><a href="{{ route('about') }}"><i class="fas fa-info-circle fa-xs"></i> À propos</a></li>
        <li><a href="#"><i class="fas fa-newspaper fa-xs"></i> News & Blog</a></li>
        <li><a href="#"><i class="fas fa-briefcase fa-xs"></i> Jobs & Carrières</a></li>
      </ul>
    </div>
    <div class="gv-footer-col">
      <h4><i class="fas fa-map-marker-alt"></i> Contact & Réseaux</h4>
      <ul>
        <li><a href="mailto:contact@govibeht.com"><i class="fas fa-envelope fa-xs"></i> contact@govibeht.com</a></li>
        <li><a href="tel:+50933988754"><i class="fas fa-phone fa-xs"></i> +509 3398-8754</a></li>
        <li><a href="https://wa.me/50948174124" target="_blank" rel="noopener"><i class="fab fa-whatsapp fa-xs"></i> WhatsApp</a></li>
        <li><a href="https://facebook.com/govibe" target="_blank" rel="noopener"><i class="fab fa-facebook-f fa-xs"></i> Facebook</a></li>
        <li><a href="https://instagram.com/govibeht" target="_blank" rel="noopener"><i class="fab fa-instagram fa-xs"></i> Instagram</a></li>
        <li><a href="{{ route('erp.login') }}"><i class="fas fa-sign-in-alt fa-xs"></i> ERP Login</a></li>
        <li><a href="{{ route('admin.login') }}"><i class="fas fa-user-shield fa-xs"></i> Admin Academy</a></li>
      </ul>
    </div>
  </div>
  <div class="gv-footer-bottom">
    <div class="gv-footer-social">
      <a href="https://facebook.com/govibe" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
      <a href="https://linkedin.com/company/govibe" target="_blank" rel="noopener" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
      <a href="https://wa.me/50933988754" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
      <a href="https://instagram.com/govibeht" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
    </div>
    <div>
      <p style="color:#475569; font-size:.8rem;">
        <i class="fas fa-map-marker-alt" style="color:var(--gv-primary);"></i> Port-au-Prince, Haïti &nbsp;|&nbsp;
        <i class="fas fa-envelope" style="color:var(--gv-primary);"></i> contact@govibeht.com &nbsp;|&nbsp;
        <i class="fas fa-phone" style="color:var(--gv-primary);"></i> +509 3398-8754
      </p>
    </div>
    <div>&copy; {{ date('Y') }} GOVIBE STARTUP LLC. Tous droits réservés.</div>
  </div>
</footer>

<!-- ===== SHARED SCRIPTS ===== -->
<script>
/* ── TRANSLATIONS ─────────────────────────────────── */
const GV_TRANSLATIONS = {
  fr: {
    nav_home:'Accueil', nav_about:'À propos', nav_all_services:'Nos Services',
    nav_ecosystem:'Écosystème', nav_saas:'Plateformes SaaS', nav_programmes:'Programmes',
    nav_contact:'Contact', nav_callcenter:'Call Center', nav_start:'Commencer',
  },
  en: {
    nav_home:'Home', nav_about:'About', nav_all_services:'Our Services',
    nav_ecosystem:'Ecosystem', nav_saas:'SaaS Platforms', nav_programmes:'Programs',
    nav_contact:'Contact', nav_callcenter:'Call Center', nav_start:'Get Started',
  },
  cr: {
    nav_home:'Akèy', nav_about:'Sou Nou', nav_all_services:'Sèvis Nou Yo',
    nav_ecosystem:'Ekosistèm', nav_saas:'Platfòm SaaS', nav_programmes:'Pwogram',
    nav_contact:'Kontakt', nav_callcenter:'Sant Apèl', nav_start:'Kòmanse',
  },
  es: {
    nav_home:'Inicio', nav_about:'Acerca de', nav_all_services:'Nuestros Servicios',
    nav_ecosystem:'Ecosistema', nav_saas:'Plataformas SaaS', nav_programmes:'Programas',
    nav_contact:'Contacto', nav_callcenter:'Call Center', nav_start:'Comenzar',
  }
};

let GV_LANG = localStorage.getItem('gv_lang') || 'fr';

function setLang(lang) {
  GV_LANG = lang;
  localStorage.setItem('gv_lang', lang);
  const t = GV_TRANSLATIONS[lang] || GV_TRANSLATIONS.fr;
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.dataset.i18n;
    if (t[key]) el.textContent = t[key];
  });
  document.querySelectorAll('.lang-btn').forEach(b => {
    b.classList.toggle('active', b.textContent.toLowerCase() === lang);
  });
  if (typeof GV_PAGE_TRANSLATIONS === 'function') GV_PAGE_TRANSLATIONS(lang);
}

/* ── MOBILE MENU ─────────────────────────────────── */
const navToggle = document.getElementById('nav-toggle');
const navMenu   = document.getElementById('nav-menu');
if (navToggle) {
  navToggle.addEventListener('click', () => navMenu.classList.toggle('active'));
}
document.querySelectorAll('.gv-nav-menu a:not(.gv-dropdown-toggle)').forEach(link => {
  link.addEventListener('click', () => navMenu.classList.remove('active'));
});
document.querySelectorAll('.gv-dropdown').forEach(dd => {
  const toggle = dd.querySelector('.gv-dropdown-toggle');
  if (toggle) {
    toggle.addEventListener('click', e => {
      if (window.innerWidth <= 900) {
        e.preventDefault();
        dd.classList.toggle('active');
      }
    });
  }
});
document.querySelectorAll('.gv-dropdown-sub').forEach(sub => {
  const a = sub.querySelector('a');
  if (a) {
    a.addEventListener('click', e => {
      if (window.innerWidth <= 900) {
        e.preventDefault();
        sub.classList.toggle('active');
      }
    });
  }
});
window.addEventListener('resize', () => {
  if (window.innerWidth > 900) {
    document.querySelectorAll('.gv-dropdown,.gv-dropdown-sub').forEach(d => d.classList.remove('active'));
  }
});

/* ── SLIDE-UP OBSERVER ───────────────────────────── */
const slideObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
      slideObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.slide-up').forEach(el => slideObserver.observe(el));

/* ── INIT LANG ───────────────────────────────────── */
setLang(GV_LANG);
</script>

@yield('scripts')

</body>
</html>

@extends('layouts.public')

@section('title', 'GOVIBE Startup Lab — Développement Logiciel & Digital')

@section('head')
<style>
:root { --gv-primary: #DC2626; }

/* ── Hero ─────────────────────────────────────────── */
.sl-hero {
    background: linear-gradient(135deg,#0a0000 0%,#1a0004 50%,#06060f 100%);
    min-height: 80vh;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    padding: 130px 0 80px;
}
.sl-hero::before {
    content:'';
    position:absolute;
    inset:0;
    background:
        radial-gradient(ellipse 70% 60% at 90% 20%, rgba(220,38,38,.22) 0%, transparent 65%),
        radial-gradient(ellipse 50% 40% at 10% 80%, rgba(220,38,38,.12) 0%, transparent 60%);
    pointer-events:none;
}
.sl-grid-bg {
    position:absolute; inset:0;
    background-image:
        linear-gradient(rgba(220,38,38,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(220,38,38,.04) 1px, transparent 1px);
    background-size:60px 60px;
    pointer-events:none;
}
.sl-tag {
    display:inline-block;
    background:rgba(220,38,38,.15);
    border:1px solid rgba(220,38,38,.35);
    color:#f87171;
    font-size:.78rem; font-weight:700;
    letter-spacing:.1em; text-transform:uppercase;
    padding:.35rem 1rem; border-radius:2rem;
    margin-bottom:1.5rem;
}
.sl-hero h1 {
    font-family:'Anton',sans-serif;
    font-size:clamp(2.4rem,7vw,4.8rem);
    color:#fff; line-height:1.0;
    margin-bottom:1.2rem;
}
.sl-hero h1 span { color:var(--gv-primary); }
.sl-hero p.lead {
    color:rgba(255,255,255,.72);
    font-size:1.1rem;
    max-width:540px;
    line-height:1.75;
    margin-bottom:2.2rem;
}
.sl-hero-nav {
    display:flex; flex-wrap:wrap; gap:.6rem;
    margin-bottom:2rem;
}
.sl-hero-nav a {
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.12);
    color:rgba(255,255,255,.7);
    font-size:.8rem; font-weight:600;
    padding:.4rem .9rem;
    border-radius:.4rem;
    text-decoration:none;
    transition:all .25s;
}
.sl-hero-nav a:hover {
    background:rgba(220,38,38,.15);
    border-color:rgba(220,38,38,.4);
    color:#fff;
}
.sl-hero-btns { display:flex; gap:1rem; flex-wrap:wrap; }

/* ── Services anchor sections ────────────────────── */
.sl-section {
    padding:90px 0;
    position:relative;
    scroll-margin-top:80px;
}
.sl-section:nth-child(odd) { background:#06060f; }
.sl-section:nth-child(even) { background:#0a0000; }

.sl-section-inner {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:3.5rem;
    align-items:center;
}
.sl-section-inner.reverse { direction:rtl; }
.sl-section-inner.reverse > * { direction:ltr; }
@media(max-width:900px){
    .sl-section-inner, .sl-section-inner.reverse { grid-template-columns:1fr; direction:ltr; }
}

.sl-section-eyebrow {
    font-size:.73rem; font-weight:700;
    letter-spacing:.12em; text-transform:uppercase;
    color:var(--gv-primary); margin-bottom:.6rem;
}
.sl-section h2 {
    font-family:'Anton',sans-serif;
    font-size:clamp(1.8rem,4vw,2.8rem);
    color:#fff; line-height:1.1;
    margin-bottom:1rem;
}
.sl-section p {
    color:rgba(255,255,255,.65);
    font-size:.98rem; line-height:1.75;
    margin-bottom:1rem;
}
.sl-tech-tags {
    display:flex; flex-wrap:wrap; gap:.5rem;
    margin:1.2rem 0;
}
.sl-tech-tag {
    background:rgba(220,38,38,.1);
    border:1px solid rgba(220,38,38,.25);
    color:#f87171;
    font-size:.75rem; font-weight:600;
    padding:.3rem .7rem; border-radius:.3rem;
}
.sl-features-list {
    list-style:none; padding:0; margin:1.2rem 0 2rem;
}
.sl-features-list li {
    color:rgba(255,255,255,.72);
    font-size:.92rem;
    padding:.45rem 0;
    border-bottom:1px solid rgba(255,255,255,.04);
    display:flex; align-items:center; gap:.7rem;
}
.sl-features-list li i { color:var(--gv-primary); font-size:.8rem; min-width:1rem; }

/* ── Visual panel ───────────────────────────────── */
.sl-visual {
    background:rgba(255,255,255,.02);
    border:1px solid rgba(220,38,38,.15);
    border-radius:1.2rem;
    padding:2rem;
    position:relative;
    overflow:hidden;
}
.sl-visual::before {
    content:'';
    position:absolute; top:0; right:0;
    width:200px; height:200px;
    background:radial-gradient(circle, rgba(220,38,38,.12) 0%, transparent 70%);
    pointer-events:none;
}
.sl-visual-icon {
    font-size:4rem; color:var(--gv-primary);
    margin-bottom:1.5rem; opacity:.85;
}
.sl-visual-title {
    font-family:'Anton',sans-serif;
    font-size:1.5rem; color:#fff;
    margin-bottom:1rem;
}
.sl-visual-items {
    display:flex; flex-direction:column; gap:.8rem;
}
.sl-visual-item {
    background:rgba(255,255,255,.04);
    border:1px solid rgba(220,38,38,.1);
    border-radius:.6rem;
    padding:.8rem 1rem;
    display:flex; align-items:center; gap:.8rem;
    transition:border-color .25s;
}
.sl-visual-item:hover { border-color:rgba(220,38,38,.35); }
.sl-visual-item i { color:var(--gv-primary); min-width:1.2rem; text-align:center; }
.sl-visual-item span { color:rgba(255,255,255,.7); font-size:.88rem; }

/* ── Process steps ──────────────────────────────── */
.sl-process { background:#06060f; padding:80px 0; }
.process-steps {
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:1rem;
    margin-top:3rem;
    position:relative;
}
.process-steps::before {
    content:'';
    position:absolute;
    top:2.4rem; left:10%; right:10%;
    height:1px;
    background:linear-gradient(90deg,transparent,rgba(220,38,38,.4),transparent);
}
@media(max-width:900px){.process-steps{grid-template-columns:1fr 1fr;}.process-steps::before{display:none;}}
@media(max-width:480px){.process-steps{grid-template-columns:1fr;}}
.process-step { text-align:center; position:relative; }
.step-num {
    width:48px; height:48px;
    border-radius:50%;
    background:rgba(220,38,38,.15);
    border:2px solid rgba(220,38,38,.4);
    color:var(--gv-primary);
    font-family:'Anton',sans-serif;
    font-size:1.1rem;
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 1rem;
}
.step-title { font-family:'Anton',sans-serif; font-size:.95rem; color:#fff; margin-bottom:.4rem; }
.step-desc { color:rgba(255,255,255,.5); font-size:.78rem; line-height:1.5; }

/* ── Stats ──────────────────────────────────────── */
.sl-stats {
    background:linear-gradient(135deg,#0a0000 0%,#1a0004 100%);
    border-top:1px solid rgba(220,38,38,.12);
    border-bottom:1px solid rgba(220,38,38,.12);
    padding:60px 0;
}
.sl-stats-grid {
    display:grid; grid-template-columns:repeat(4,1fr); gap:2rem;
    text-align:center;
}
@media(max-width:700px){.sl-stats-grid{grid-template-columns:repeat(2,1fr);}}
.sl-stat-num {
    font-family:'Anton',sans-serif; font-size:2.4rem;
    color:var(--gv-primary);
}
.sl-stat-lbl { color:rgba(255,255,255,.55); font-size:.85rem; margin-top:.3rem; }

/* ── CTA ────────────────────────────────────────── */
.sl-cta {
    background:linear-gradient(135deg,#DC2626 0%,#7f1d1d 100%);
    padding:80px 0; text-align:center; position:relative; overflow:hidden;
}
.sl-cta::before {
    content:'';
    position:absolute; inset:0;
    background:radial-gradient(ellipse 60% 80% at 50% 50%,rgba(255,255,255,.06) 0%,transparent 70%);
    pointer-events:none;
}
.sl-cta h2 {
    font-family:'Anton',sans-serif;
    font-size:clamp(1.8rem,5vw,3rem); color:#fff;
    margin-bottom:1rem;
}
.sl-cta p { color:rgba(255,255,255,.82); font-size:1.05rem; max-width:500px; margin:0 auto 2rem; }
.sl-cta-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }
.btn-white {
    background:#fff; color:var(--gv-primary);
    font-weight:700; font-size:.92rem;
    padding:.75rem 2rem; border-radius:.5rem;
    text-decoration:none;
    transition:transform .25s,box-shadow .25s;
    display:inline-flex; align-items:center; gap:.5rem;
}
.btn-white:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(0,0,0,.3); }
.btn-outline-white {
    background:transparent; color:#fff;
    font-weight:700; font-size:.92rem;
    padding:.75rem 2rem; border-radius:.5rem;
    border:2px solid rgba(255,255,255,.5);
    text-decoration:none; transition:all .25s;
    display:inline-flex; align-items:center; gap:.5rem;
}
.btn-outline-white:hover { border-color:#fff; background:rgba(255,255,255,.1); }

.section-eyebrow {
    font-size:.73rem; font-weight:700;
    letter-spacing:.12em; text-transform:uppercase;
    color:var(--gv-primary); margin-bottom:.6rem;
}
.section-title {
    font-family:'Anton',sans-serif;
    font-size:clamp(1.8rem,4vw,2.8rem); color:#fff;
    margin-bottom:1rem; line-height:1.1;
}
.section-sub { color:rgba(255,255,255,.55); font-size:1rem; max-width:520px; }
.section-sub.centered { margin:0 auto; text-align:center; }
</style>
@endsection

@section('content')

{{-- ── Hero ─────────────────────────────────────────── --}}
<section class="sl-hero">
    <div class="sl-grid-bg"></div>
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;position:relative;z-index:1;">
        <div class="sl-tag"><i class="fas fa-rocket"></i> &nbsp;Innovation & Technologie</div>
        <h1>GOVIBE<br><span>Startup Lab</span></h1>
        <p class="lead">Centre d'innovation technologique d'Haïti — développement web, mobile, ERP/CRM, Intelligence Artificielle, SaaS et cybersécurité pour propulser votre organisation dans l'ère digitale.</p>
        <div class="sl-hero-nav">
            <a href="#web">Site Web</a>
            <a href="#mobile">App Mobile</a>
            <a href="#erp">ERP / CRM</a>
            <a href="#ai">Intelligence Artificielle</a>
            <a href="#saas">SaaS & Cloud</a>
            <a href="#cyber">Cybersécurité</a>
        </div>
        <div class="sl-hero-btns">
            <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                <i class="fas fa-paper-plane"></i> Démarrer un projet
            </a>
            <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE+Startup+Lab,+j'ai+un+projet+à+discuter" target="_blank" class="gv-btn-outline">
                <i class="fab fa-whatsapp"></i> Discuter avec un expert
            </a>
        </div>
    </div>
</section>

{{-- ── Stats ────────────────────────────────────────── --}}
<div class="sl-stats">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="sl-stats-grid">
            <div class="slide-up"><div class="sl-stat-num">50+</div><div class="sl-stat-lbl">Projets livrés</div></div>
            <div class="slide-up" style="animation-delay:.1s;"><div class="sl-stat-num">+1000</div><div class="sl-stat-lbl">Utilisateurs finaux</div></div>
            <div class="slide-up" style="animation-delay:.2s;"><div class="sl-stat-num">6</div><div class="sl-stat-lbl">Expertises clés</div></div>
            <div class="slide-up" style="animation-delay:.3s;"><div class="sl-stat-num">+9</div><div class="sl-stat-lbl">Pays servis</div></div>
        </div>
    </div>
</div>

{{-- ── #web — Développement Web ─────────────────────── --}}
<section id="web" class="sl-section">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="sl-section-inner">
            <div class="slide-up">
                <div class="sl-section-eyebrow"><i class="fas fa-globe"></i> Site Web</div>
                <h2>Développement Web Professionnel</h2>
                <p>Votre présence en ligne est votre première impression. GOVIBE conçoit et développe des sites web performants, modernes et sécurisés — du site vitrine à la plateforme e-commerce avancée.</p>
                <div class="sl-tech-tags">
                    <span class="sl-tech-tag">Laravel</span>
                    <span class="sl-tech-tag">React</span>
                    <span class="sl-tech-tag">Next.js</span>
                    <span class="sl-tech-tag">Vue.js</span>
                    <span class="sl-tech-tag">WordPress</span>
                    <span class="sl-tech-tag">Tailwind CSS</span>
                    <span class="sl-tech-tag">MySQL</span>
                    <span class="sl-tech-tag">PostgreSQL</span>
                </div>
                <ul class="sl-features-list">
                    <li><i class="fas fa-check"></i> Sites vitrines & landing pages</li>
                    <li><i class="fas fa-check"></i> Plateformes e-commerce (WooCommerce, Shopify, custom)</li>
                    <li><i class="fas fa-check"></i> Portails intranet & extranet</li>
                    <li><i class="fas fa-check"></i> Applications web SPA / PWA</li>
                    <li><i class="fas fa-check"></i> SEO technique et optimisation de performance</li>
                    <li><i class="fas fa-check"></i> Hébergement, maintenance et support inclus</li>
                </ul>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                    <i class="fas fa-paper-plane"></i> Demander un devis
                </a>
            </div>
            <div class="sl-visual slide-up" style="animation-delay:.15s;">
                <div class="sl-visual-icon"><i class="fas fa-code"></i></div>
                <div class="sl-visual-title">Ce qu'on livre</div>
                <div class="sl-visual-items">
                    <div class="sl-visual-item"><i class="fas fa-paint-brush"></i><span>Design UI/UX personnalisé</span></div>
                    <div class="sl-visual-item"><i class="fas fa-mobile-alt"></i><span>Responsive toutes plateformes</span></div>
                    <div class="sl-visual-item"><i class="fas fa-shield-alt"></i><span>Sécurité SSL & protection OWASP</span></div>
                    <div class="sl-visual-item"><i class="fas fa-tachometer-alt"></i><span>Performance & Core Web Vitals</span></div>
                    <div class="sl-visual-item"><i class="fas fa-chart-line"></i><span>Analytics & tableau de bord</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── #mobile — App Mobile ─────────────────────────── --}}
<section id="mobile" class="sl-section">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="sl-section-inner reverse">
            <div class="slide-up">
                <div class="sl-section-eyebrow"><i class="fas fa-mobile-alt"></i> App Mobile</div>
                <h2>Applications Mobiles iOS & Android</h2>
                <p>Des applications mobiles performantes et intuitives qui répondent aux besoins réels de vos utilisateurs — du prototype à la publication sur l'App Store et Google Play.</p>
                <div class="sl-tech-tags">
                    <span class="sl-tech-tag">React Native</span>
                    <span class="sl-tech-tag">Flutter</span>
                    <span class="sl-tech-tag">Kotlin</span>
                    <span class="sl-tech-tag">Swift</span>
                    <span class="sl-tech-tag">Firebase</span>
                    <span class="sl-tech-tag">REST API</span>
                </div>
                <ul class="sl-features-list">
                    <li><i class="fas fa-check"></i> Applications cross-platform (React Native / Flutter)</li>
                    <li><i class="fas fa-check"></i> Apps natives iOS (Swift) et Android (Kotlin)</li>
                    <li><i class="fas fa-check"></i> Intégration paiement mobile (MonCash, PayPal, Stripe)</li>
                    <li><i class="fas fa-check"></i> Notifications push & messagerie in-app</li>
                    <li><i class="fas fa-check"></i> Mode hors-ligne & synchronisation</li>
                    <li><i class="fas fa-check"></i> Publication et maintenance App Store / Google Play</li>
                </ul>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                    <i class="fas fa-paper-plane"></i> Demander un devis
                </a>
            </div>
            <div class="sl-visual slide-up" style="animation-delay:.15s;">
                <div class="sl-visual-icon"><i class="fas fa-mobile-screen-button"></i></div>
                <div class="sl-visual-title">Secteurs couverts</div>
                <div class="sl-visual-items">
                    <div class="sl-visual-item"><i class="fas fa-shopping-cart"></i><span>E-commerce & marketplace</span></div>
                    <div class="sl-visual-item"><i class="fas fa-heartbeat"></i><span>HealthTech & Telemédecine</span></div>
                    <div class="sl-visual-item"><i class="fas fa-graduation-cap"></i><span>EdTech & apprentissage</span></div>
                    <div class="sl-visual-item"><i class="fas fa-money-bill-wave"></i><span>FinTech & paiement mobile</span></div>
                    <div class="sl-visual-item"><i class="fas fa-truck"></i><span>Logistique & livraison</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── #erp — ERP / CRM ─────────────────────────────── --}}
<section id="erp" class="sl-section">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="sl-section-inner">
            <div class="slide-up">
                <div class="sl-section-eyebrow"><i class="fas fa-sitemap"></i> ERP / CRM</div>
                <h2>Systèmes ERP & CRM Sur-Mesure</h2>
                <p>Centralisez et automatisez vos opérations avec des systèmes ERP et CRM conçus spécifiquement pour les besoins des entreprises haïtiennes — de la PME à la grande entreprise.</p>
                <div class="sl-tech-tags">
                    <span class="sl-tech-tag">Odoo</span>
                    <span class="sl-tech-tag">Laravel ERP</span>
                    <span class="sl-tech-tag">GOVIBE ERP</span>
                    <span class="sl-tech-tag">MySQL</span>
                    <span class="sl-tech-tag">API REST</span>
                    <span class="sl-tech-tag">Docker</span>
                </div>
                <ul class="sl-features-list">
                    <li><i class="fas fa-check"></i> Gestion comptable et financière</li>
                    <li><i class="fas fa-check"></i> CRM — suivi clients et opportunités</li>
                    <li><i class="fas fa-check"></i> Inventaire & gestion des stocks</li>
                    <li><i class="fas fa-check"></i> Ressources humaines & paie</li>
                    <li><i class="fas fa-check"></i> Point de vente (POS) intégré</li>
                    <li><i class="fas fa-check"></i> Rapports et tableaux de bord temps réel</li>
                    <li><i class="fas fa-check"></i> Formation et support inclus</li>
                </ul>
                <a href="{{ route('erp.login') }}" class="gv-btn-prim">
                    <i class="fas fa-sign-in-alt"></i> Accéder à l'ERP GOVIBE
                </a>
            </div>
            <div class="sl-visual slide-up" style="animation-delay:.15s;">
                <div class="sl-visual-icon"><i class="fas fa-network-wired"></i></div>
                <div class="sl-visual-title">Modules disponibles</div>
                <div class="sl-visual-items">
                    <div class="sl-visual-item"><i class="fas fa-calculator"></i><span>Finance & Comptabilité</span></div>
                    <div class="sl-visual-item"><i class="fas fa-users-cog"></i><span>RH & Gestion du Personnel</span></div>
                    <div class="sl-visual-item"><i class="fas fa-boxes"></i><span>Inventaire & Logistique</span></div>
                    <div class="sl-visual-item"><i class="fas fa-cash-register"></i><span>Point de Vente (POS)</span></div>
                    <div class="sl-visual-item"><i class="fas fa-chart-bar"></i><span>Analytics & Reporting</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── #ai — Intelligence Artificielle ─────────────── --}}
<section id="ai" class="sl-section">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="sl-section-inner reverse">
            <div class="slide-up">
                <div class="sl-section-eyebrow"><i class="fas fa-brain"></i> Intelligence Artificielle</div>
                <h2>Solutions IA & Machine Learning</h2>
                <p>Exploitez la puissance de l'Intelligence Artificielle pour automatiser vos processus, analyser vos données et offrir des expériences personnalisées à vos clients.</p>
                <div class="sl-tech-tags">
                    <span class="sl-tech-tag">Python</span>
                    <span class="sl-tech-tag">TensorFlow</span>
                    <span class="sl-tech-tag">OpenAI API</span>
                    <span class="sl-tech-tag">Claude API</span>
                    <span class="sl-tech-tag">NLP</span>
                    <span class="sl-tech-tag">Computer Vision</span>
                </div>
                <ul class="sl-features-list">
                    <li><i class="fas fa-check"></i> Chatbots & assistants virtuels (WhatsApp, Web)</li>
                    <li><i class="fas fa-check"></i> Analyse prédictive et business intelligence</li>
                    <li><i class="fas fa-check"></i> Automatisation des processus (RPA)</li>
                    <li><i class="fas fa-check"></i> Reconnaissance d'image et de documents</li>
                    <li><i class="fas fa-check"></i> Traitement du langage naturel (NLP) en créole haïtien</li>
                    <li><i class="fas fa-check"></i> Recommandations personnalisées</li>
                </ul>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                    <i class="fas fa-paper-plane"></i> Explorer l'IA avec GOVIBE
                </a>
            </div>
            <div class="sl-visual slide-up" style="animation-delay:.15s;">
                <div class="sl-visual-icon"><i class="fas fa-robot"></i></div>
                <div class="sl-visual-title">Applications concrètes</div>
                <div class="sl-visual-items">
                    <div class="sl-visual-item"><i class="fas fa-comments"></i><span>Chatbot service client 24/7</span></div>
                    <div class="sl-visual-item"><i class="fas fa-search-dollar"></i><span>Détection de fraude</span></div>
                    <div class="sl-visual-item"><i class="fas fa-id-card"></i><span>Vérification d'identité OCR</span></div>
                    <div class="sl-visual-item"><i class="fas fa-chart-area"></i><span>Prévision des ventes</span></div>
                    <div class="sl-visual-item"><i class="fas fa-language"></i><span>Traduction créole / français</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── #saas — SaaS & Cloud ─────────────────────────── --}}
<section id="saas" class="sl-section">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="sl-section-inner">
            <div class="slide-up">
                <div class="sl-section-eyebrow"><i class="fas fa-cloud"></i> SaaS & Cloud</div>
                <h2>Plateformes SaaS & Architecture Cloud</h2>
                <p>Concevez et déployez des solutions logicielles en mode SaaS — évolutives, haute disponibilité et monétisables. GOVIBE vous accompagne de la conception au lancement commercial.</p>
                <div class="sl-tech-tags">
                    <span class="sl-tech-tag">AWS</span>
                    <span class="sl-tech-tag">DigitalOcean</span>
                    <span class="sl-tech-tag">Nginx</span>
                    <span class="sl-tech-tag">Docker</span>
                    <span class="sl-tech-tag">Kubernetes</span>
                    <span class="sl-tech-tag">CI/CD</span>
                    <span class="sl-tech-tag">Redis</span>
                    <span class="sl-tech-tag">Stripe</span>
                </div>
                <ul class="sl-features-list">
                    <li><i class="fas fa-check"></i> Architecture multi-tenant SaaS</li>
                    <li><i class="fas fa-check"></i> Déploiement cloud (AWS, DigitalOcean)</li>
                    <li><i class="fas fa-check"></i> API REST & GraphQL</li>
                    <li><i class="fas fa-check"></i> Système de facturation & abonnements</li>
                    <li><i class="fas fa-check"></i> Monitoring & alertes en temps réel</li>
                    <li><i class="fas fa-check"></i> Scalabilité automatique</li>
                </ul>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                    <i class="fas fa-paper-plane"></i> Lancer mon SaaS
                </a>
            </div>
            <div class="sl-visual slide-up" style="animation-delay:.15s;">
                <div class="sl-visual-icon"><i class="fas fa-server"></i></div>
                <div class="sl-visual-title">Infrastructure GOVIBE</div>
                <div class="sl-visual-items">
                    <div class="sl-visual-item"><i class="fas fa-lock"></i><span>Sécurité SSL & certificats</span></div>
                    <div class="sl-visual-item"><i class="fas fa-database"></i><span>Backups automatiques quotidiens</span></div>
                    <div class="sl-visual-item"><i class="fas fa-tachometer-alt"></i><span>Uptime garanti 99.9%</span></div>
                    <div class="sl-visual-item"><i class="fas fa-globe"></i><span>CDN mondial</span></div>
                    <div class="sl-visual-item"><i class="fas fa-sync-alt"></i><span>Déploiement continu (CI/CD)</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── #cyber — Cybersécurité ───────────────────────── --}}
<section id="cyber" class="sl-section">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="sl-section-inner reverse">
            <div class="slide-up">
                <div class="sl-section-eyebrow"><i class="fas fa-shield-halved"></i> Cybersécurité</div>
                <h2>Cybersécurité & Protection des Données</h2>
                <p>Protégez votre entreprise, vos clients et vos données. GOVIBE offre des audits de sécurité, des tests de pénétration et la mise en place de politiques de sécurité robustes.</p>
                <div class="sl-tech-tags">
                    <span class="sl-tech-tag">Pentest</span>
                    <span class="sl-tech-tag">OWASP</span>
                    <span class="sl-tech-tag">SSL/TLS</span>
                    <span class="sl-tech-tag">Firewall</span>
                    <span class="sl-tech-tag">WAF</span>
                    <span class="sl-tech-tag">2FA</span>
                    <span class="sl-tech-tag">GDPR</span>
                </div>
                <ul class="sl-features-list">
                    <li><i class="fas fa-check"></i> Audit de sécurité et analyse des vulnérabilités</li>
                    <li><i class="fas fa-check"></i> Tests de pénétration (ethical hacking)</li>
                    <li><i class="fas fa-check"></i> Configuration pare-feu & WAF</li>
                    <li><i class="fas fa-check"></i> Authentification forte (2FA / MFA)</li>
                    <li><i class="fas fa-check"></i> Formation cybersécurité pour équipes</li>
                    <li><i class="fas fa-check"></i> Conformité GDPR & protection des données</li>
                </ul>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                    <i class="fas fa-paper-plane"></i> Sécuriser mon entreprise
                </a>
            </div>
            <div class="sl-visual slide-up" style="animation-delay:.15s;">
                <div class="sl-visual-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="sl-visual-title">Notre approche sécurité</div>
                <div class="sl-visual-items">
                    <div class="sl-visual-item"><i class="fas fa-search"></i><span>Analyse des vulnérabilités</span></div>
                    <div class="sl-visual-item"><i class="fas fa-bug"></i><span>Tests de pénétration (Pentest)</span></div>
                    <div class="sl-visual-item"><i class="fas fa-fire-extinguisher"></i><span>Réponse aux incidents</span></div>
                    <div class="sl-visual-item"><i class="fas fa-file-shield"></i><span>Politique de sécurité</span></div>
                    <div class="sl-visual-item"><i class="fas fa-chalkboard-teacher"></i><span>Sensibilisation des équipes</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Processus ────────────────────────────────────── --}}
<section class="sl-process">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">Méthodologie</div>
            <h2 class="section-title">Notre Processus de Développement</h2>
            <p class="section-sub centered">De l'idée au lancement — une approche agile et transparente.</p>
        </div>
        <div class="process-steps">
            <div class="process-step slide-up">
                <div class="step-num">01</div>
                <div class="step-title">Découverte</div>
                <div class="step-desc">Analyse des besoins, définition des objectifs et faisabilité technique</div>
            </div>
            <div class="process-step slide-up" style="animation-delay:.1s;">
                <div class="step-num">02</div>
                <div class="step-title">Design</div>
                <div class="step-desc">Wireframes, prototypes UI/UX et validation par le client</div>
            </div>
            <div class="process-step slide-up" style="animation-delay:.2s;">
                <div class="step-num">03</div>
                <div class="step-title">Développement</div>
                <div class="step-desc">Sprints agiles avec démonstrations régulières et itérations</div>
            </div>
            <div class="process-step slide-up" style="animation-delay:.3s;">
                <div class="step-num">04</div>
                <div class="step-title">Tests & QA</div>
                <div class="step-desc">Tests automatisés, QA manuel et audit de sécurité</div>
            </div>
            <div class="process-step slide-up" style="animation-delay:.4s;">
                <div class="step-num">05</div>
                <div class="step-title">Lancement</div>
                <div class="step-desc">Déploiement, formation et support post-lancement</div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA ──────────────────────────────────────────── --}}
<section class="sl-cta">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;position:relative;z-index:1;">
        <h2 class="slide-up">Votre projet commence ici.</h2>
        <p class="slide-up">Que vous ayez une idée ou un besoin concret — nos experts technologiques sont prêts à vous accompagner de A à Z.</p>
        <div class="sl-cta-btns slide-up">
            <a href="{{ route('inscription.create') }}" class="btn-white">
                <i class="fas fa-rocket"></i> Démarrer mon projet
            </a>
            <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE,+je+veux+démarrer+un+projet+tech" target="_blank" class="btn-outline-white">
                <i class="fab fa-whatsapp"></i> Parler à un expert
            </a>
        </div>
    </div>
</section>

@endsection

@section('scripts')
<script>
// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e){
        const target = document.querySelector(this.getAttribute('href'));
        if(target){
            e.preventDefault();
            target.scrollIntoView({ behavior:'smooth', block:'start' });
        }
    });
});
</script>
@endsection

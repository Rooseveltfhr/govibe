@extends('layouts.public')

@section('title', 'Nos Services — GOVIBE Innovation Hub')
@section('description', 'GOVIBE accompagne les entreprises, ONG, institutions et entrepreneurs dans leur transformation numérique : Media & Digital, Développement Logiciel & IA, Academy, Coworking et Accompagnement Startups.')

@section('head')
<style>
:root { --gv-primary:#DC2626; --gv-primary-dark:#991b1b; }

/* ── Hero ───────────────────────────────────────────── */
.sv-hero {
    background: linear-gradient(135deg,#0a0000 0%,#1a0004 45%,#06060f 100%);
    min-height: 70vh;
    display: flex; align-items: center;
    position: relative; overflow: hidden;
    padding: 130px 0 90px;
}
.sv-hero::before {
    content:''; position:absolute; inset:0;
    background:
        radial-gradient(ellipse 65% 55% at 85% 20%, rgba(220,38,38,.22) 0%, transparent 65%),
        radial-gradient(ellipse 50% 45% at 5% 80%, rgba(220,38,38,.12) 0%, transparent 60%);
    pointer-events:none;
}
.sv-grid-bg {
    position:absolute; inset:0;
    background-image:
        linear-gradient(rgba(220,38,38,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(220,38,38,.04) 1px, transparent 1px);
    background-size:55px 55px; pointer-events:none;
}
.sv-hero-inner { max-width:800px; margin:0 auto; text-align:center; position:relative; z-index:1; padding:0 1.5rem; }
.sv-hero-tag {
    display:inline-block;
    background:rgba(220,38,38,.15); border:1px solid rgba(220,38,38,.35);
    color:#f87171; font-size:.78rem; font-weight:700;
    letter-spacing:.1em; text-transform:uppercase;
    padding:.35rem 1rem; border-radius:2rem; margin-bottom:1.5rem;
}
.sv-hero h1 {
    font-family:'Anton',sans-serif;
    font-size:clamp(2.6rem,8vw,5rem);
    color:#fff; line-height:1.0; margin-bottom:1.2rem;
}
.sv-hero h1 span { color:var(--gv-primary); }
.sv-hero p.lead {
    color:rgba(255,255,255,.7); font-size:1.1rem;
    line-height:1.8; max-width:660px; margin:0 auto 2.4rem;
}

/* Animated dots background */
.sv-dots-canvas { position:absolute; inset:0; pointer-events:none; }

/* Nav anchors quick-jump */
.sv-anchors {
    display:flex; flex-wrap:wrap; justify-content:center; gap:.6rem;
    margin-top:2rem;
}
.sv-anchors a {
    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12);
    color:rgba(255,255,255,.72); font-size:.82rem; font-weight:600;
    padding:.4rem 1rem; border-radius:.4rem; text-decoration:none;
    transition:all .25s;
}
.sv-anchors a:hover {
    background:rgba(220,38,38,.18); border-color:rgba(220,38,38,.45); color:#fff;
}

/* ── Section base ──────────────────────────────────── */
.sv-section { padding:90px 0; }
.sv-section:nth-child(odd) { background:#06060f; }
.sv-section:nth-child(even) { background:#0a0000; }
.sv-wrap { max-width:1200px; margin:0 auto; padding:0 1.5rem; }

.sv-eyebrow {
    font-size:.73rem; font-weight:700;
    letter-spacing:.13em; text-transform:uppercase;
    color:var(--gv-primary); margin-bottom:.5rem;
    display:flex; align-items:center; gap:.5rem;
}
.sv-eyebrow::before {
    content:''; display:inline-block;
    width:24px; height:2px; background:var(--gv-primary);
}
.sv-section-title {
    font-family:'Anton',sans-serif;
    font-size:clamp(1.9rem,4vw,2.9rem); color:#fff;
    line-height:1.08; margin-bottom:.8rem;
}
.sv-section-sub {
    color:rgba(255,255,255,.55); font-size:.98rem;
    line-height:1.7; max-width:580px;
}
.sv-head-row {
    display:flex; align-items:flex-end; justify-content:space-between;
    gap:2rem; flex-wrap:wrap; margin-bottom:3rem;
}

/* ── Service Cards ─────────────────────────────────── */
.sv-cards {
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));
    gap:1.4rem;
}
.sv-card {
    background:rgba(255,255,255,.03);
    border:1px solid rgba(220,38,38,.1);
    border-radius:1.1rem;
    padding:1.6rem 1.4rem;
    transition:all .3s;
    display:flex; flex-direction:column; gap:.6rem;
    position:relative; overflow:hidden;
}
.sv-card::before {
    content:''; position:absolute; top:0; left:0;
    width:3px; height:0; background:var(--gv-primary);
    transition:height .35s ease;
}
.sv-card:hover { border-color:rgba(220,38,38,.4); transform:translateY(-5px); box-shadow:0 16px 40px rgba(220,38,38,.12); }
.sv-card:hover::before { height:100%; }
.sv-card-icon {
    width:44px; height:44px; border-radius:.7rem;
    background:rgba(220,38,38,.12); border:1px solid rgba(220,38,38,.2);
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; color:var(--gv-primary); flex-shrink:0;
}
.sv-card-title {
    font-family:'Anton',sans-serif; font-size:1rem; color:#fff;
    margin:0; line-height:1.2;
}
.sv-card-desc {
    color:rgba(255,255,255,.52); font-size:.83rem;
    line-height:1.55; margin:0; flex:1;
}
.sv-card-link {
    color:var(--gv-primary); font-size:.8rem; font-weight:700;
    display:inline-flex; align-items:center; gap:.35rem;
    margin-top:.4rem; text-decoration:none;
    transition:gap .2s;
}
.sv-card-link:hover { gap:.6rem; }

/* ── Category header badge ─────────────────────────── */
.sv-cat-badge {
    display:inline-flex; align-items:center; gap:.6rem;
    background:rgba(220,38,38,.1); border:1px solid rgba(220,38,38,.25);
    border-radius:2rem; padding:.4rem 1.1rem;
    font-size:.8rem; font-weight:700; color:var(--gv-primary);
    letter-spacing:.06em; text-transform:uppercase;
    margin-bottom:1rem;
}

/* ── Formation list grid ────────────────────────────── */
.sv-formation-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1rem;
    margin-bottom:2.5rem;
}
.sv-formation-item {
    background:rgba(255,255,255,.03); border:1px solid rgba(220,38,38,.1);
    border-radius:.8rem; padding:1rem 1.2rem;
    display:flex; align-items:center; gap:.8rem;
    transition:all .25s;
}
.sv-formation-item:hover { border-color:rgba(220,38,38,.35); background:rgba(220,38,38,.05); }
.sv-formation-item i { color:var(--gv-primary); font-size:1rem; min-width:1.2rem; text-align:center; }
.sv-formation-item span { color:rgba(255,255,255,.78); font-size:.88rem; font-weight:500; }

/* ── Coworking stats ────────────────────────────────── */
.sv-cw-stats {
    display:grid; grid-template-columns:repeat(4,1fr); gap:1.2rem;
    margin-top:2.5rem;
}
@media(max-width:700px){.sv-cw-stats{grid-template-columns:repeat(2,1fr);}}
.sv-cw-stat {
    background:rgba(255,255,255,.03); border:1px solid rgba(220,38,38,.1);
    border-radius:1rem; padding:1.2rem; text-align:center;
    transition:border-color .25s;
}
.sv-cw-stat:hover { border-color:rgba(220,38,38,.35); }
.sv-cw-stat .num { font-family:'Anton',sans-serif; font-size:1.9rem; color:var(--gv-primary); }
.sv-cw-stat .lbl { color:rgba(255,255,255,.5); font-size:.78rem; margin-top:.3rem; }

/* ── Solutions SaaS cards ───────────────────────────── */
.sv-solutions-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.4rem;
}
.sv-solution-card {
    background:rgba(255,255,255,.03); border:1px solid rgba(220,38,38,.12);
    border-radius:1.1rem; padding:1.8rem;
    display:flex; gap:1.2rem; align-items:flex-start;
    transition:all .3s;
}
.sv-solution-card:hover { border-color:rgba(220,38,38,.4); transform:translateY(-4px); box-shadow:0 14px 36px rgba(220,38,38,.1); }
.sv-sol-icon {
    width:50px; height:50px; flex-shrink:0;
    border-radius:.8rem; background:rgba(220,38,38,.12); border:1px solid rgba(220,38,38,.25);
    display:flex; align-items:center; justify-content:center;
    font-size:1.3rem; color:var(--gv-primary);
}
.sv-sol-name { font-family:'Anton',sans-serif; font-size:1.05rem; color:#fff; margin-bottom:.4rem; }
.sv-sol-desc { color:rgba(255,255,255,.52); font-size:.83rem; line-height:1.55; }

/* ── Pourquoi GOVIBE ───────────────────────────────── */
.sv-why { background:linear-gradient(135deg,#0a0000 0%,#150002 100%); padding:90px 0; }
.sv-why-grid {
    display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; margin-top:3rem;
}
@media(max-width:900px){.sv-why-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:500px){.sv-why-grid{grid-template-columns:1fr;}}
.sv-why-card {
    background:rgba(255,255,255,.03); border:1px solid rgba(220,38,38,.1);
    border-radius:1.2rem; padding:2rem; text-align:center;
    transition:all .3s;
}
.sv-why-card:hover { border-color:rgba(220,38,38,.4); transform:translateY(-5px); }
.sv-why-icon {
    width:60px; height:60px; border-radius:50%;
    background:rgba(220,38,38,.12); border:2px solid rgba(220,38,38,.25);
    display:flex; align-items:center; justify-content:center;
    font-size:1.5rem; color:var(--gv-primary);
    margin:0 auto 1.2rem;
}
.sv-why-title { font-family:'Anton',sans-serif; font-size:1.05rem; color:#fff; margin-bottom:.6rem; }
.sv-why-desc { color:rgba(255,255,255,.52); font-size:.86rem; line-height:1.6; }

/* ── CTA ────────────────────────────────────────────── */
.sv-cta {
    background:linear-gradient(135deg,#DC2626 0%,#7f1d1d 100%);
    padding:100px 0; text-align:center; position:relative; overflow:hidden;
}
.sv-cta::before {
    content:''; position:absolute; inset:0;
    background:
        radial-gradient(ellipse 70% 80% at 50% 50%,rgba(255,255,255,.07) 0%,transparent 70%),
        linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),
        linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);
    background-size:auto,50px 50px,50px 50px;
    pointer-events:none;
}
.sv-cta h2 {
    font-family:'Anton',sans-serif;
    font-size:clamp(2rem,5.5vw,3.5rem); color:#fff; margin-bottom:1rem;
}
.sv-cta p { color:rgba(255,255,255,.85); font-size:1.08rem; max-width:520px; margin:0 auto 2.5rem; line-height:1.75; }
.sv-cta-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }
.btn-white {
    background:#fff; color:var(--gv-primary);
    font-weight:700; font-size:.95rem;
    padding:.85rem 2.3rem; border-radius:50px;
    text-decoration:none; transition:all .3s;
    display:inline-flex; align-items:center; gap:.5rem;
    box-shadow:0 4px 20px rgba(0,0,0,.2);
}
.btn-white:hover { transform:translateY(-3px); box-shadow:0 14px 32px rgba(0,0,0,.35); }
.btn-outline-white {
    background:transparent; color:#fff;
    font-weight:700; font-size:.95rem;
    padding:.85rem 2.3rem; border-radius:50px;
    border:2px solid rgba(255,255,255,.55);
    text-decoration:none; transition:all .3s;
    display:inline-flex; align-items:center; gap:.5rem;
}
.btn-outline-white:hover { border-color:#fff; background:rgba(255,255,255,.12); }

/* ── Clients types strip ──────────────────────────── */
.sv-clients { background:#050a14; padding:60px 0; border-top:1px solid rgba(220,38,38,.1); }
.sv-clients-scroll {
    display:flex; gap:1rem; flex-wrap:wrap; justify-content:center;
    margin-top:1.5rem;
}
.sv-client-tag {
    background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
    border-radius:2rem; padding:.45rem 1.1rem;
    color:rgba(255,255,255,.6); font-size:.82rem; font-weight:600;
    display:flex; align-items:center; gap:.45rem;
    transition:all .25s;
}
.sv-client-tag:hover { border-color:rgba(220,38,38,.35); color:#fff; }
.sv-client-tag i { color:var(--gv-primary); }

/* ── Responsive tweaks ──────────────────────────────── */
@media(max-width:600px){
    .sv-cards { grid-template-columns:1fr; }
    .sv-formation-grid { grid-template-columns:1fr; }
    .sv-solutions-grid { grid-template-columns:1fr; }
}
</style>
@endsection

@section('content')

{{-- ── HERO ────────────────────────────────────────── --}}
<section class="sv-hero">
    <canvas class="sv-dots-canvas" id="sv-canvas"></canvas>
    <div class="sv-grid-bg"></div>
    <div class="sv-hero-inner">
        <div class="sv-hero-tag slide-up"><i class="fas fa-layer-group"></i> &nbsp;GOVIBE Innovation Hub</div>
        <h1 class="slide-up"><span>Nos</span> Services</h1>
        <p class="lead slide-up">Nous accompagnons les entreprises, les organisations, les institutions publiques, les ONG et les entrepreneurs dans leur transformation numérique grâce à des solutions innovantes, des formations et des services professionnels.</p>
        <div class="sv-anchors slide-up">
            <a href="#media"><i class="fas fa-bullhorn"></i> Media & Digital</a>
            <a href="#dev"><i class="fas fa-code"></i> Développement & IA</a>
            <a href="#academy-cat"><i class="fas fa-graduation-cap"></i> Academy</a>
            <a href="#coworking-cat"><i class="fas fa-building"></i> Coworking</a>
            <a href="#startup"><i class="fas fa-rocket"></i> Startups & PME</a>
            <a href="#solutions"><i class="fas fa-cubes"></i> Solutions SaaS</a>
        </div>
    </div>
</section>

{{-- ── 1. MEDIA & DIGITAL ──────────────────────────── --}}
<section class="sv-section" id="media" style="scroll-margin-top:80px;">
    <div class="sv-wrap">
        <div class="sv-head-row">
            <div class="slide-up">
                <div class="sv-eyebrow"><i class="fas fa-bullhorn"></i> Catégorie 1</div>
                <h2 class="sv-section-title">Media & Digital</h2>
                <p class="sv-section-sub">Nous aidons les organisations à développer leur image de marque, leur visibilité et leur présence numérique à travers des stratégies créatives et des campagnes data-driven.</p>
            </div>
            <a href="{{ route('inscription.create') }}" class="gv-btn-prim slide-up" style="white-space:nowrap;">
                <i class="fas fa-paper-plane"></i> Demander un devis
            </a>
        </div>
        <div class="sv-cards">
            @foreach([
                ['fas fa-share-alt','Gestion des Réseaux Sociaux','Création, planification et animation de vos comptes Facebook, Instagram, LinkedIn, TikTok et X.'],
                ['fas fa-users','Community Management','Interaction avec votre communauté, modération, croissance organique et fidélisation.'],
                ['fab fa-facebook','Publicité Facebook / Instagram','Campagnes Meta Ads ciblées — génération de leads, ventes, notoriété, retargeting.'],
                ['fab fa-google','Google Ads & SEA','Référencement payant Google Search, Display, YouTube Ads pour maximiser votre ROI.'],
                ['fab fa-tiktok','TikTok Ads','Publicités vidéo TikTok pour toucher les nouvelles générations et amplifier votre visibilité.'],
                ['fas fa-palette','Création Graphique & Branding','Logo, identité visuelle, charte graphique, supports print et digital — un branding cohérent et mémorable.'],
                ['fas fa-photo-film','Production Vidéo & Motion Design','Vidéos institutionnelles, reels, tutoriels, motion design et animations 2D pour vos campagnes.'],
                ['fas fa-camera','Photographie Professionnelle','Shooting produit, événementiel, portrait corporate et couverture d\'événements.'],
                ['fas fa-search','SEO & Référencement Naturel','Audit, optimisation technique, contenu et netlinking pour dominer Google en Haïti et à l\'international.'],
                ['fas fa-store','Google Business Profile','Optimisation et gestion de votre fiche Google My Business pour attirer plus de clients locaux.'],
                ['fas fa-envelope-open-text','Email Marketing','Campagnes email, newsletters, automatisations et segmentation pour convertir vos prospects.'],
                ['fas fa-handshake','Relations Publiques','Stratégie RP, communiqués, relations médias et gestion de la réputation en ligne.'],
                ['fas fa-chart-line','Marketing Digital 360°','Stratégie globale intégrant tous les canaux digitaux pour une croissance mesurable.'],
                ['fas fa-vote-yea','Marketing Politique','Campagnes digitales, communication de crise et gestion de l\'image pour personnalités publiques.'],
                ['fas fa-ad','Création de Campagnes','Conception créative de campagnes publicitaires multicanal de A à Z.'],
                ['fas fa-film','Montage Vidéo','Post-production professionnelle, sous-titrage, colorimétrie et diffusion multi-plateformes.'],
            ] as [$icon,$title,$desc])
            <div class="sv-card slide-up">
                <div class="sv-card-icon"><i class="{{ $icon }}"></i></div>
                <h3 class="sv-card-title">{{ $title }}</h3>
                <p class="sv-card-desc">{{ $desc }}</p>
                <a href="{{ route('inscription.create') }}" class="sv-card-link">
                    Demander un devis <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── 2. DÉVELOPPEMENT LOGICIEL & IA ─────────────── --}}
<section class="sv-section" id="dev" style="scroll-margin-top:80px;">
    <div class="sv-wrap">
        <div class="sv-head-row">
            <div class="slide-up">
                <div class="sv-eyebrow"><i class="fas fa-code"></i> Catégorie 2</div>
                <h2 class="sv-section-title">Développement Logiciel & Intelligence Artificielle</h2>
                <p class="sv-section-sub">Nous concevons des solutions numériques sur-mesure adaptées aux besoins des entreprises haïtiennes et internationales — de la startup à la grande institution.</p>
            </div>
            <a href="{{ route('startup-lab') }}" class="gv-btn-prim slide-up" style="white-space:nowrap;">
                <i class="fas fa-flask"></i> Voir Startup Lab
            </a>
        </div>
        <div class="sv-cards">
            @foreach([
                ['fas fa-globe','Sites Web Professionnels','Sites vitrines, e-commerce, portails intranet et applications web avec design premium et performances optimales.'],
                ['fas fa-mobile-alt','Applications Mobiles','Apps iOS & Android natives ou cross-platform (React Native / Flutter) publiées sur App Store et Google Play.'],
                ['fas fa-cogs','Logiciels Sur-Mesure','Développement de solutions logicielles adaptées à vos processus métier spécifiques.'],
                ['fas fa-cloud','Plateformes SaaS','Architectures multi-tenant évolutives avec facturation, API et déploiement cloud.'],
                ['fas fa-robot','Intelligence Artificielle','Modèles ML, analyse prédictive, traitement de données et solutions IA pour l\'entreprise.'],
                ['fas fa-comments','Chatbots IA','Assistants virtuels intelligents pour WhatsApp, site web et applications — service client 24/7.'],
                ['fas fa-user-robot','Agents IA','Agents autonomes pour automatiser des workflows complexes et décisions métier.'],
                ['fas fa-magic','Automatisation','RPA, scripts, intégrations Zapier/Make et orchestration de processus pour gagner du temps.'],
                ['fas fa-plug','API & Intégrations','Développement d\'API REST/GraphQL et intégrations avec vos outils existants (ERP, CRM, paiement).'],
                ['fas fa-server','Hébergement Web & Cloud','Hébergement VPS, cloud AWS/DigitalOcean, CDN, SSL et infrastructure scalable.'],
                ['fas fa-tools','Maintenance Informatique','Support technique, mises à jour, monitoring et maintenance préventive de vos systèmes.'],
                ['fas fa-shield-alt','Cybersécurité','Audit, pentest, pare-feu, 2FA, conformité GDPR et formation des équipes à la sécurité digitale.'],
                ['fas fa-sitemap','ERP / CRM','Systèmes intégrés de gestion d\'entreprise : finance, RH, inventaire, POS et CRM clients.'],
                ['fas fa-hospital','Solutions Sectorielles','Logiciels verticaux pour hôpitaux (Hospify), éducation, logistique, finance et commerce.'],
            ] as [$icon,$title,$desc])
            <div class="sv-card slide-up">
                <div class="sv-card-icon"><i class="{{ $icon }}"></i></div>
                <h3 class="sv-card-title">{{ $title }}</h3>
                <p class="sv-card-desc">{{ $desc }}</p>
                <a href="{{ route('startup-lab') }}" class="sv-card-link">
                    En savoir plus <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── 3. ACADEMY ──────────────────────────────────── --}}
<section class="sv-section" id="academy-cat" style="scroll-margin-top:80px;">
    <div class="sv-wrap">
        <div class="sv-head-row">
            <div class="slide-up">
                <div class="sv-eyebrow"><i class="fas fa-graduation-cap"></i> Catégorie 3</div>
                <h2 class="sv-section-title">GOVIBE Academy</h2>
                <p class="sv-section-sub">Nous développons les compétences des jeunes, des professionnels et des entreprises grâce à des formations pratiques, des bootcamps et des certifications reconnues.</p>
            </div>
            <a href="{{ route('academy') }}" class="gv-btn-prim slide-up" style="white-space:nowrap;">
                <i class="fas fa-graduation-cap"></i> Voir toutes les formations
            </a>
        </div>

        {{-- Formation types --}}
        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem;">
            @foreach(['FORMATIONS','BOOTCAMPS','CERTIFICATIONS','ATELIERS PRATIQUES'] as $type)
            <span style="background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.3);color:#f87171;font-size:.75rem;font-weight:700;padding:.35rem .9rem;border-radius:2rem;letter-spacing:.06em;">{{ $type }}</span>
            @endforeach
        </div>

        <div class="sv-formation-grid">
            @foreach([
                ['fas fa-brain','Intelligence Artificielle'],
                ['fas fa-bullhorn','Marketing Digital'],
                ['fas fa-code','Développement Web'],
                ['fas fa-mobile-alt','Développement Mobile'],
                ['fas fa-paint-brush','Canva & Design Graphique'],
                ['fas fa-shield-alt','Cybersécurité'],
                ['fas fa-flag-usa','Création de LLC aux USA'],
                ['fas fa-lightbulb','Entrepreneuriat'],
                ['fas fa-shopping-cart','Commerce Électronique'],
                ['fas fa-store','Google Business Profile'],
                ['fas fa-chart-bar','Facebook & Google Ads'],
                ['fas fa-terminal','Programmation'],
                ['fas fa-project-diagram','Gestion de Projets'],
                ['fas fa-sync-alt','Transformation Digitale'],
                ['fas fa-globe-americas','Commerce International'],
                ['fas fa-certificate','Certifications Professionnelles'],
                ['fas fa-heartbeat','Entrepreneuriat Social'],
                ['fas fa-calculator','Gestion Financière'],
            ] as [$icon,$label])
            <div class="sv-formation-item slide-up">
                <i class="{{ $icon }}"></i>
                <span>{{ $label }}</span>
            </div>
            @endforeach
        </div>

        <div style="display:flex;gap:1rem;flex-wrap:wrap;">
            <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                <i class="fas fa-pen-to-square"></i> S'inscrire à une formation
            </a>
            <a href="{{ route('academy') }}" class="gv-btn-outline">
                <i class="fas fa-book"></i> Programme complet
            </a>
        </div>
    </div>
</section>

{{-- ── 4. COWORKING SPACE ──────────────────────────── --}}
<section class="sv-section" id="coworking-cat" style="scroll-margin-top:80px;">
    <div class="sv-wrap">
        <div class="sv-head-row">
            <div class="slide-up">
                <div class="sv-eyebrow"><i class="fas fa-building"></i> Catégorie 4</div>
                <h2 class="sv-section-title">Coworking Space</h2>
                <p class="sv-section-sub">Un espace de travail moderne conçu pour les ONG, associations, PME, TPME et professionnels. Infrastructures premium, communauté dynamique et accompagnement inclus.</p>
            </div>
            <a href="{{ route('coworking') }}" class="gv-btn-prim slide-up" style="white-space:nowrap;">
                <i class="fas fa-building"></i> Découvrir l'espace
            </a>
        </div>
        <div class="sv-cards">
            @foreach([
                ['fas fa-door-closed','Bureaux Privés','Espaces fermés pour équipes de 2 à 10 personnes — confidentialité totale et ambiance bureau d\'entreprise.'],
                ['fas fa-users','Espaces Partagés','Hot desk pour ONG, associations, freelancers et professionnels — flexibles et accessibles.'],
                ['fas fa-chalkboard-teacher','Salle de Conférence','Équipée projecteur, tableau blanc, vidéoconférence — pour présentations et réunions importantes.'],
                ['fas fa-chalkboard','Salle de Formation','Espace dédié aux ateliers, séminaires, workshops et sessions de formation — jusqu\'à 40 participants.'],
                ['fas fa-calendar-star','Événements & Séminaires','Organisation complète d\'événements professionnels : conférences, lancements, galas, hackathons.'],
                ['fas fa-wifi','Internet Haut Débit','WiFi fibré sécurisé, onduleur et groupe électrogène — zéro interruption pour vos activités.'],
                ['fas fa-mug-hot','Café & Networking','Espace lounge pour pauses, rencontres informelles et opportunités business au quotidien.'],
                ['fas fa-envelope','Domiciliation d\'Entreprise','Adresse professionnelle GOVIBE pour vos courriers, factures et démarches administratives.'],
                ['fas fa-shield-alt','Sécurité & Accès 24/7','Surveillance, contrôle d\'accès sécurisé et parking — disponible à toute heure.'],
            ] as [$icon,$title,$desc])
            <div class="sv-card slide-up">
                <div class="sv-card-icon"><i class="{{ $icon }}"></i></div>
                <h3 class="sv-card-title">{{ $title }}</h3>
                <p class="sv-card-desc">{{ $desc }}</p>
                <a href="{{ route('coworking') }}" class="sv-card-link">
                    Voir les plans <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            @endforeach
        </div>

        {{-- Stats --}}
        <div class="sv-cw-stats">
            <div class="sv-cw-stat slide-up">
                <div class="num">120+</div>
                <div class="lbl">Membres actifs</div>
            </div>
            <div class="sv-cw-stat slide-up" style="animation-delay:.1s;">
                <div class="num">500+</div>
                <div class="lbl">Événements organisés</div>
            </div>
            <div class="sv-cw-stat slide-up" style="animation-delay:.2s;">
                <div class="num">24/7</div>
                <div class="lbl">Accès disponible</div>
            </div>
            <div class="sv-cw-stat slide-up" style="animation-delay:.3s;">
                <div class="num">2020</div>
                <div class="lbl">Fondé en</div>
            </div>
        </div>
    </div>
</section>

{{-- ── 5. ACCOMPAGNEMENT STARTUPS & PME ───────────── --}}
<section class="sv-section" id="startup" style="scroll-margin-top:80px;">
    <div class="sv-wrap">
        <div class="sv-head-row">
            <div class="slide-up">
                <div class="sv-eyebrow"><i class="fas fa-rocket"></i> Catégorie 5</div>
                <h2 class="sv-section-title">Accompagnement Startups & PME</h2>
                <p class="sv-section-sub">Nous accompagnons les entrepreneurs et les dirigeants de PME dans le développement, la structuration et la croissance de leurs projets — de l\'idée au marché.</p>
            </div>
            <a href="{{ route('inscription.create') }}" class="gv-btn-prim slide-up" style="white-space:nowrap;">
                <i class="fas fa-paper-plane"></i> Postuler au programme
            </a>
        </div>
        <div class="sv-cards">
            @foreach([
                ['fas fa-seedling','Incubation de Startups','Programme intensif de 12 semaines pour valider votre idée, construire votre MVP et préparer votre pitch.'],
                ['fas fa-tachometer-alt','Accélération','Accélération des startups en phase de croissance — réseau, financement et go-to-market.'],
                ['fas fa-user-tie','Mentorat','Sessions individuelles avec des mentors experts en technologie, finance, droit et entrepreneuriat.'],
                ['fas fa-file-alt','Business Plan','Élaboration complète de votre plan d\'affaires — prévisions financières, analyse SWOT et roadmap.'],
                ['fas fa-chess','Modèle Économique','Design et validation de votre business model canvas, étude de faisabilité et proposition de valeur.'],
                ['fas fa-chart-pie','Études de Marché','Recherche quantitative et qualitative pour comprendre votre marché, concurrents et opportunités.'],
                ['fas fa-search-dollar','Recherche de Financement','Accompagnement pour accéder aux subventions, investisseurs, fonds d\'impact et institutions de microfinance.'],
                ['fas fa-trophy','Préparation aux Concours','Coaching pour concours de startups, pitches d\'investisseurs et programmes internationaux.'],
                ['fas fa-chart-line','Stratégie de Croissance','Définition de votre go-to-market, stratégie de scaling et KPIs de performance.'],
                ['fas fa-digital-tachograph','Digitalisation des Entreprises','Transition numérique de PME — logiciels, processus, formation des équipes et suivi.'],
            ] as [$icon,$title,$desc])
            <div class="sv-card slide-up">
                <div class="sv-card-icon"><i class="{{ $icon }}"></i></div>
                <h3 class="sv-card-title">{{ $title }}</h3>
                <p class="sv-card-desc">{{ $desc }}</p>
                <a href="{{ route('inscription.create') }}" class="sv-card-link">
                    Postuler <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── 6. NOS SOLUTIONS SAAS ───────────────────────── --}}
<section class="sv-section" id="solutions" style="scroll-margin-top:80px;">
    <div class="sv-wrap">
        <div class="slide-up" style="text-align:center;margin-bottom:3rem;">
            <div class="sv-eyebrow" style="justify-content:center;"><i class="fas fa-cubes"></i> Plateformes Propriétaires</div>
            <h2 class="sv-section-title">Nos Solutions SaaS</h2>
            <p class="sv-section-sub" style="margin:0 auto;text-align:center;">GOVIBE développe ses propres plateformes numériques pour répondre aux besoins spécifiques du marché haïtien et caribéen.</p>
        </div>
        <div class="sv-solutions-grid">
            @foreach([
                ['fas fa-graduation-cap','KLASYO','Plateforme de formation en ligne (e-learning) — cours, certifications et suivi des apprenants en haïtien et français.'],
                ['fas fa-money-bill-wave','GOVIBE Pay','Solutions de paiement digital adaptées au contexte haïtien — intégration MonCash, carte et mobile money.'],
                ['fas fa-hospital','Hospify','Logiciel de gestion hospitalière intégré — patients, rendez-vous, facturation et dossiers médicaux électroniques.'],
                ['fas fa-nfc-symbol','TagToa','Solutions NFC innovantes pour cartes de visite digitales, événements et contrôle d\'accès sans contact.'],
                ['fas fa-search','Tchekela','Plateforme de gestion des objets perdus et trouvés — signalements, alertes et récupération sécurisée.'],
                ['fas fa-truck','Livrewo','Plateforme de livraison locale — connexion entre marchands, livreurs et clients en Haïti.'],
            ] as [$icon,$name,$desc])
            <div class="sv-solution-card slide-up">
                <div class="sv-sol-icon"><i class="{{ $icon }}"></i></div>
                <div>
                    <div class="sv-sol-name">{{ $name }}</div>
                    <div class="sv-sol-desc">{{ $desc }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── CLIENTS TYPES ────────────────────────────────── --}}
<div class="sv-clients">
    <div class="sv-wrap" style="text-align:center;">
        <p style="color:rgba(255,255,255,.4);font-size:.78rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;margin-bottom:0;">Nous accompagnons</p>
        <div class="sv-clients-scroll">
            @foreach([
                ['fas fa-building','Entreprises'],
                ['fas fa-store','PME / TPME'],
                ['fas fa-rocket','Startups'],
                ['fas fa-hands-helping','ONG'],
                ['fas fa-landmark','Institutions publiques'],
                ['fas fa-school','Écoles & Universités'],
                ['fas fa-hospital','Hôpitaux'],
                ['fas fa-shopping-basket','Commerçants'],
                ['fas fa-users','Associations'],
                ['fas fa-user-tie','Indépendants'],
                ['fas fa-globe','Diaspora haïtienne'],
                ['fas fa-church','Organisations religieuses'],
            ] as [$icon,$label])
            <div class="sv-client-tag"><i class="{{ $icon }}"></i> {{ $label }}</div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── POURQUOI GOVIBE ──────────────────────────────── --}}
<section class="sv-why">
    <div class="sv-wrap">
        <div class="slide-up" style="text-align:center;margin-bottom:0;">
            <div class="sv-eyebrow" style="justify-content:center;"><i class="fas fa-star"></i> Notre différence</div>
            <h2 class="sv-section-title">Pourquoi choisir GOVIBE ?</h2>
            <p class="sv-section-sub" style="margin:0 auto;text-align:center;">6 raisons pour lesquelles des centaines d'organisations font confiance à GOVIBE pour leur transformation numérique.</p>
        </div>
        <div class="sv-why-grid">
            @foreach([
                ['fas fa-layer-group','Expertise Multidisciplinaire','Une équipe de 50+ experts couvrant le digital, la tech, l\'IA, le design, la formation et le conseil stratégique.'],
                ['fas fa-lightbulb','Solutions Innovantes','Nous ne copions pas les solutions existantes — nous créons des réponses adaptées au contexte haïtien et caribéen.'],
                ['fas fa-user-tie','Équipe Professionnelle','13 employés à temps plein et 23 à temps partiel — des profils certifiés avec une expérience internationale.'],
                ['fas fa-hand-holding-heart','Accompagnement Personnalisé','Suivi dédié de A à Z — pas de support générique. Votre succès est notre KPI principal.'],
                ['fas fa-headset','Support Technique 24/7','Call center disponible, chat WhatsApp et support en ligne pour ne jamais vous laisser seul.'],
                ['fas fa-chart-line','Orienté Résultats','Nous travaillons avec des objectifs mesurables, des livrables concrets et des rapports transparents.'],
            ] as [$icon,$title,$desc])
            <div class="sv-why-card slide-up">
                <div class="sv-why-icon"><i class="{{ $icon }}"></i></div>
                <div class="sv-why-title">{{ $title }}</div>
                <div class="sv-why-desc">{{ $desc }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── CTA ────────────────────────────────────────────── --}}
<section class="sv-cta">
    <div class="sv-wrap" style="position:relative;z-index:1;">
        <h2 class="slide-up">Prêt à développer votre projet ?</h2>
        <p class="slide-up">Notre équipe est prête à vous accompagner dans votre transformation digitale. Contactez-nous dès aujourd'hui pour un diagnostic gratuit.</p>
        <div class="sv-cta-btns slide-up">
            <a href="{{ route('inscription.create') }}" class="btn-white">
                <i class="fas fa-paper-plane"></i> Demander un devis
            </a>
            <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE,+je+souhaite+en+savoir+plus+sur+vos+services" target="_blank" class="btn-outline-white">
                <i class="fab fa-whatsapp"></i> Nous contacter
            </a>
        </div>
    </div>
</section>

@endsection

@section('scripts')
<script>
/* ── Smooth scroll for anchors ─────────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

/* ── Canvas particle background ─────────────────────── */
(function(){
    const canvas = document.getElementById('sv-canvas');
    if(!canvas) return;
    const ctx = canvas.getContext('2d');
    let W, H, particles = [];
    const WORDS = ['IA','Web','Mobile','ERP','SaaS','Design','Cloud','SEO','Branding','CRM','API','UX'];
    const N = 30;

    function resize(){
        W = canvas.width = canvas.offsetWidth;
        H = canvas.height = canvas.offsetHeight;
    }

    function rand(a,b){ return Math.random()*(b-a)+a; }

    function initParticles(){
        particles = [];
        for(let i=0;i<N;i++){
            particles.push({
                x: rand(0,W), y: rand(0,H),
                vx: rand(-.3,.3), vy: rand(-.4,-.1),
                alpha: rand(.05,.2),
                size: rand(9,15),
                word: WORDS[Math.floor(Math.random()*WORDS.length)],
                isWord: Math.random() > .5
            });
        }
    }

    function draw(){
        ctx.clearRect(0,0,W,H);
        particles.forEach(p => {
            p.x += p.vx; p.y += p.vy;
            if(p.y < -30) p.y = H+10;
            if(p.x < -40) p.x = W+10;
            if(p.x > W+40) p.x = -10;
            ctx.globalAlpha = p.alpha;
            if(p.isWord){
                ctx.fillStyle = '#DC2626';
                ctx.font = `bold ${p.size}px 'DM Sans',sans-serif`;
                ctx.fillText(p.word, p.x, p.y);
            } else {
                ctx.fillStyle = '#DC2626';
                ctx.beginPath();
                ctx.arc(p.x, p.y, 2, 0, Math.PI*2);
                ctx.fill();
            }
        });
        ctx.globalAlpha = 1;
        requestAnimationFrame(draw);
    }

    resize();
    initParticles();
    draw();
    window.addEventListener('resize', ()=>{ resize(); initParticles(); });
})();
</script>
@endsection

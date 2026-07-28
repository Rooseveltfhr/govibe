@extends('layouts.public')

@section('title', 'GOVIBE Coworking Space — Innovation Hub Haïti')

@section('head')
<style>
:root { --gv-primary: #DC2626; }

/* ── Hero ─────────────────────────────────────────── */
.cw-hero {
    background: linear-gradient(135deg,#0a0000 0%,#1a0004 45%,#050505 100%);
    min-height: 78vh;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    padding: 120px 0 80px;
}
.cw-hero::before {
    content:'';
    position:absolute;
    inset:0;
    background:
        radial-gradient(ellipse 60% 50% at 80% 30%, rgba(220,38,38,.18) 0%, transparent 70%),
        radial-gradient(ellipse 40% 40% at 20% 70%, rgba(220,38,38,.10) 0%, transparent 60%);
    pointer-events:none;
}
.cw-hero-grid {
    position:absolute;
    inset:0;
    background-image:
        linear-gradient(rgba(220,38,38,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(220,38,38,.04) 1px, transparent 1px);
    background-size:50px 50px;
    pointer-events:none;
}
.cw-hero-tag {
    display:inline-block;
    background:rgba(220,38,38,.15);
    border:1px solid rgba(220,38,38,.35);
    color:#f87171;
    font-size:.78rem;
    font-weight:700;
    letter-spacing:.1em;
    text-transform:uppercase;
    padding:.35rem 1rem;
    border-radius:2rem;
    margin-bottom:1.5rem;
}
.cw-hero h1 {
    font-family:'Anton',sans-serif;
    font-size:clamp(2.4rem,7vw,4.5rem);
    color:#fff;
    line-height:1.05;
    margin-bottom:1.2rem;
}
.cw-hero h1 span { color:var(--gv-primary); }
.cw-hero p.lead {
    color:rgba(255,255,255,.72);
    font-size:1.15rem;
    max-width:520px;
    margin-bottom:2.2rem;
    line-height:1.7;
}
.cw-hero-btns { display:flex; gap:1rem; flex-wrap:wrap; }
.cw-hero-visual {
    position:relative;
    border-radius:1.2rem;
    overflow:hidden;
    background:linear-gradient(135deg,#1a0004 0%,#2a0008 100%);
    min-height:360px;
    display:flex;
    flex-direction:column;
    gap:1rem;
    padding:2rem;
}
.cw-space-card {
    background:rgba(255,255,255,.04);
    border:1px solid rgba(220,38,38,.18);
    border-radius:.9rem;
    padding:1.2rem 1.5rem;
    display:flex;
    align-items:center;
    gap:1rem;
    transition:border-color .3s;
}
.cw-space-card:hover { border-color:rgba(220,38,38,.5); }
.cw-space-card i { font-size:1.5rem; color:var(--gv-primary); min-width:2rem; text-align:center; }
.cw-space-card h4 { font-family:'Anton',sans-serif; color:#fff; font-size:1rem; margin:0 0 .2rem; }
.cw-space-card p { color:rgba(255,255,255,.55); font-size:.82rem; margin:0; }
.cw-badge-live {
    position:absolute;
    top:1rem; right:1rem;
    background:rgba(220,38,38,.2);
    border:1px solid rgba(220,38,38,.4);
    color:#f87171;
    font-size:.72rem;
    font-weight:700;
    letter-spacing:.08em;
    padding:.25rem .75rem;
    border-radius:1rem;
    display:flex;
    align-items:center;
    gap:.4rem;
}
.cw-badge-live::before {
    content:'';
    width:.55rem;
    height:.55rem;
    background:#DC2626;
    border-radius:50%;
    animation:pulse-dot 1.5s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%,100% { opacity:1; transform:scale(1); }
    50% { opacity:.5; transform:scale(.8); }
}

/* ── Stats bar ─────────────────────────────────────── */
.cw-stats {
    background:#0d0d0d;
    border-top:1px solid rgba(220,38,38,.12);
    border-bottom:1px solid rgba(220,38,38,.12);
    padding:2rem 0;
}
.cw-stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
@media(max-width:640px){.cw-stats-grid{grid-template-columns:repeat(2,1fr);}}
.cw-stat { text-align:center; }
.cw-stat .num { font-family:'Anton',sans-serif; font-size:2rem; color:var(--gv-primary); }
.cw-stat .lbl { color:rgba(255,255,255,.55); font-size:.82rem; margin-top:.2rem; }

/* ── Plans ─────────────────────────────────────────── */
.cw-plans { background:#06060f; padding:80px 0; }
.cw-plans-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; margin-top:3rem; }
@media(max-width:900px){.cw-plans-grid{grid-template-columns:1fr;}}
.plan-card {
    background:rgba(255,255,255,.03);
    border:1px solid rgba(220,38,38,.15);
    border-radius:1.2rem;
    padding:2rem;
    position:relative;
    transition:transform .3s,border-color .3s,box-shadow .3s;
}
.plan-card:hover {
    transform:translateY(-6px);
    border-color:rgba(220,38,38,.45);
    box-shadow:0 20px 50px rgba(220,38,38,.15);
}
.plan-card.featured {
    border-color:rgba(220,38,38,.5);
    background:linear-gradient(135deg,rgba(220,38,38,.08) 0%,rgba(220,38,38,.03) 100%);
}
.plan-badge {
    position:absolute;
    top:-12px; left:50%; transform:translateX(-50%);
    background:var(--gv-primary);
    color:#fff;
    font-size:.72rem;
    font-weight:700;
    letter-spacing:.06em;
    padding:.25rem 1rem;
    border-radius:1rem;
    white-space:nowrap;
}
.plan-icon { font-size:2rem; color:var(--gv-primary); margin-bottom:1rem; }
.plan-name { font-family:'Anton',sans-serif; font-size:1.5rem; color:#fff; margin-bottom:.5rem; }
.plan-price { margin-bottom:1.5rem; }
.plan-price .amount { font-family:'Anton',sans-serif; font-size:2.2rem; color:#fff; }
.plan-price .period { color:rgba(255,255,255,.45); font-size:.9rem; }
.plan-price .currency { color:var(--gv-primary); font-size:1.1rem; font-weight:700; }
.plan-features { list-style:none; padding:0; margin:0 0 2rem; }
.plan-features li {
    color:rgba(255,255,255,.72);
    font-size:.9rem;
    padding:.5rem 0;
    border-bottom:1px solid rgba(255,255,255,.05);
    display:flex;
    align-items:center;
    gap:.6rem;
}
.plan-features li:last-child { border-bottom:none; }
.plan-features li i { color:var(--gv-primary); font-size:.8rem; min-width:1rem; }

/* ── Spaces ─────────────────────────────────────────── */
.cw-spaces { background:#0a0000; padding:80px 0; }
.spaces-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem; margin-top:3rem; }
@media(max-width:900px){.spaces-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){.spaces-grid{grid-template-columns:1fr;}}
.space-tile {
    background:rgba(255,255,255,.03);
    border:1px solid rgba(220,38,38,.12);
    border-radius:1rem;
    padding:2rem 1.5rem;
    text-align:center;
    transition:all .3s;
}
.space-tile:hover {
    border-color:rgba(220,38,38,.4);
    background:rgba(220,38,38,.06);
    transform:translateY(-4px);
}
.space-tile i { font-size:2.4rem; color:var(--gv-primary); margin-bottom:1rem; display:block; }
.space-tile h3 { font-family:'Anton',sans-serif; font-size:1.1rem; color:#fff; margin-bottom:.6rem; }
.space-tile p { color:rgba(255,255,255,.55); font-size:.85rem; line-height:1.6; margin:0; }

/* ── Amenities ─────────────────────────────────────── */
.cw-amenities { background:#06060f; padding:80px 0; }
.amenities-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1.2rem; margin-top:3rem; }
@media(max-width:900px){.amenities-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.amenities-grid{grid-template-columns:1fr 1fr;}}
.amenity-item {
    display:flex;
    align-items:center;
    gap:.8rem;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(220,38,38,.1);
    border-radius:.8rem;
    padding:1rem;
    transition:border-color .3s;
}
.amenity-item:hover { border-color:rgba(220,38,38,.35); }
.amenity-item i { color:var(--gv-primary); font-size:1.2rem; min-width:1.5rem; text-align:center; }
.amenity-item span { color:rgba(255,255,255,.75); font-size:.85rem; }

/* ── Booking CTA ───────────────────────────────────── */
.cw-cta {
    background:linear-gradient(135deg,#DC2626 0%,#7f1d1d 100%);
    padding:80px 0;
    text-align:center;
    position:relative;
    overflow:hidden;
}
.cw-cta::before {
    content:'';
    position:absolute;
    inset:0;
    background:radial-gradient(ellipse 60% 80% at 50% 50%,rgba(255,255,255,.06) 0%,transparent 70%);
    pointer-events:none;
}
.cw-cta h2 { font-family:'Anton',sans-serif; font-size:clamp(1.8rem,5vw,3rem); color:#fff; margin-bottom:1rem; }
.cw-cta p { color:rgba(255,255,255,.82); font-size:1.05rem; max-width:480px; margin:0 auto 2rem; }
.cw-cta-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }
.btn-white {
    background:#fff;
    color:var(--gv-primary);
    font-weight:700;
    font-size:.92rem;
    padding:.75rem 2rem;
    border-radius:.5rem;
    text-decoration:none;
    transition:transform .25s,box-shadow .25s;
    display:inline-flex;
    align-items:center;
    gap:.5rem;
}
.btn-white:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(0,0,0,.3); }
.btn-outline-white {
    background:transparent;
    color:#fff;
    font-weight:700;
    font-size:.92rem;
    padding:.75rem 2rem;
    border-radius:.5rem;
    border:2px solid rgba(255,255,255,.5);
    text-decoration:none;
    transition:all .25s;
    display:inline-flex;
    align-items:center;
    gap:.5rem;
}
.btn-outline-white:hover { border-color:#fff; background:rgba(255,255,255,.1); }

/* ── Section headings ──────────────────────────────── */
.section-eyebrow {
    font-size:.75rem;
    font-weight:700;
    letter-spacing:.12em;
    text-transform:uppercase;
    color:var(--gv-primary);
    margin-bottom:.6rem;
}
.section-title {
    font-family:'Anton',sans-serif;
    font-size:clamp(1.8rem,4vw,2.8rem);
    color:#fff;
    margin-bottom:1rem;
    line-height:1.1;
}
.section-sub {
    color:rgba(255,255,255,.55);
    font-size:1rem;
    max-width:520px;
}
.section-sub.centered { margin:0 auto; text-align:center; }

/* ── Testimonials ──────────────────────────────────── */
.cw-testimonials { background:#0a0000; padding:80px 0; }
.testimonials-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1.5rem; margin-top:3rem; }
@media(max-width:700px){.testimonials-grid{grid-template-columns:1fr;}}
.testimonial-card {
    background:rgba(255,255,255,.03);
    border:1px solid rgba(220,38,38,.12);
    border-radius:1.2rem;
    padding:2rem;
    position:relative;
}
.testimonial-card::before {
    content:'"';
    position:absolute;
    top:1rem; left:1.5rem;
    font-size:4rem;
    color:rgba(220,38,38,.25);
    font-family:'Anton',sans-serif;
    line-height:1;
}
.testimonial-card p {
    color:rgba(255,255,255,.72);
    font-size:.95rem;
    line-height:1.7;
    margin:1rem 0 1.5rem;
    padding-top:.5rem;
}
.testimonial-author { display:flex; align-items:center; gap:.8rem; }
.t-avatar {
    width:44px; height:44px;
    border-radius:50%;
    background:var(--gv-primary);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-weight:700; font-size:.9rem;
    flex-shrink:0;
}
.t-name { color:#fff; font-weight:700; font-size:.9rem; }
.t-role { color:rgba(255,255,255,.45); font-size:.78rem; }
</style>
@endsection

@section('content')

{{-- ── Hero ─────────────────────────────────────────── --}}
<section class="cw-hero">
    <div class="cw-hero-grid"></div>
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center;">
            <div class="slide-up">
                <div class="cw-hero-tag"><i class="fas fa-wifi"></i> &nbsp;Espace Connecté</div>
                <h1>GOVIBE<br><span>Coworking</span><br>Space</h1>
                <p class="lead">Un espace de travail professionnel, collaboratif et inspirant au cœur de Port-au-Prince. Rejoignez la communauté des entrepreneurs, freelancers et startups qui façonnent l'avenir d'Haïti.</p>
                <div class="cw-hero-btns">
                    <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                        <i class="fas fa-calendar-check"></i> Réserver mon espace
                    </a>
                    <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE,+je+veux+en+savoir+plus+sur+le+Coworking" target="_blank" class="gv-btn-outline">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                </div>
            </div>
            <div class="cw-hero-visual slide-up" style="animation-delay:.2s;">
                <div class="cw-badge-live">OUVERT</div>
                <div class="cw-space-card">
                    <i class="fas fa-desk"></i>
                    <div>
                        <h4>Hot Desk</h4>
                        <p>Poste flexible disponible à la journée ou à la semaine</p>
                    </div>
                </div>
                <div class="cw-space-card">
                    <i class="fas fa-user-tie"></i>
                    <div>
                        <h4>Bureau Dédié</h4>
                        <p>Votre propre espace réservé — mensuel ou trimestriel</p>
                    </div>
                </div>
                <div class="cw-space-card">
                    <i class="fas fa-users"></i>
                    <div>
                        <h4>Salle de Réunion</h4>
                        <p>Salles équipées pour présentations et réunions d'équipe</p>
                    </div>
                </div>
                <div class="cw-space-card">
                    <i class="fas fa-building"></i>
                    <div>
                        <h4>Bureau Privé</h4>
                        <p>Espace fermé pour équipes de 2 à 10 personnes</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Stats bar ─────────────────────────────────────── --}}
<div class="cw-stats">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="cw-stats-grid">
            <div class="cw-stat slide-up">
                <div class="num">120+</div>
                <div class="lbl">Membres actifs</div>
            </div>
            <div class="cw-stat slide-up" style="animation-delay:.1s;">
                <div class="num">4</div>
                <div class="lbl">Types d'espaces</div>
            </div>
            <div class="cw-stat slide-up" style="animation-delay:.2s;">
                <div class="num">24/7</div>
                <div class="lbl">Accès disponible</div>
            </div>
            <div class="cw-stat slide-up" style="animation-delay:.3s;">
                <div class="num">2020</div>
                <div class="lbl">Depuis</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Plans & Tarifs ────────────────────────────────── --}}
<section class="cw-plans">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">Nos Formules</div>
            <h2 class="section-title">Plans & Tarifs</h2>
            <p class="section-sub centered">Des options flexibles adaptées à votre rythme de travail et votre budget.</p>
        </div>
        <div class="cw-plans-grid">
            {{-- Hot Desk --}}
            <div class="plan-card slide-up">
                <div class="plan-icon"><i class="fas fa-laptop"></i></div>
                <div class="plan-name">Hot Desk</div>
                <div class="plan-price">
                    <span class="currency">HTG </span><span class="amount">1 500</span><span class="period"> / jour</span>
                </div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Poste de travail flexible</li>
                    <li><i class="fas fa-check"></i> WiFi haut débit sécurisé</li>
                    <li><i class="fas fa-check"></i> Électricité & onduleur</li>
                    <li><i class="fas fa-check"></i> Café & eau inclus</li>
                    <li><i class="fas fa-check"></i> Accès lounge commun</li>
                    <li><i class="fas fa-check"></i> Casier sécurisé</li>
                </ul>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="width:100%;text-align:center;display:block;">
                    Réserver
                </a>
            </div>

            {{-- Bureau Dédié --}}
            <div class="plan-card featured slide-up" style="animation-delay:.1s;">
                <div class="plan-badge">Le Plus Populaire</div>
                <div class="plan-icon"><i class="fas fa-user-tie"></i></div>
                <div class="plan-name">Bureau Dédié</div>
                <div class="plan-price">
                    <span class="currency">HTG </span><span class="amount">22 000</span><span class="period"> / mois</span>
                </div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Poste attitré et sécurisé</li>
                    <li><i class="fas fa-check"></i> WiFi haut débit dédié</li>
                    <li><i class="fas fa-check"></i> Électricité & onduleur</li>
                    <li><i class="fas fa-check"></i> 4h/mois salle de réunion</li>
                    <li><i class="fas fa-check"></i> Adresse postale GOVIBE</li>
                    <li><i class="fas fa-check"></i> Accès communauté + events</li>
                    <li><i class="fas fa-check"></i> Café & eau illimités</li>
                </ul>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="width:100%;text-align:center;display:block;">
                    Démarrer
                </a>
            </div>

            {{-- Bureau Privé --}}
            <div class="plan-card slide-up" style="animation-delay:.2s;">
                <div class="plan-icon"><i class="fas fa-building"></i></div>
                <div class="plan-name">Bureau Privé</div>
                <div class="plan-price">
                    <span class="currency">HTG </span><span class="amount">65 000</span><span class="period"> / mois</span>
                </div>
                <ul class="plan-features">
                    <li><i class="fas fa-check"></i> Bureau fermé (2–10 personnes)</li>
                    <li><i class="fas fa-check"></i> Réseau WiFi privatisé</li>
                    <li><i class="fas fa-check"></i> Électricité & groupe électrogène</li>
                    <li><i class="fas fa-check"></i> Salle de réunion illimitée</li>
                    <li><i class="fas fa-check"></i> Adresse et domiciliation</li>
                    <li><i class="fas fa-check"></i> Support IT inclus</li>
                    <li><i class="fas fa-check"></i> Accès 24h/7j</li>
                </ul>
                <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE,+je+suis+intéressé+par+un+bureau+privé" target="_blank" class="gv-btn-outline" style="width:100%;text-align:center;display:block;">
                    Nous Contacter
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ── Types d'espaces ──────────────────────────────── --}}
<section class="cw-spaces">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">Nos Espaces</div>
            <h2 class="section-title">Un Espace Pour Chaque Besoin</h2>
            <p class="section-sub centered">Du poste ouvert à la salle de conférence — GOVIBE s'adapte à votre façon de travailler.</p>
        </div>
        <div class="spaces-grid">
            <div class="space-tile slide-up">
                <i class="fas fa-laptop-code"></i>
                <h3>Open Space</h3>
                <p>Environnement dynamique et collaboratif pour freelancers et professionnels nomades.</p>
            </div>
            <div class="space-tile slide-up" style="animation-delay:.08s;">
                <i class="fas fa-user-lock"></i>
                <h3>Bureau Dédié</h3>
                <p>Votre poste attitré avec rangement sécurisé — disponible à la semaine ou au mois.</p>
            </div>
            <div class="space-tile slide-up" style="animation-delay:.16s;">
                <i class="fas fa-door-closed"></i>
                <h3>Bureau Privé</h3>
                <p>Espace fermé pour équipes — confidentialité totale et ambiance bureau d'entreprise.</p>
            </div>
            <div class="space-tile slide-up" style="animation-delay:.24s;">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>Salle de Réunion</h3>
                <p>Équipée d'un projecteur, tableau blanc, vidéoconférence — pour 4 à 20 personnes.</p>
            </div>
            <div class="space-tile slide-up" style="animation-delay:.32s;">
                <i class="fas fa-podcast"></i>
                <h3>Studio Podcast / Media</h3>
                <p>Enregistrement audio-vidéo professionnel pour content creators et conférenciers.</p>
            </div>
            <div class="space-tile slide-up" style="animation-delay:.4s;">
                <i class="fas fa-mug-hot"></i>
                <h3>Lounge & Networking</h3>
                <p>Espace détente pour pauses, rencontres informelles et opportunités de réseautage.</p>
            </div>
        </div>
    </div>
</section>

{{-- ── Commodités ───────────────────────────────────── --}}
<section class="cw-amenities">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;margin-bottom:0;">
            <div class="section-eyebrow">Inclus</div>
            <h2 class="section-title">Tout ce qu'il vous faut</h2>
            <p class="section-sub centered" style="margin-bottom:0;">Toutes les commodités sont comprises dans votre abonnement.</p>
        </div>
        <div class="amenities-grid">
            @foreach([
                ['fas fa-wifi','WiFi Haut Débit'],
                ['fas fa-bolt','Électricité Stable'],
                ['fas fa-car','Parking Sécurisé'],
                ['fas fa-mug-hot','Café & Eau'],
                ['fas fa-print','Imprimante & Scanner'],
                ['fas fa-video','Vidéoconférence'],
                ['fas fa-lock','Accès Sécurisé'],
                ['fas fa-headset','Support Technique'],
                ['fas fa-envelope','Adresse Postale'],
                ['fas fa-shield-alt','Sécurité 24/7'],
                ['fas fa-snowflake','Climatisation'],
                ['fas fa-calendar-alt','Events Mensuels'],
            ] as [$icon,$label])
            <div class="amenity-item slide-up">
                <i class="{{ $icon }}"></i>
                <span>{{ $label }}</span>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── Témoignages ─────────────────────────────────── --}}
<section class="cw-testimonials">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">Témoignages</div>
            <h2 class="section-title">Ce que disent nos membres</h2>
        </div>
        <div class="testimonials-grid">
            <div class="testimonial-card slide-up">
                <p>GOVIBE Coworking m'a permis de développer mon réseau professionnel tout en ayant accès à une infrastructure de qualité. En 6 mois, j'ai signé 3 nouveaux contrats grâce aux événements networking.</p>
                <div class="testimonial-author">
                    <div class="t-avatar">ML</div>
                    <div>
                        <div class="t-name">Marie-Louise Jean</div>
                        <div class="t-role">Consultante en Marketing Digital</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card slide-up" style="animation-delay:.1s;">
                <p>Comme startup en phase de démarrage, GOVIBE était la solution idéale. Un bureau professionnel sans les coûts d'un bail traditionnel. L'équipe est formidable et l'ambiance très motivante.</p>
                <div class="testimonial-author">
                    <div class="t-avatar">PD</div>
                    <div>
                        <div class="t-name">Pierre Dupont</div>
                        <div class="t-role">Fondateur, TechStart Haïti</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA ──────────────────────────────────────────── --}}
<section class="cw-cta">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;position:relative;z-index:1;">
        <h2 class="slide-up">Prêt à rejoindre la communauté GOVIBE ?</h2>
        <p class="slide-up">Réservez votre espace dès aujourd'hui et rejoignez plus de 120 professionnels qui travaillent, collaborent et innovent chez GOVIBE.</p>
        <div class="cw-cta-btns slide-up">
            <a href="{{ route('inscription.create') }}" class="btn-white">
                <i class="fas fa-calendar-check"></i> Réserver maintenant
            </a>
            <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE,+je+voudrais+une+visite+du+Coworking" target="_blank" class="btn-outline-white">
                <i class="fab fa-whatsapp"></i> Visiter l'espace
            </a>
        </div>
    </div>
</section>

@endsection

@section('scripts')
<script>
// Make hero visual cards responsive
(function(){
    const heroGrid = document.querySelector('.cw-hero .container > div');
    if(!heroGrid) return;
    function adjustLayout(){
        if(window.innerWidth < 900){
            heroGrid.style.gridTemplateColumns = '1fr';
        } else {
            heroGrid.style.gridTemplateColumns = '1fr 1fr';
        }
    }
    adjustLayout();
    window.addEventListener('resize', adjustLayout);
})();
</script>
@endsection

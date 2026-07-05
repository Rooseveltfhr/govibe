@extends('layouts.public')

@section('title', 'GOVIBE Academy — Formation Professionnelle & Digitale en Haïti')

@section('head')
<style>
:root { --gv-primary: #DC2626; }

/* ── Hero ─────────────────────────────────────────── */
.ac-hero {
    background: linear-gradient(135deg,#0a0000 0%,#1a0004 50%,#06060f 100%);
    min-height: 82vh;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    padding: 130px 0 80px;
}
.ac-hero::before {
    content:'';
    position:absolute; inset:0;
    background:
        radial-gradient(ellipse 65% 55% at 85% 25%, rgba(220,38,38,.22) 0%, transparent 65%),
        radial-gradient(ellipse 45% 40% at 15% 75%, rgba(220,38,38,.10) 0%, transparent 60%);
    pointer-events:none;
}
.ac-hero-grid {
    position:absolute; inset:0;
    background-image:
        linear-gradient(rgba(220,38,38,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(220,38,38,.04) 1px, transparent 1px);
    background-size:55px 55px;
    pointer-events:none;
}
.ac-hero-tag {
    display:inline-block;
    background:rgba(220,38,38,.15);
    border:1px solid rgba(220,38,38,.35);
    color:#f87171;
    font-size:.78rem; font-weight:700;
    letter-spacing:.1em; text-transform:uppercase;
    padding:.35rem 1rem; border-radius:2rem;
    margin-bottom:1.5rem;
}
.ac-hero h1 {
    font-family:'Anton',sans-serif;
    font-size:clamp(2.4rem,7vw,4.8rem);
    color:#fff; line-height:1.02;
    margin-bottom:1.2rem;
}
.ac-hero h1 span { color:var(--gv-primary); }
.ac-hero p.lead {
    color:rgba(255,255,255,.72);
    font-size:1.1rem; max-width:540px;
    line-height:1.75; margin-bottom:2rem;
}
.ac-hero-btns { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:2.5rem; }
.ac-hero-stats {
    display:flex; gap:2rem; flex-wrap:wrap;
}
.ac-hero-stat .num {
    font-family:'Anton',sans-serif;
    font-size:1.8rem; color:var(--gv-primary);
}
.ac-hero-stat .lbl { color:rgba(255,255,255,.55); font-size:.82rem; }

/* ── Formations grid ─────────────────────────────── */
.ac-formations { background:#06060f; padding:90px 0; }
.formations-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:1.5rem;
    margin-top:3rem;
}
@media(max-width:900px){.formations-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){.formations-grid{grid-template-columns:1fr;}}
.formation-card {
    background:rgba(255,255,255,.03);
    border:1px solid rgba(220,38,38,.12);
    border-radius:1.2rem;
    padding:1.8rem;
    position:relative;
    transition:all .3s;
    display:flex; flex-direction:column;
}
.formation-card:hover {
    transform:translateY(-6px);
    border-color:rgba(220,38,38,.4);
    box-shadow:0 20px 50px rgba(220,38,38,.12);
}
.formation-card.hot { border-color:rgba(220,38,38,.35); }
.formation-badge {
    position:absolute; top:1rem; right:1rem;
    background:var(--gv-primary);
    color:#fff; font-size:.65rem; font-weight:700;
    letter-spacing:.06em; padding:.2rem .6rem;
    border-radius:.4rem;
}
.formation-icon { font-size:2rem; color:var(--gv-primary); margin-bottom:1rem; }
.formation-title {
    font-family:'Anton',sans-serif;
    font-size:1.15rem; color:#fff;
    margin-bottom:.5rem;
}
.formation-desc {
    color:rgba(255,255,255,.55);
    font-size:.88rem; line-height:1.6;
    margin-bottom:1.2rem; flex:1;
}
.formation-meta {
    display:flex; gap:1rem; flex-wrap:wrap;
    margin-bottom:1.5rem;
}
.formation-meta span {
    color:rgba(255,255,255,.45);
    font-size:.78rem;
    display:flex; align-items:center; gap:.35rem;
}
.formation-meta i { color:var(--gv-primary); }
.formation-tags {
    display:flex; flex-wrap:wrap; gap:.4rem;
    margin-bottom:1.5rem;
}
.formation-tag {
    background:rgba(220,38,38,.08);
    border:1px solid rgba(220,38,38,.2);
    color:#f87171;
    font-size:.7rem; font-weight:600;
    padding:.2rem .55rem; border-radius:.3rem;
}

/* ── Programmes ─────────────────────────────────── */
.ac-programmes { background:#0a0000; padding:80px 0; }
.programmes-grid {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:1.5rem;
    margin-top:3rem;
}
@media(max-width:700px){.programmes-grid{grid-template-columns:1fr;}}
.programme-card {
    background:rgba(255,255,255,.02);
    border:1px solid rgba(220,38,38,.12);
    border-radius:1.2rem;
    padding:2rem;
    display:flex; gap:1.5rem;
    align-items:flex-start;
    transition:all .3s;
}
.programme-card:hover {
    border-color:rgba(220,38,38,.4);
    background:rgba(220,38,38,.04);
}
.prog-icon {
    width:54px; height:54px; flex-shrink:0;
    border-radius:.8rem;
    background:rgba(220,38,38,.15);
    border:1px solid rgba(220,38,38,.25);
    display:flex; align-items:center; justify-content:center;
    font-size:1.4rem; color:var(--gv-primary);
}
.prog-title {
    font-family:'Anton',sans-serif;
    font-size:1.1rem; color:#fff;
    margin-bottom:.5rem;
}
.prog-desc {
    color:rgba(255,255,255,.58);
    font-size:.88rem; line-height:1.6;
    margin-bottom:1rem;
}
.prog-link {
    color:var(--gv-primary);
    font-size:.85rem; font-weight:700;
    text-decoration:none;
    display:inline-flex; align-items:center; gap:.4rem;
    transition:gap .2s;
}
.prog-link:hover { gap:.7rem; }

/* ── Comment ça marche ─────────────────────────── */
.ac-how { background:#06060f; padding:80px 0; }
.how-steps {
    display:grid; grid-template-columns:repeat(4,1fr);
    gap:1.5rem; margin-top:3rem;
}
@media(max-width:900px){.how-steps{grid-template-columns:1fr 1fr;}}
@media(max-width:500px){.how-steps{grid-template-columns:1fr;}}
.how-step { text-align:center; }
.how-step-num {
    width:56px; height:56px;
    border-radius:50%;
    background:rgba(220,38,38,.12);
    border:2px solid rgba(220,38,38,.35);
    color:var(--gv-primary);
    font-family:'Anton',sans-serif; font-size:1.3rem;
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 1.2rem;
}
.how-step h4 { font-family:'Anton',sans-serif; color:#fff; font-size:1rem; margin-bottom:.5rem; }
.how-step p { color:rgba(255,255,255,.5); font-size:.84rem; line-height:1.55; margin:0; }

/* ── Instructors ────────────────────────────────── */
.ac-instructors { background:#0a0000; padding:80px 0; }
.instructors-grid {
    display:grid; grid-template-columns:repeat(3,1fr);
    gap:1.5rem; margin-top:3rem;
}
@media(max-width:900px){.instructors-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){.instructors-grid{grid-template-columns:1fr;}}
.instructor-card {
    background:rgba(255,255,255,.03);
    border:1px solid rgba(220,38,38,.1);
    border-radius:1.2rem;
    padding:1.8rem; text-align:center;
    transition:all .3s;
}
.instructor-card:hover {
    border-color:rgba(220,38,38,.35);
    transform:translateY(-4px);
}
.instructor-avatar {
    width:72px; height:72px; border-radius:50%;
    background:linear-gradient(135deg,var(--gv-primary),#7f1d1d);
    display:flex; align-items:center; justify-content:center;
    font-family:'Anton',sans-serif; font-size:1.4rem; color:#fff;
    margin:0 auto 1rem;
    border:2px solid rgba(220,38,38,.3);
}
.instructor-name { font-family:'Anton',sans-serif; color:#fff; font-size:1rem; margin-bottom:.3rem; }
.instructor-role { color:var(--gv-primary); font-size:.8rem; font-weight:600; margin-bottom:.7rem; }
.instructor-bio { color:rgba(255,255,255,.5); font-size:.82rem; line-height:1.55; }

/* ── CTA ────────────────────────────────────────── */
.ac-cta {
    background:linear-gradient(135deg,#DC2626 0%,#7f1d1d 100%);
    padding:90px 0; text-align:center; position:relative; overflow:hidden;
}
.ac-cta::before {
    content:'';
    position:absolute; inset:0;
    background:radial-gradient(ellipse 70% 80% at 50% 50%,rgba(255,255,255,.07) 0%,transparent 70%);
    pointer-events:none;
}
.ac-cta h2 {
    font-family:'Anton',sans-serif;
    font-size:clamp(2rem,5vw,3.2rem); color:#fff;
    margin-bottom:1rem;
}
.ac-cta p { color:rgba(255,255,255,.85); font-size:1.05rem; max-width:520px; margin:0 auto 2.2rem; }
.ac-cta-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }
.btn-white {
    background:#fff; color:var(--gv-primary);
    font-weight:700; font-size:.95rem;
    padding:.8rem 2.2rem; border-radius:.5rem;
    text-decoration:none;
    transition:transform .25s,box-shadow .25s;
    display:inline-flex; align-items:center; gap:.5rem;
}
.btn-white:hover { transform:translateY(-3px); box-shadow:0 12px 32px rgba(0,0,0,.35); }
.btn-outline-white {
    background:transparent; color:#fff;
    font-weight:700; font-size:.95rem;
    padding:.8rem 2.2rem; border-radius:.5rem;
    border:2px solid rgba(255,255,255,.5);
    text-decoration:none; transition:all .25s;
    display:inline-flex; align-items:center; gap:.5rem;
}
.btn-outline-white:hover { border-color:#fff; background:rgba(255,255,255,.1); }

/* ── Testimonials ───────────────────────────────── */
.ac-testimonials { background:#06060f; padding:80px 0; }
.ac-testi-grid {
    display:grid; grid-template-columns:repeat(2,1fr);
    gap:1.5rem; margin-top:3rem;
}
@media(max-width:700px){.ac-testi-grid{grid-template-columns:1fr;}}
.ac-testi-card {
    background:rgba(255,255,255,.03);
    border:1px solid rgba(220,38,38,.1);
    border-radius:1.2rem; padding:2rem;
    position:relative;
}
.ac-testi-card::before {
    content:'"';
    position:absolute; top:.8rem; left:1.5rem;
    font-size:4rem; color:rgba(220,38,38,.2);
    font-family:'Anton',sans-serif; line-height:1;
}
.ac-testi-card p {
    color:rgba(255,255,255,.68); font-size:.92rem;
    line-height:1.7; margin:1rem 0 1.5rem; padding-top:.5rem;
}
.ac-testi-author { display:flex; align-items:center; gap:.8rem; }
.at-avatar {
    width:40px; height:40px; border-radius:50%;
    background:var(--gv-primary);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-weight:700; font-size:.85rem; flex-shrink:0;
}
.at-name { color:#fff; font-weight:700; font-size:.88rem; }
.at-role { color:rgba(255,255,255,.4); font-size:.76rem; }

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
<section class="ac-hero">
    <div class="ac-hero-grid"></div>
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;position:relative;z-index:1;">
        <div class="ac-hero-tag"><i class="fas fa-graduation-cap"></i> &nbsp;GOVIBE Academy</div>
        <h1>Formez-vous.<br>Évoluez.<br><span>Réussissez.</span></h1>
        <p class="lead">La première académie tech d'Haïti dédiée aux formations professionnelles en développement digital, IA, cybersécurité et entrepreneuriat. Apprenez des meilleurs experts et transformez votre carrière.</p>
        <div class="ac-hero-btns">
            <a href="{{ route('inscription.create') }}" class="gv-btn-prim">
                <i class="fas fa-pen-to-square"></i> S'inscrire maintenant
            </a>
            <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE+Academy,+je+veux+des+informations+sur+les+formations" target="_blank" class="gv-btn-outline">
                <i class="fab fa-whatsapp"></i> Demander un programme
            </a>
        </div>
        <div class="ac-hero-stats">
            <div class="ac-hero-stat">
                <div class="num">500+</div>
                <div class="lbl">Apprenants formés</div>
            </div>
            <div class="ac-hero-stat">
                <div class="num">15+</div>
                <div class="lbl">Formations actives</div>
            </div>
            <div class="ac-hero-stat">
                <div class="num">50+</div>
                <div class="lbl">Experts formateurs</div>
            </div>
            <div class="ac-hero-stat">
                <div class="num">95%</div>
                <div class="lbl">Taux de satisfaction</div>
            </div>
        </div>
    </div>
</section>

{{-- ── Formations ───────────────────────────────────── --}}
<section class="ac-formations">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">Catalogue</div>
            <h2 class="section-title">Nos Formations</h2>
            <p class="section-sub centered">Des programmes pratiques conçus pour le marché haïtien et international.</p>
        </div>
        <div class="formations-grid">

            <div class="formation-card hot slide-up">
                <div class="formation-badge">POPULAIRE</div>
                <div class="formation-icon"><i class="fas fa-code"></i></div>
                <div class="formation-title">Développement Web Full Stack</div>
                <div class="formation-desc">HTML, CSS, JavaScript, PHP/Laravel, React — de zéro à développeur employable.</div>
                <div class="formation-meta">
                    <span><i class="fas fa-clock"></i> 3 mois</span>
                    <span><i class="fas fa-users"></i> 20 places</span>
                    <span><i class="fas fa-star"></i> Débutant+</span>
                </div>
                <div class="formation-tags">
                    <span class="formation-tag">HTML/CSS</span>
                    <span class="formation-tag">JavaScript</span>
                    <span class="formation-tag">PHP</span>
                    <span class="formation-tag">Laravel</span>
                    <span class="formation-tag">React</span>
                </div>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="width:100%;text-align:center;display:block;margin-top:auto;">
                    S'inscrire
                </a>
            </div>

            <div class="formation-card slide-up" style="animation-delay:.08s;">
                <div class="formation-icon"><i class="fas fa-mobile-alt"></i></div>
                <div class="formation-title">Développement App Mobile</div>
                <div class="formation-desc">React Native et Flutter — créez des apps iOS & Android prêtes pour le marché.</div>
                <div class="formation-meta">
                    <span><i class="fas fa-clock"></i> 2 mois</span>
                    <span><i class="fas fa-users"></i> 15 places</span>
                    <span><i class="fas fa-star"></i> Intermédiaire</span>
                </div>
                <div class="formation-tags">
                    <span class="formation-tag">React Native</span>
                    <span class="formation-tag">Flutter</span>
                    <span class="formation-tag">Firebase</span>
                </div>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="width:100%;text-align:center;display:block;margin-top:auto;">
                    S'inscrire
                </a>
            </div>

            <div class="formation-card hot slide-up" style="animation-delay:.16s;">
                <div class="formation-badge">NOUVEAU</div>
                <div class="formation-icon"><i class="fas fa-brain"></i></div>
                <div class="formation-title">Intelligence Artificielle & ChatGPT</div>
                <div class="formation-desc">Prompting avancé, API OpenAI, automatisation IA et création d'agents — en créole et français.</div>
                <div class="formation-meta">
                    <span><i class="fas fa-clock"></i> 6 semaines</span>
                    <span><i class="fas fa-users"></i> 25 places</span>
                    <span><i class="fas fa-star"></i> Tous niveaux</span>
                </div>
                <div class="formation-tags">
                    <span class="formation-tag">ChatGPT</span>
                    <span class="formation-tag">Python</span>
                    <span class="formation-tag">OpenAI API</span>
                </div>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="width:100%;text-align:center;display:block;margin-top:auto;">
                    S'inscrire
                </a>
            </div>

            <div class="formation-card slide-up" style="animation-delay:.24s;">
                <div class="formation-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="formation-title">Cybersécurité & Hacking Éthique</div>
                <div class="formation-desc">Sécurité des systèmes, pentest, forensics et conformité — préparez la certification CEH.</div>
                <div class="formation-meta">
                    <span><i class="fas fa-clock"></i> 2 mois</span>
                    <span><i class="fas fa-users"></i> 12 places</span>
                    <span><i class="fas fa-star"></i> Avancé</span>
                </div>
                <div class="formation-tags">
                    <span class="formation-tag">Linux</span>
                    <span class="formation-tag">Kali</span>
                    <span class="formation-tag">OWASP</span>
                    <span class="formation-tag">Pentest</span>
                </div>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="width:100%;text-align:center;display:block;margin-top:auto;">
                    S'inscrire
                </a>
            </div>

            <div class="formation-card slide-up" style="animation-delay:.32s;">
                <div class="formation-icon"><i class="fas fa-sitemap"></i></div>
                <div class="formation-title">ERP / CRM — Gestion d'Entreprise</div>
                <div class="formation-desc">Odoo, GOVIBE ERP, gestion comptable et CRM pour managers et directeurs financiers.</div>
                <div class="formation-meta">
                    <span><i class="fas fa-clock"></i> 4 semaines</span>
                    <span><i class="fas fa-users"></i> 20 places</span>
                    <span><i class="fas fa-star"></i> Gestionnaires</span>
                </div>
                <div class="formation-tags">
                    <span class="formation-tag">Odoo</span>
                    <span class="formation-tag">Excel</span>
                    <span class="formation-tag">Comptabilité</span>
                </div>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="width:100%;text-align:center;display:block;margin-top:auto;">
                    S'inscrire
                </a>
            </div>

            <div class="formation-card slide-up" style="animation-delay:.4s;">
                <div class="formation-icon"><i class="fas fa-bullhorn"></i></div>
                <div class="formation-title">Marketing Digital & Réseaux Sociaux</div>
                <div class="formation-desc">Facebook Ads, SEO, email marketing et personal branding pour entrepreneurs et PME haïtiennes.</div>
                <div class="formation-meta">
                    <span><i class="fas fa-clock"></i> 5 semaines</span>
                    <span><i class="fas fa-users"></i> 30 places</span>
                    <span><i class="fas fa-star"></i> Débutant+</span>
                </div>
                <div class="formation-tags">
                    <span class="formation-tag">Facebook Ads</span>
                    <span class="formation-tag">SEO</span>
                    <span class="formation-tag">Canva</span>
                </div>
                <a href="{{ route('inscription.create') }}" class="gv-btn-prim" style="width:100%;text-align:center;display:block;margin-top:auto;">
                    S'inscrire
                </a>
            </div>

        </div>
    </div>
</section>

{{-- ── Programmes spéciaux ──────────────────────────── --}}
<section class="ac-programmes">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">Programmes Spéciaux</div>
            <h2 class="section-title">Au-delà des formations classiques</h2>
            <p class="section-sub centered">Des programmes d'accompagnement intensif pour accélérer votre croissance.</p>
        </div>
        <div class="programmes-grid">
            <div class="programme-card slide-up">
                <div class="prog-icon"><i class="fas fa-seedling"></i></div>
                <div>
                    <div class="prog-title">Programme d'Incubation</div>
                    <div class="prog-desc">12 semaines d'accompagnement intensif pour valider votre idée, construire votre MVP et pitcher devant des investisseurs. Mentorat, financement et réseau inclus.</div>
                    <a href="{{ route('inscription.create') }}" class="prog-link">
                        Postuler <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="programme-card slide-up" style="animation-delay:.1s;">
                <div class="prog-icon"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="prog-title">Crédit Digital PME</div>
                    <div class="prog-desc">Accès à des financements pour la digitalisation de votre PME — formation + crédit couplés pour maximiser le ROI de votre transformation numérique.</div>
                    <a href="{{ route('inscription.create') }}" class="prog-link">
                        En savoir plus <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="programme-card slide-up" style="animation-delay:.2s;">
                <div class="prog-icon"><i class="fas fa-briefcase"></i></div>
                <div>
                    <div class="prog-title">Bootcamp Emploi Tech</div>
                    <div class="prog-desc">Formation accélérée de 8 semaines orientée emploi — CV, portfolio GitHub, entretiens techniques et mise en relation avec des employeurs partenaires.</div>
                    <a href="{{ route('inscription.create') }}" class="prog-link">
                        Candidater <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="programme-card slide-up" style="animation-delay:.3s;">
                <div class="prog-icon"><i class="fas fa-globe-americas"></i></div>
                <div>
                    <div class="prog-title">Formation Entreprises</div>
                    <div class="prog-desc">Programmes sur-mesure pour entreprises — formations in-house pour vos équipes en digital, cybersécurité, ERP et leadership technologique.</div>
                    <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE,+je+veux+une+formation+pour+mon+entreprise" target="_blank" class="prog-link">
                        Contacter <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Comment ça marche ────────────────────────────── --}}
<section class="ac-how">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">Processus</div>
            <h2 class="section-title">Comment ça marche</h2>
            <p class="section-sub centered">De l'inscription à la certification — un parcours simple et structuré.</p>
        </div>
        <div class="how-steps">
            <div class="how-step slide-up">
                <div class="how-step-num">01</div>
                <h4>Choisir une formation</h4>
                <p>Parcourez notre catalogue et sélectionnez la formation qui correspond à vos objectifs.</p>
            </div>
            <div class="how-step slide-up" style="animation-delay:.1s;">
                <div class="how-step-num">02</div>
                <h4>S'inscrire en ligne</h4>
                <p>Remplissez le formulaire d'inscription et confirmez votre place via WhatsApp ou en personne.</p>
            </div>
            <div class="how-step slide-up" style="animation-delay:.2s;">
                <div class="how-step-num">03</div>
                <h4>Suivre les cours</h4>
                <p>Sessions en présentiel au Coworking GOVIBE ou en ligne — avec projets pratiques et suivi personnalisé.</p>
            </div>
            <div class="how-step slide-up" style="animation-delay:.3s;">
                <div class="how-step-num">04</div>
                <h4>Obtenir votre certificat</h4>
                <p>Réussissez votre projet final et recevez votre certificat GOVIBE Academy reconnu par les entreprises partenaires.</p>
            </div>
        </div>
    </div>
</section>

{{-- ── Formateurs ───────────────────────────────────── --}}
<section class="ac-instructors">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">L'Équipe Pédagogique</div>
            <h2 class="section-title">Apprenez des Meilleurs Experts</h2>
            <p class="section-sub centered">Des professionnels actifs dans leur domaine — pas que des théoriciens.</p>
        </div>
        <div class="instructors-grid">
            <div class="instructor-card slide-up">
                <div class="instructor-avatar">RF</div>
                <div class="instructor-name">Roosevelt Forestal</div>
                <div class="instructor-role">Fondateur & CEO — Expert IA & Stratégie Digitale</div>
                <div class="instructor-bio">15+ ans d'expérience en développement digital, intelligence artificielle et stratégie d'entreprise. Accompagne startups et grandes entreprises en Haïti, USA et Canada.</div>
            </div>
            <div class="instructor-card slide-up" style="animation-delay:.1s;">
                <div class="instructor-avatar">JL</div>
                <div class="instructor-name">Jean-Louis Pierre</div>
                <div class="instructor-role">Lead Développeur — Full Stack & Architecture Cloud</div>
                <div class="instructor-bio">Expert Laravel, React et AWS. Auteur de plusieurs applications SaaS déployées à l'international. Formateur certifié depuis 2021.</div>
            </div>
            <div class="instructor-card slide-up" style="animation-delay:.2s;">
                <div class="instructor-avatar">MC</div>
                <div class="instructor-name">Marie Claire Duvivier</div>
                <div class="instructor-role">Spécialiste Cybersécurité — Ethical Hacking</div>
                <div class="instructor-bio">Certifiée CEH et CISSP. Consultante en sécurité informatique pour banques et institutions haïtiennes. Formatrice GOVIBE depuis 2022.</div>
            </div>
        </div>
    </div>
</section>

{{-- ── Témoignages ─────────────────────────────────── --}}
<section class="ac-testimonials">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;">
        <div class="slide-up" style="text-align:center;">
            <div class="section-eyebrow">Témoignages</div>
            <h2 class="section-title">Ils ont transformé leur carrière</h2>
        </div>
        <div class="ac-testi-grid">
            <div class="ac-testi-card slide-up">
                <p>Avant GOVIBE Academy, je ne savais rien du code. Après 3 mois de formation Full Stack, j'ai décroché mon premier contrat freelance. La formation est très pratique et les formateurs sont toujours disponibles.</p>
                <div class="ac-testi-author">
                    <div class="at-avatar">KJ</div>
                    <div>
                        <div class="at-name">Kendy Joseph</div>
                        <div class="at-role">Développeur Web Freelance — Promo 2023</div>
                    </div>
                </div>
            </div>
            <div class="ac-testi-card slide-up" style="animation-delay:.1s;">
                <p>La formation IA de GOVIBE m'a permis d'automatiser 60% des tâches répétitives dans mon entreprise. En 6 semaines, j'ai vu un ROI concret. Je recommande à tous les entrepreneurs haïtiens.</p>
                <div class="ac-testi-author">
                    <div class="at-avatar">SB</div>
                    <div>
                        <div class="at-name">Sophia Bernard</div>
                        <div class="at-role">Directrice — Entreprise de Logistique, Haïti</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA ──────────────────────────────────────────── --}}
<section class="ac-cta">
    <div class="container" style="max-width:1200px;margin:0 auto;padding:0 1.5rem;position:relative;z-index:1;">
        <h2 class="slide-up">Votre avenir digital commence maintenant.</h2>
        <p class="slide-up">Rejoignez les 500+ professionnels qui ont transformé leur carrière avec GOVIBE Academy. Les prochaines sessions démarrent bientôt — les places sont limitées.</p>
        <div class="ac-cta-btns slide-up">
            <a href="{{ route('inscription.create') }}" class="btn-white">
                <i class="fas fa-pen-to-square"></i> S'inscrire maintenant
            </a>
            <a href="https://wa.me/50948174124?text=Bonjour+GOVIBE+Academy,+je+veux+m'inscrire+à+une+formation" target="_blank" class="btn-outline-white">
                <i class="fab fa-whatsapp"></i> Parler à un conseiller
            </a>
        </div>
    </div>
</section>

@endsection

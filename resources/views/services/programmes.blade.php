@extends('layouts.public')

@section('title', 'Nos Programmes — GOVIBE Innovation Hub')

@section('content')
<style>
.prog-hero {
    background: linear-gradient(135deg, #0a0f1e 0%, #1a1f2e 60%, #2a0a0a 100%);
    padding: 120px 0 80px;
    position: relative;
    overflow: hidden;
}
.prog-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 60% 60% at 80% 50%, rgba(220,38,38,.12) 0%, transparent 70%);
}
.prog-hero-inner {
    position: relative;
    z-index: 1;
}
.prog-hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(220,38,38,.15);
    border: 1px solid rgba(220,38,38,.35);
    color: #f87171;
    font-size: .8rem;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: 6px 16px;
    border-radius: 100px;
    margin-bottom: 24px;
}
.prog-hero h1 {
    font-size: clamp(2rem, 5vw, 3.2rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 20px;
}
.prog-hero h1 span { color: #DC2626; }
.prog-hero p {
    font-size: 1.1rem;
    color: rgba(255,255,255,.72);
    max-width: 600px;
    line-height: 1.7;
    margin-bottom: 36px;
}
.prog-stats {
    display: flex;
    gap: 40px;
    flex-wrap: wrap;
}
.prog-stat strong {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: #DC2626;
    line-height: 1;
}
.prog-stat span {
    font-size: .82rem;
    color: rgba(255,255,255,.55);
    margin-top: 4px;
    display: block;
}

/* Programmes grid */
.prog-section {
    padding: 80px 0;
    background: #fff;
}
.prog-section.alt { background: #f8f9fa; }
.dark .prog-section { background: #111827; }
.dark .prog-section.alt { background: #0f172a; }

.prog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 28px;
    margin-top: 48px;
}
.prog-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    overflow: hidden;
    transition: box-shadow .25s, transform .25s;
}
.dark .prog-card { background: #1e293b; border-color: #334155; }
.prog-card:hover {
    box-shadow: 0 12px 40px rgba(220,38,38,.12);
    transform: translateY(-4px);
}
.prog-card-header {
    padding: 28px 28px 20px;
    border-bottom: 1px solid #f3f4f6;
    position: relative;
}
.dark .prog-card-header { border-color: #334155; }
.prog-card-badge {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: 4px 12px;
    border-radius: 100px;
    margin-bottom: 16px;
    display: inline-block;
}
.badge-red { background: rgba(220,38,38,.1); color: #DC2626; }
.badge-blue { background: rgba(29,78,216,.1); color: #1D4ED8; }
.badge-gold { background: rgba(245,158,11,.1); color: #d97706; }
.badge-green { background: rgba(22,163,74,.1); color: #16A34A; }
.badge-purple { background: rgba(124,58,237,.1); color: #7c3aed; }

.prog-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: rgba(220,38,38,.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}
.prog-icon-box i { font-size: 1.4rem; color: #DC2626; }
.prog-card h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 8px;
}
.dark .prog-card h3 { color: #f1f5f9; }
.prog-card .sub {
    font-size: .85rem;
    color: #64748b;
    line-height: 1.5;
}
.dark .prog-card .sub { color: #94a3b8; }
.prog-card-body { padding: 20px 28px 28px; }
.prog-feat-list { list-style: none; padding: 0; margin: 0 0 24px; display: flex; flex-direction: column; gap: 10px; }
.prog-feat-list li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: .88rem;
    color: #374151;
    line-height: 1.4;
}
.dark .prog-feat-list li { color: #cbd5e1; }
.prog-feat-list li i { color: #DC2626; font-size: .9rem; flex-shrink: 0; margin-top: 2px; }
.prog-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    border-top: 1px solid #f3f4f6;
    padding-top: 16px;
    margin-bottom: 20px;
}
.dark .prog-meta { border-color: #334155; }
.prog-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: .8rem;
    color: #64748b;
}
.prog-meta-item i { color: #DC2626; font-size: .85rem; }

/* Bootcamp / Feature highlight */
.bootcamp-banner {
    background: linear-gradient(135deg, #0a0f1e 0%, #1a0505 100%);
    border: 1px solid rgba(220,38,38,.3);
    border-radius: 20px;
    padding: 48px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 32px;
    align-items: center;
    margin-top: 48px;
    position: relative;
    overflow: hidden;
}
.bootcamp-banner::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(220,38,38,.08);
}
.bootcamp-banner-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(220,38,38,.2);
    border: 1px solid rgba(220,38,38,.4);
    color: #f87171;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: 5px 14px;
    border-radius: 100px;
    margin-bottom: 16px;
}
.bootcamp-banner h3 {
    font-size: 1.6rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 12px;
}
.bootcamp-banner p {
    color: rgba(255,255,255,.65);
    font-size: .95rem;
    line-height: 1.6;
    max-width: 520px;
}
.bootcamp-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #DC2626, #b91c1c);
    color: #fff;
    font-weight: 700;
    font-size: .95rem;
    padding: 14px 28px;
    border-radius: 10px;
    text-decoration: none;
    white-space: nowrap;
    transition: opacity .2s, transform .2s;
}
.bootcamp-btn:hover { opacity: .9; transform: translateY(-2px); color: #fff; }

/* Process section */
.process-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 24px;
    margin-top: 48px;
    counter-reset: step;
}
.process-step {
    text-align: center;
    padding: 32px 24px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    position: relative;
}
.dark .process-step { background: #1e293b; border-color: #334155; }
.process-step-num {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #DC2626, #b91c1c);
    color: #fff;
    font-size: 1.1rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}
.process-step h4 {
    font-size: .95rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 8px;
}
.dark .process-step h4 { color: #f1f5f9; }
.process-step p { font-size: .85rem; color: #64748b; line-height: 1.5; }
.dark .process-step p { color: #94a3b8; }

/* CTA section */
.prog-cta {
    background: linear-gradient(135deg, #DC2626 0%, #991b1b 100%);
    padding: 80px 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.prog-cta::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 50% 80% at 50% 0%, rgba(255,255,255,.08) 0%, transparent 60%);
}
.prog-cta h2 {
    font-size: clamp(1.6rem, 4vw, 2.4rem);
    font-weight: 800;
    color: #fff;
    margin-bottom: 16px;
    position: relative;
}
.prog-cta p {
    color: rgba(255,255,255,.8);
    font-size: 1rem;
    margin-bottom: 32px;
    position: relative;
}
.prog-cta-btns { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; position: relative; }
.prog-cta-btn-wh {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    color: #DC2626;
    font-weight: 700;
    padding: 13px 28px;
    border-radius: 10px;
    text-decoration: none;
    font-size: .95rem;
    transition: opacity .2s, transform .2s;
}
.prog-cta-btn-wh:hover { opacity: .95; transform: translateY(-2px); color: #DC2626; }
.prog-cta-btn-out {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    border: 2px solid rgba(255,255,255,.6);
    color: #fff;
    font-weight: 600;
    padding: 11px 26px;
    border-radius: 10px;
    text-decoration: none;
    font-size: .95rem;
    transition: border-color .2s, background .2s;
}
.prog-cta-btn-out:hover { border-color: #fff; background: rgba(255,255,255,.1); color: #fff; }

@media (max-width: 900px) {
    .prog-hero { padding: 90px 0 60px; }
    .bootcamp-banner { grid-template-columns: 1fr; padding: 32px; }
}
@media (max-width: 768px) {
    .prog-grid { grid-template-columns: 1fr; }
    .prog-stats { gap: 24px; }
    .process-steps { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 576px) {
    .bootcamp-banner { padding: 24px; }
    .bootcamp-banner h3 { font-size: 1.3rem; }
    .process-steps { grid-template-columns: 1fr; }
    .prog-hero { padding: 70px 0 48px; }
}
</style>

<!-- HERO -->
<section class="prog-hero">
    <div class="gv-wrap prog-hero-inner">
        <div class="prog-hero-tag">
            <img src="{{ asset('images/govibe-icon.svg') }}" alt="" style="height:16px;">
            Nos Programmes
        </div>
        <h1>Former. Accélérer.<br><span>Transformer.</span></h1>
        <p>Des programmes intensifs, des bootcamps et des certifications conçus pour préparer les entrepreneurs et les professionnels haïtiens aux défis du numérique.</p>
        <div class="prog-stats">
            <div class="prog-stat">
                <strong>500+</strong>
                <span>Participants formés</span>
            </div>
            <div class="prog-stat">
                <strong>12</strong>
                <span>Programmes actifs</span>
            </div>
            <div class="prog-stat">
                <strong>30+</strong>
                <span>Formateurs experts</span>
            </div>
            <div class="prog-stat">
                <strong>85%</strong>
                <span>Taux de satisfaction</span>
            </div>
        </div>
    </div>
</section>

<!-- BOOTCAMP FEATURED -->
<section class="prog-section" style="padding-bottom: 0;">
    <div class="gv-wrap">
        <div class="gv-stag">À la une</div>
        <h2 class="section-title">Programme phare 2026</h2>

        <div class="bootcamp-banner slide-up">
            <div>
                <div class="bootcamp-banner-tag">
                    <i class="fas fa-fire"></i> Inscriptions ouvertes
                </div>
                <h3>Bootcamp IA & Innovation 2026</h3>
                <p>Un programme intensif de 12 semaines pour maîtriser l'intelligence artificielle, l'automatisation et les outils numériques qui redéfinissent le marché du travail. Rejoignez la prochaine vague d'innovateurs haïtiens.</p>
                <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;">
                    <div class="prog-meta-item">
                        <i class="fas fa-calendar"></i> 12 semaines
                    </div>
                    <div class="prog-meta-item">
                        <i class="fas fa-users"></i> 30 places
                    </div>
                    <div class="prog-meta-item">
                        <i class="fas fa-map-marker-alt"></i> Présentiel + En ligne
                    </div>
                    <div class="prog-meta-item">
                        <i class="fas fa-certificate"></i> Certificat inclus
                    </div>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;align-items:flex-start;">
                <a href="{{ route('bootcamp.landing') }}" class="bootcamp-btn">
                    <i class="fas fa-rocket"></i> S'inscrire maintenant
                </a>
                <a href="{{ route('home') }}#reservation" style="color:rgba(255,255,255,.6);font-size:.85rem;text-decoration:none;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-info-circle"></i> Demander plus d'infos
                </a>
            </div>
        </div>
    </div>
</section>

<!-- PROGRAMMES GRID -->
<section class="prog-section">
    <div class="gv-wrap">
        <div class="gv-stag">Catalogue</div>
        <h2 class="section-title">Tous nos programmes</h2>
        <p class="section-subtitle">Des formations courtes aux parcours longs, adaptés à votre niveau et à vos objectifs.</p>

        <div class="prog-grid">

            <!-- Digital Skills -->
            <div class="prog-card slide-up">
                <div class="prog-card-header">
                    <div class="prog-card-badge badge-red">Formation courte</div>
                    <div class="prog-icon-box">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <h3>Compétences Numériques Essentielles</h3>
                    <p class="sub">Maîtrisez les outils du quotidien numérique : bureautique avancée, cloud, cybersécurité de base et productivité.</p>
                </div>
                <div class="prog-card-body">
                    <ul class="prog-feat-list">
                        <li><i class="fas fa-check-circle"></i> Suite Google & Microsoft 365</li>
                        <li><i class="fas fa-check-circle"></i> Gestion de fichiers & cloud</li>
                        <li><i class="fas fa-check-circle"></i> Sécurité numérique de base</li>
                        <li><i class="fas fa-check-circle"></i> Communication professionnelle en ligne</li>
                    </ul>
                    <div class="prog-meta">
                        <div class="prog-meta-item"><i class="fas fa-clock"></i> 4 semaines</div>
                        <div class="prog-meta-item"><i class="fas fa-layer-group"></i> Débutant</div>
                        <div class="prog-meta-item"><i class="fas fa-certificate"></i> Attestation</div>
                    </div>
                    <a href="{{ route('home') }}#reservation" class="gv-btn-prim" style="width:100%;text-align:center;display:block;">
                        S'inscrire <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Entrepreneurship -->
            <div class="prog-card slide-up">
                <div class="prog-card-header">
                    <div class="prog-card-badge badge-blue">Programme complet</div>
                    <div class="prog-icon-box" style="background:rgba(29,78,216,.1);">
                        <i class="fas fa-lightbulb" style="color:#1D4ED8;"></i>
                    </div>
                    <h3>Entrepreneuriat & Innovation</h3>
                    <p class="sub">Transformez votre idée en entreprise viable. Idéation, validation marché, business model, pitch et financement.</p>
                </div>
                <div class="prog-card-body">
                    <ul class="prog-feat-list">
                        <li><i class="fas fa-check-circle"></i> Design thinking & idéation</li>
                        <li><i class="fas fa-check-circle"></i> Business Model Canvas</li>
                        <li><i class="fas fa-check-circle"></i> Étude de marché & validation</li>
                        <li><i class="fas fa-check-circle"></i> Pitch et recherche de financement</li>
                        <li><i class="fas fa-check-circle"></i> Accompagnement post-formation</li>
                    </ul>
                    <div class="prog-meta">
                        <div class="prog-meta-item"><i class="fas fa-clock"></i> 8 semaines</div>
                        <div class="prog-meta-item"><i class="fas fa-layer-group"></i> Intermédiaire</div>
                        <div class="prog-meta-item"><i class="fas fa-certificate"></i> Certification</div>
                    </div>
                    <a href="{{ route('home') }}#reservation" class="gv-btn-prim" style="width:100%;text-align:center;display:block;">
                        S'inscrire <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Digital Marketing -->
            <div class="prog-card slide-up">
                <div class="prog-card-header">
                    <div class="prog-card-badge badge-gold">Formation intensive</div>
                    <div class="prog-icon-box" style="background:rgba(245,158,11,.1);">
                        <i class="fas fa-bullhorn" style="color:#d97706;"></i>
                    </div>
                    <h3>Marketing Digital & Réseaux Sociaux</h3>
                    <p class="sub">Stratégies de contenu, publicité en ligne, SEO et analytics pour développer votre présence digitale.</p>
                </div>
                <div class="prog-card-body">
                    <ul class="prog-feat-list">
                        <li><i class="fas fa-check-circle"></i> Stratégie de contenu digital</li>
                        <li><i class="fas fa-check-circle"></i> Facebook & Instagram Ads</li>
                        <li><i class="fas fa-check-circle"></i> SEO & référencement web</li>
                        <li><i class="fas fa-check-circle"></i> Email marketing & automation</li>
                        <li><i class="fas fa-check-circle"></i> Analyse de performance (Google Analytics)</li>
                    </ul>
                    <div class="prog-meta">
                        <div class="prog-meta-item"><i class="fas fa-clock"></i> 6 semaines</div>
                        <div class="prog-meta-item"><i class="fas fa-layer-group"></i> Intermédiaire</div>
                        <div class="prog-meta-item"><i class="fas fa-certificate"></i> Certification</div>
                    </div>
                    <a href="{{ route('home') }}#reservation" class="gv-btn-prim" style="width:100%;text-align:center;display:block;">
                        S'inscrire <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Développement Web/Mobile -->
            <div class="prog-card slide-up">
                <div class="prog-card-header">
                    <div class="prog-card-badge badge-green">Bootcamp technique</div>
                    <div class="prog-icon-box" style="background:rgba(22,163,74,.1);">
                        <i class="fas fa-code" style="color:#16A34A;"></i>
                    </div>
                    <h3>Développement Web & Mobile</h3>
                    <p class="sub">Apprenez à développer des applications web et mobiles modernes avec les technologies les plus demandées.</p>
                </div>
                <div class="prog-card-body">
                    <ul class="prog-feat-list">
                        <li><i class="fas fa-check-circle"></i> HTML, CSS, JavaScript moderne</li>
                        <li><i class="fas fa-check-circle"></i> React / Vue.js & frameworks</li>
                        <li><i class="fas fa-check-circle"></i> Backend API (Node.js / Laravel)</li>
                        <li><i class="fas fa-check-circle"></i> Développement mobile (React Native)</li>
                        <li><i class="fas fa-check-circle"></i> Déploiement & DevOps de base</li>
                    </ul>
                    <div class="prog-meta">
                        <div class="prog-meta-item"><i class="fas fa-clock"></i> 16 semaines</div>
                        <div class="prog-meta-item"><i class="fas fa-layer-group"></i> Débutant → Avancé</div>
                        <div class="prog-meta-item"><i class="fas fa-certificate"></i> Certification</div>
                    </div>
                    <a href="{{ route('home') }}#reservation" class="gv-btn-prim" style="width:100%;text-align:center;display:block;">
                        S'inscrire <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- IA & Data -->
            <div class="prog-card slide-up">
                <div class="prog-card-header">
                    <div class="prog-card-badge badge-purple">Avancé</div>
                    <div class="prog-icon-box" style="background:rgba(124,58,237,.1);">
                        <i class="fas fa-brain" style="color:#7c3aed;"></i>
                    </div>
                    <h3>Intelligence Artificielle & Data Science</h3>
                    <p class="sub">Maîtrisez les outils d'IA, le machine learning et l'analyse de données pour prendre des décisions éclairées.</p>
                </div>
                <div class="prog-card-body">
                    <ul class="prog-feat-list">
                        <li><i class="fas fa-check-circle"></i> Introduction au Machine Learning</li>
                        <li><i class="fas fa-check-circle"></i> Python pour la data science</li>
                        <li><i class="fas fa-check-circle"></i> Outils IA générateurs (ChatGPT, Claude, etc.)</li>
                        <li><i class="fas fa-check-circle"></i> Visualisation de données</li>
                        <li><i class="fas fa-check-circle"></i> Projets pratiques sectoriels</li>
                    </ul>
                    <div class="prog-meta">
                        <div class="prog-meta-item"><i class="fas fa-clock"></i> 12 semaines</div>
                        <div class="prog-meta-item"><i class="fas fa-layer-group"></i> Intermédiaire</div>
                        <div class="prog-meta-item"><i class="fas fa-certificate"></i> Certification</div>
                    </div>
                    <a href="{{ route('home') }}#reservation" class="gv-btn-prim" style="width:100%;text-align:center;display:block;">
                        S'inscrire <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Gestion de Projet -->
            <div class="prog-card slide-up">
                <div class="prog-card-header">
                    <div class="prog-card-badge badge-red">Certification pro</div>
                    <div class="prog-icon-box">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Gestion de Projet Digital</h3>
                    <p class="sub">Méthodologies agiles, outils de collaboration et leadership pour piloter des projets numériques avec succès.</p>
                </div>
                <div class="prog-card-body">
                    <ul class="prog-feat-list">
                        <li><i class="fas fa-check-circle"></i> Scrum & Agile (Scrum Master)</li>
                        <li><i class="fas fa-check-circle"></i> Trello, Notion, Jira & Asana</li>
                        <li><i class="fas fa-check-circle"></i> Gestion budgétaire de projets</li>
                        <li><i class="fas fa-check-circle"></i> Communication & leadership</li>
                    </ul>
                    <div class="prog-meta">
                        <div class="prog-meta-item"><i class="fas fa-clock"></i> 5 semaines</div>
                        <div class="prog-meta-item"><i class="fas fa-layer-group"></i> Intermédiaire</div>
                        <div class="prog-meta-item"><i class="fas fa-certificate"></i> Certification</div>
                    </div>
                    <a href="{{ route('home') }}#reservation" class="gv-btn-prim" style="width:100%;text-align:center;display:block;">
                        S'inscrire <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- PROCESSUS D'INSCRIPTION -->
<section class="prog-section alt">
    <div class="gv-wrap">
        <div class="gv-stag">Comment ça marche</div>
        <h2 class="section-title">Processus d'inscription</h2>
        <p class="section-subtitle">Simple, rapide et transparent. Du choix du programme à votre première session.</p>

        <div class="process-steps">
            <div class="process-step slide-up">
                <div class="process-step-num">1</div>
                <h4>Choisissez votre programme</h4>
                <p>Parcourez nos formations et sélectionnez celle qui correspond à vos objectifs et votre niveau actuel.</p>
            </div>
            <div class="process-step slide-up">
                <div class="process-step-num">2</div>
                <h4>Remplissez le formulaire</h4>
                <p>Soumettez votre demande d'inscription en ligne avec vos informations de contact et votre motivation.</p>
            </div>
            <div class="process-step slide-up">
                <div class="process-step-num">3</div>
                <h4>Entretien de sélection</h4>
                <p>Notre équipe vous contacte sous 48h pour un entretien rapide et vous confirme votre place.</p>
            </div>
            <div class="process-step slide-up">
                <div class="process-step-num">4</div>
                <h4>Commencez votre formation</h4>
                <p>Rejoignez votre cohorte, accédez aux ressources et démarrez votre parcours de transformation.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="prog-cta">
    <div class="gv-wrap">
        <div style="display:flex;justify-content:center;margin-bottom:20px;">
            <img src="{{ asset('images/govibe-icon.svg') }}" alt="GOVIBE" style="height:48px;opacity:.9;">
        </div>
        <h2>Prêt à investir dans votre avenir ?</h2>
        <p>Rejoignez les centaines d'entrepreneurs et de professionnels qui ont transformé leur carrière avec GOVIBE.</p>
        <div class="prog-cta-btns">
            <a href="{{ route('home') }}#reservation" class="prog-cta-btn-wh">
                <i class="fas fa-paper-plane"></i> Soumettre ma candidature
            </a>
            <a href="{{ route('partenaires') }}" class="prog-cta-btn-out">
                <i class="fas fa-handshake"></i> Devenir partenaire
            </a>
        </div>
    </div>
</section>

@endsection

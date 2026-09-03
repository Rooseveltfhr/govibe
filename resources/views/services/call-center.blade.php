@extends('layouts.public')

@section('title', 'GOVIBE Call Center — Support 24/7 Haïti')
@section('description', 'GOVIBE Call Center : réception d\'appels, service client externalisé, support technique et secrétariat à distance 24/7 depuis Haïti.')

@section('head')
<style>
:root { --gv-primary: #DC2626; }

.cc-hero {
    background: linear-gradient(135deg,#0a0000 0%,#1a0004 50%,#06060f 100%);
    min-height: 78vh;
    display: flex; align-items: center;
    position: relative; overflow: hidden; padding: 120px 0 80px;
}
.cc-hero::before {
    content:''; position:absolute; inset:0;
    background:
        radial-gradient(ellipse 60% 50% at 80% 30%, rgba(220,38,38,.18) 0%, transparent 70%),
        radial-gradient(ellipse 40% 40% at 20% 70%, rgba(220,38,38,.10) 0%, transparent 60%);
    pointer-events:none;
}
.cc-grid-bg {
    position:absolute; inset:0;
    background-image:
        linear-gradient(rgba(220,38,38,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(220,38,38,.04) 1px, transparent 1px);
    background-size:50px 50px; pointer-events:none;
}
.cc-tag {
    display:inline-flex; align-items:center; gap:.4rem;
    background:rgba(220,38,38,.15); border:1px solid rgba(220,38,38,.35);
    color:#f87171; font-size:.78rem; font-weight:700;
    letter-spacing:.1em; text-transform:uppercase;
    padding:.35rem 1rem; border-radius:2rem; margin-bottom:1.5rem;
}
.cc-hero h1 {
    font-family:'Anton',sans-serif; font-size:clamp(2.4rem,7vw,4.8rem);
    color:#fff; line-height:1.05; margin-bottom:1.2rem;
}
.cc-hero h1 span { color:#DC2626; }
.cc-hero p.lead {
    color:rgba(255,255,255,.72); font-size:1.1rem;
    max-width:540px; line-height:1.75; margin-bottom:2.2rem;
}
.cc-btns { display:flex; gap:1rem; flex-wrap:wrap; }
.cc-stat-row {
    display:flex; gap:2.5rem; flex-wrap:wrap; margin-top:3rem;
    padding-top:2rem; border-top:1px solid rgba(255,255,255,.08);
}
.cc-stat { text-align:center; }
.cc-stat-n { font-family:'Barlow Condensed',sans-serif; font-size:2.2rem; font-weight:900; color:#DC2626; line-height:1; }
.cc-stat-l { font-size:.72rem; color:rgba(255,255,255,.45); text-transform:uppercase; letter-spacing:1.5px; margin-top:3px; }

/* Service cards */
.cc-card {
    background:#fff; border-radius:20px; padding:2rem;
    box-shadow:0 8px 24px rgba(0,0,0,.05); border:1px solid #f1f5f9;
    transition:all .3s;
}
.cc-card:hover { transform:translateY(-6px); box-shadow:0 20px 40px rgba(220,38,38,.1); border-color:rgba(220,38,38,.2); }
.cc-card .icon-box {
    width:56px; height:56px; background:rgba(220,38,38,.1); border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    margin-bottom:1.2rem; font-size:1.6rem; color:#DC2626;
}
.cc-card h3 { font-family:'Anton',sans-serif; font-size:1.3rem; color:#0f172a; margin-bottom:.5rem; }
.cc-card p { color:#64748b; font-size:.9rem; line-height:1.65; }

/* Process steps */
.cc-step {
    display:flex; gap:1.2rem; align-items:flex-start; padding:1.5rem;
    background:#fff; border-radius:16px; border:1px solid #f1f5f9;
    box-shadow:0 4px 12px rgba(0,0,0,.04);
}
.cc-step-num {
    width:44px; height:44px; min-width:44px;
    background:linear-gradient(135deg,#DC2626,#991b1b);
    border-radius:50%; display:flex; align-items:center; justify-content:center;
    font-family:'Barlow Condensed',sans-serif; font-weight:900; font-size:1.2rem; color:#fff;
}
.cc-step h4 { font-family:'Anton',sans-serif; font-size:1rem; color:#0f172a; margin-bottom:.25rem; }
.cc-step p { color:#64748b; font-size:.87rem; line-height:1.6; }

/* Pricing */
.cc-plan {
    background:#fff; border-radius:20px; padding:2rem;
    border:2px solid #f1f5f9; transition:all .3s; text-align:center;
}
.cc-plan.featured {
    border-color:#DC2626;
    box-shadow:0 20px 40px rgba(220,38,38,.15);
}
.cc-plan h4 { font-family:'Anton',sans-serif; font-size:1.2rem; color:#0f172a; margin-bottom:.3rem; }
.cc-plan .price { font-family:'Barlow Condensed',sans-serif; font-size:2.8rem; font-weight:900; color:#DC2626; line-height:1; margin:.5rem 0; }
.cc-plan .price span { font-size:1rem; color:#94a3b8; }
.cc-plan ul { list-style:none; text-align:left; margin:1rem 0; padding:0; display:flex; flex-direction:column; gap:.5rem; }
.cc-plan ul li { display:flex; align-items:center; gap:.5rem; font-size:.88rem; color:#475569; }
.cc-plan ul li i { color:#DC2626; font-size:.75rem; flex-shrink:0; }

@media(max-width:768px) {
    .cc-hero { padding:100px 1.2rem 60px; }
    .cc-btns { flex-direction:column; }
    .cc-btns a { width:100%; justify-content:center; }
    .cc-stat-row { gap:1.5rem; }
}
</style>
@endsection

@section('content')

{{-- HERO --}}
<section class="cc-hero">
    <div class="cc-grid-bg"></div>
    <div class="gv-wrap" style="position:relative;z-index:10;width:100%;">
        <div class="cc-tag">
            <img src="{{ asset('images/govibe-icon.svg') }}" alt="" style="height:16px;">
            <i class="fas fa-headset"></i> GOVIBE Call Center
        </div>
        <h1>Support Client<br><span>Professionnel 24/7</span><br>depuis Haïti</h1>
        <p class="lead">Externalisez votre service client à une équipe haïtienne formée, motivée et disponible. Réception d'appels, support technique, secrétariat à distance — nous sommes la voix de votre entreprise.</p>
        <div class="cc-btns">
            <a href="https://1207.3cx.cloud/supporttechnical" target="_blank" rel="noopener" class="gv-btn-prim">
                <i class="fas fa-phone"></i> Appeler maintenant
            </a>
            <a href="{{ route('home') }}#reservation" class="gv-btn-outline">
                <i class="fas fa-calendar-alt"></i> Demander un devis
            </a>
        </div>
        <div class="cc-stat-row">
            <div class="cc-stat"><div class="cc-stat-n">24/7</div><div class="cc-stat-l">Disponibilité</div></div>
            <div class="cc-stat"><div class="cc-stat-n">+1000</div><div class="cc-stat-l">Appels traités</div></div>
            <div class="cc-stat"><div class="cc-stat-n">36</div><div class="cc-stat-l">Agents formés</div></div>
            <div class="cc-stat"><div class="cc-stat-n">&lt;30s</div><div class="cc-stat-l">Temps de réponse</div></div>
        </div>
    </div>
</section>

{{-- SERVICES --}}
<section class="gv-section-light">
    <div class="gv-wrap">
        <div class="slide-up" style="text-align:center;margin-bottom:3rem;">
            <span class="gv-stag"><i class="fas fa-cogs"></i> NOS SERVICES CALL CENTER</span>
            <h2 class="gv-h2">Tout ce dont votre entreprise<br><span style="color:#DC2626;">a besoin</span></h2>
            <p class="gv-sub" style="margin:0 auto;text-align:center;">De la réception d'appels à la gestion de campagnes, nous couvrons tout votre service client.</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;">
            <div class="cc-card slide-up">
                <div class="icon-box"><i class="fas fa-phone-volume"></i></div>
                <h3>Réception d'Appels</h3>
                <p>Répondre à vos clients en votre nom. Accueil professionnel, transfert d'appels, prise de messages et gestion des urgences.</p>
            </div>
            <div class="cc-card slide-up delay-1">
                <div class="icon-box"><i class="fas fa-headset"></i></div>
                <h3>Support Technique</h3>
                <p>Résolution de problèmes techniques de niveau 1 et 2. FAQ, guides, assistance à distance pour vos clients.</p>
            </div>
            <div class="cc-card slide-up delay-2">
                <div class="icon-box"><i class="fas fa-calendar-check"></i></div>
                <h3>Prise de Rendez-vous</h3>
                <p>Gestion de votre agenda, confirmation et rappel de rendez-vous, coordination avec votre équipe.</p>
            </div>
            <div class="cc-card slide-up">
                <div class="icon-box"><i class="fas fa-bullhorn"></i></div>
                <h3>Campagnes Outbound</h3>
                <p>Prospection téléphonique, enquêtes de satisfaction, relances clients, campagnes marketing.</p>
            </div>
            <div class="cc-card slide-up delay-1">
                <div class="icon-box"><i class="fas fa-user-tie"></i></div>
                <h3>Secrétariat à Distance</h3>
                <p>Gestion de courriers, rédaction de documents, filtrage des appels et support administratif complet.</p>
            </div>
            <div class="cc-card slide-up delay-2">
                <div class="icon-box"><i class="fas fa-chart-bar"></i></div>
                <h3>Reporting & Analytics</h3>
                <p>Rapports hebdomadaires détaillés, statistiques d'appels, taux de satisfaction et recommandations.</p>
            </div>
        </div>
    </div>
</section>

{{-- PROCESSUS --}}
<section class="gv-section-gray">
    <div class="gv-wrap">
        <div class="slide-up" style="text-align:center;margin-bottom:3rem;">
            <span class="gv-stag"><i class="fas fa-list-ol"></i> COMMENT ÇA MARCHE</span>
            <h2 class="gv-h2">Démarrez en <span style="color:#DC2626;">4 étapes</span></h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.2rem;">
            <div class="cc-step slide-up">
                <div class="cc-step-num">1</div>
                <div>
                    <h4>Consultation gratuite</h4>
                    <p>Analysons ensemble vos besoins, volumes d'appels et objectifs de service client.</p>
                </div>
            </div>
            <div class="cc-step slide-up delay-1">
                <div class="cc-step-num">2</div>
                <div>
                    <h4>Formation de l'équipe</h4>
                    <p>Nos agents apprennent votre marque, vos produits et vos scripts en 48–72h.</p>
                </div>
            </div>
            <div class="cc-step slide-up delay-2">
                <div class="cc-step-num">3</div>
                <div>
                    <h4>Mise en service</h4>
                    <p>Déploiement via 3CX, transfert de numéros ou numéro dédié. Zéro interruption.</p>
                </div>
            </div>
            <div class="cc-step slide-up delay-3">
                <div class="cc-step-num">4</div>
                <div>
                    <h4>Suivi & optimisation</h4>
                    <p>Rapports réguliers, ajustements continus, réunions mensuelles de performance.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- PLANS --}}
<section class="gv-section-light">
    <div class="gv-wrap">
        <div class="slide-up" style="text-align:center;margin-bottom:3rem;">
            <span class="gv-stag"><i class="fas fa-tags"></i> TARIFS</span>
            <h2 class="gv-h2">Plans <span style="color:#DC2626;">Call Center</span></h2>
            <p class="gv-sub" style="margin:0 auto;text-align:center;">Tous les plans incluent formation, reporting et support dédié.</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;max-width:900px;margin:0 auto;">
            <div class="cc-plan slide-up">
                <span style="font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;">Starter</span>
                <h4>Essentiel</h4>
                <div class="price">$299<span>/mois</span></div>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Jusqu'à 500 appels/mois</li>
                    <li><i class="fas fa-check-circle"></i> 1 agent dédié</li>
                    <li><i class="fas fa-check-circle"></i> Horaires bureau (8h–18h)</li>
                    <li><i class="fas fa-check-circle"></i> Rapport hebdomadaire</li>
                    <li><i class="fas fa-check-circle"></i> Numéro dédié inclus</li>
                </ul>
                <a href="{{ route('home') }}#reservation" class="gv-btn-outline" style="width:100%;justify-content:center;margin-top:.5rem;">Démarrer</a>
            </div>
            <div class="cc-plan featured slide-up delay-1">
                <span style="font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#DC2626;">⭐ Populaire</span>
                <h4>Professionnel</h4>
                <div class="price">$599<span>/mois</span></div>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Jusqu'à 2000 appels/mois</li>
                    <li><i class="fas fa-check-circle"></i> 2–3 agents</li>
                    <li><i class="fas fa-check-circle"></i> Disponibilité 24/7</li>
                    <li><i class="fas fa-check-circle"></i> Support multicanal (tél+WA)</li>
                    <li><i class="fas fa-check-circle"></i> CRM intégré</li>
                    <li><i class="fas fa-check-circle"></i> Rapport en temps réel</li>
                </ul>
                <a href="{{ route('home') }}#reservation" class="gv-btn-prim" style="width:100%;justify-content:center;margin-top:.5rem;">Choisir ce plan</a>
            </div>
            <div class="cc-plan slide-up delay-2">
                <span style="font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;">Enterprise</span>
                <h4>Sur Mesure</h4>
                <div class="price" style="font-size:2rem;">Sur devis</div>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Volume illimité</li>
                    <li><i class="fas fa-check-circle"></i> Équipe dédiée</li>
                    <li><i class="fas fa-check-circle"></i> SLA garanti</li>
                    <li><i class="fas fa-check-circle"></i> Intégration CRM/ERP</li>
                    <li><i class="fas fa-check-circle"></i> Manager de compte dédié</li>
                </ul>
                <a href="{{ route('home') }}#reservation" class="gv-btn-outline" style="width:100%;justify-content:center;margin-top:.5rem;">Nous contacter</a>
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section style="background:linear-gradient(135deg,#0a0000,#1a0004);padding:70px 2rem;text-align:center;">
    <div class="gv-wrap">
        <div class="slide-up">
            <img src="{{ asset('images/govibe-icon.svg') }}" alt="" style="height:52px;margin:0 auto 1.2rem;display:block;">
            <h2 style="font-family:'Anton',sans-serif;font-size:clamp(1.8rem,4vw,2.8rem);color:#fff;margin-bottom:1rem;">
                Prêt à externaliser votre service client ?
            </h2>
            <p style="color:rgba(255,255,255,.65);max-width:520px;margin:0 auto 2rem;line-height:1.75;">
                Rejoignez les entreprises qui font confiance au Call Center GOVIBE pour leur relation client.
            </p>
            <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
                <a href="https://1207.3cx.cloud/supporttechnical" target="_blank" rel="noopener" class="gv-btn-prim">
                    <i class="fas fa-phone"></i> Appeler maintenant
                </a>
                <a href="{{ route('home') }}#reservation" class="gv-btn-outline" style="color:#fff;border-color:rgba(255,255,255,.3);">
                    <i class="fas fa-envelope"></i> Envoyer une demande
                </a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('scripts')
<script>
document.querySelectorAll('.slide-up').forEach(el => {
    const obs = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); } });
    }, { threshold: 0.1 });
    obs.observe(el);
});
</script>
@endsection

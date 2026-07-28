@extends('layouts.public')

@section('title', 'À propos — GOVIBE Innovation Hub')
@section('description', 'GOVIBE STARTUP LLC — Fondé en 2020 par Roosevelt Forestal. Écosystème d\'innovation enregistré aux USA (IRS) et en Haïti (MCI). 36 experts, +1000 clients.')

@section('head')
<style>
  .about-hero-stat { text-align:center; }
  .about-hero-stat .num { font-family:'Anton',sans-serif; font-size:2.5rem; color:#DC2626; line-height:1; }
  .about-hero-stat .lbl { font-size:.78rem; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:.15em; margin-top:.3rem; }
  .timeline-item { display:flex; gap:1.5rem; position:relative; padding-bottom:2.5rem; }
  .timeline-item:not(:last-child)::after { content:''; position:absolute; left:19px; top:40px; bottom:0; width:2px; background:linear-gradient(to bottom,#DC2626,rgba(220,38,38,.1)); }
  .timeline-dot { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,#DC2626,#991b1b); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:#fff; font-size:.9rem; box-shadow:0 0 20px rgba(220,38,38,.4); }
  .timeline-content h4 { font-family:'Anton',sans-serif; font-size:1.1rem; color:#0f172a; margin-bottom:.3rem; }
  .timeline-content p { color:#64748b; font-size:.9rem; line-height:1.65; }
  .timeline-year { font-size:.78rem; font-weight:700; color:#DC2626; letter-spacing:.1em; margin-bottom:.2rem; }
  .team-card { background:#fff; border-radius:16px; padding:1.5rem; box-shadow:0 4px 20px rgba(0,0,0,.06); border:1px solid #f1f5f9; text-align:center; transition:all .3s; }
  .team-card:hover { transform:translateY(-4px); box-shadow:0 16px 40px rgba(220,38,38,.1); border-color:rgba(220,38,38,.2); }
  .team-avatar { width:80px; height:80px; border-radius:50%; margin:0 auto 1rem; display:flex; align-items:center; justify-content:center; font-family:'Anton',sans-serif; font-size:1.8rem; color:#fff; }
  .client-badge { display:inline-flex; align-items:center; gap:.5rem; padding:.6rem 1.2rem; border-radius:50px; background:#f8fafc; border:1px solid #e2e8f0; font-family:'Anton',sans-serif; font-size:.9rem; color:#475569; transition:all .3s; cursor:default; }
  .client-badge:hover { background:rgba(220,38,38,.05); border-color:rgba(220,38,38,.25); color:#DC2626; transform:translateY(-2px); }
  .legal-card { background:#fff; border-radius:16px; padding:2rem; box-shadow:0 8px 30px rgba(0,0,0,.06); border:1px solid #f1f5f9; border-left:4px solid #DC2626; transition:all .3s; }
  .legal-card:hover { box-shadow:0 12px 40px rgba(220,38,38,.1); }
  .legal-card h3 { font-family:'Anton',sans-serif; font-size:1.3rem; color:#0f172a; margin-bottom:.3rem; }
  .value-card { background:#fff; border-radius:16px; padding:1.8rem; box-shadow:0 4px 20px rgba(0,0,0,.04); border:1px solid #f1f5f9; transition:all .3s; }
  .value-card:hover { transform:translateY(-4px); box-shadow:0 16px 40px rgba(220,38,38,.08); border-color:rgba(220,38,38,.2); }
  .value-card h3 { font-family:'Anton',sans-serif; font-size:1.15rem; color:#0f172a; margin:.75rem 0 .5rem; }
</style>
@endsection

@section('content')

{{-- PAGE HERO --}}
<div class="page-hero">
  <div class="page-hero-inner">
    <div class="page-breadcrumb">
      <a href="{{ route('home') }}">Accueil</a>
      <i class="fas fa-chevron-right fa-xs"></i>
      <span>À propos</span>
    </div>
    <span class="gv-stag" style="color:#DC2626;background:rgba(220,38,38,.1);border-color:rgba(220,38,38,.25);">
      <i class="fas fa-info-circle"></i> GOVIBE INNOVATION HUB
    </span>
    <h1>L'Écosystème qui<br><span style="color:#DC2626;">Transforme Haïti</span></h1>
    <p>Fondé en 2020 par Roosevelt Forestal — Enregistré aux USA (IRS) et en Haïti (MCI). Une vision internationale, un ancrage haïtien.</p>
    <div style="display:flex;justify-content:center;gap:3rem;flex-wrap:wrap;margin-top:2rem;">
      <div class="about-hero-stat"><div class="num">2020</div><div class="lbl">Année de Fondation</div></div>
      <div class="about-hero-stat"><div class="num">36</div><div class="lbl">Experts Dévoués</div></div>
      <div class="about-hero-stat"><div class="num">+1000</div><div class="lbl">Clients Servis</div></div>
      <div class="about-hero-stat"><div class="num">+9</div><div class="lbl">Pays d'Impact</div></div>
    </div>
  </div>
</div>

{{-- MISSION / VISION / HISTOIRE --}}
<section id="mission" style="padding:80px 2rem;background:#fff;">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center;margin-bottom:3.5rem;">
      <span class="gv-stag"><i class="fas fa-bullseye"></i> QUI SOMMES-NOUS</span>
      <h2 class="gv-h2">GOVIBE — <span style="color:#DC2626;">L'Écosystème</span> d'Innovation en Haïti</h2>
      <p class="gv-sub" style="max-width:700px;margin:0 auto;text-align:center;">
        Plus qu'un espace de travail — une plateforme intégrée de croissance pour entrepreneurs, startups et organisations.
      </p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:2rem;">
      <div class="value-card slide-up">
        <div style="width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#DC2626,#991b1b);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;">
          <i class="fas fa-eye"></i>
        </div>
        <h3 id="vision">Notre Vision</h3>
        <p style="color:#64748b;line-height:1.75;font-size:.92rem;">
          Être le principal écosystème d'innovation dans la Caraïbe et dans la diaspora haïtienne, en offrant une infrastructure complète qui permet à chaque entrepreneur de réaliser son plein potentiel.
        </p>
      </div>
      <div class="value-card slide-up delay-1">
        <div style="width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#10B981,#059669);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;">
          <i class="fas fa-rocket"></i>
        </div>
        <h3>Notre Mission</h3>
        <p style="color:#64748b;line-height:1.75;font-size:.92rem;">
          Construire une infrastructure physique, numérique et financière robuste qui donne aux entrepreneurs haïtiens accès aux outils, aux financements et aux réseaux dont ils ont besoin pour croître et réussir.
        </p>
      </div>
      <div class="value-card slide-up delay-2">
        <div style="width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#2563EB,#1e40af);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;">
          <i class="fas fa-star"></i>
        </div>
        <h3>Nos Valeurs</h3>
        <p style="color:#64748b;line-height:1.75;font-size:.92rem;">
          Innovation, Intégrité, Impact, Inclusion. Nous croyons que chaque haïtien mérite accès aux mêmes opportunités technologiques et économiques qu'ailleurs dans le monde.
        </p>
      </div>
    </div>
  </div>
</section>

{{-- HISTOIRE & TIMELINE --}}
<section id="histoire" style="padding:80px 2rem;background:linear-gradient(135deg,#f8fafc,#fff);">
  <div class="gv-wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:start;">
      <div class="slide-up">
        <span class="gv-stag"><i class="fas fa-history"></i> NOTRE HISTOIRE</span>
        <h2 class="gv-h2">Une décennie de<br><span style="color:#DC2626;">transformation digitale</span></h2>
        <p style="color:#64748b;line-height:1.75;margin-bottom:1.5rem;">
          GOVIBE Innovation Hub est né d'une vision simple mais audacieuse : créer en Haïti un écosystème complet où les entrepreneurs pourraient trouver tout ce dont ils ont besoin pour réussir — de l'espace physique aux solutions digitales, en passant par le financement.
        </p>
        <p style="color:#64748b;line-height:1.75;margin-bottom:1.5rem;">
          Fondé en 2020 par Roosevelt Forestal, GOVIBE a rapidement évolué d'un simple espace de coworking vers un véritable hub d'innovation multicouche, servant aujourd'hui plus de 1000 clients à travers Haïti, les États-Unis, le Canada et l'Afrique.
        </p>
        <div style="background:linear-gradient(135deg,#DC2626,#991b1b);border-radius:16px;padding:1.5rem;color:#fff;margin-top:1.5rem;">
          <p style="font-family:'Anton',sans-serif;font-size:1.3rem;margin-bottom:.5rem;">"L'innovation n'attend pas les conditions parfaites. On la crée."</p>
          <p style="color:rgba(255,255,255,.7);font-size:.88rem;">— Roosevelt Forestal, Founder &amp; CEO</p>
        </div>
      </div>

      <div class="slide-up delay-2">
        <div class="timeline-item">
          <div class="timeline-dot"><i class="fas fa-flag"></i></div>
          <div class="timeline-content">
            <div class="timeline-year">2020</div>
            <h4>Fondation de GOVIBE</h4>
            <p>Roosevelt Forestal fonde GOVIBE Innovation Hub à Port-au-Prince. Enregistrement comme GOVIBE STARTUP LLC aux USA (IRS) et enregistrement au MCI Haïti.</p>
          </div>
        </div>
        <div class="timeline-item">
          <div class="timeline-dot"><i class="fas fa-building"></i></div>
          <div class="timeline-content">
            <div class="timeline-year">2021</div>
            <h4>Ouverture du Coworking Space</h4>
            <p>Lancement de l'espace coworking avec bureaux flexibles, privés et salles de réunion. Premiers clients comme Delimart Haiti et Access Haiti.</p>
          </div>
        </div>
        <div class="timeline-item">
          <div class="timeline-dot"><i class="fas fa-code"></i></div>
          <div class="timeline-content">
            <div class="timeline-year">2022</div>
            <h4>Lancement du Startup Lab</h4>
            <p>Création du département développement logiciel. Partenariats avec Prestige Bière et Sunrise Airways. Développement des premières solutions ERP.</p>
          </div>
        </div>
        <div class="timeline-item">
          <div class="timeline-dot"><i class="fas fa-graduation-cap"></i></div>
          <div class="timeline-content">
            <div class="timeline-year">2023</div>
            <h4>GOVIBE Academy &amp; Call Center</h4>
            <p>Lancement de l'académie de formation professionnelle et du call center 24/7. Expansion en Canada, USA et Afrique.</p>
          </div>
        </div>
        <div class="timeline-item">
          <div class="timeline-dot"><i class="fas fa-robot"></i></div>
          <div class="timeline-content">
            <div class="timeline-year">2024–2025</div>
            <h4>GOVIBE AI &amp; SaaS Platforms</h4>
            <p>Intégration de l'intelligence artificielle dans tous les services. Lancement des plateformes SaaS Klasyo, Hospify, Tchekela.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- LEGAL REGISTRATION --}}
<section style="padding:80px 2rem;background:#fff;">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center;margin-bottom:3rem;">
      <span class="gv-stag"><i class="fas fa-certificate"></i> ENREGISTREMENT LÉGAL</span>
      <h2 class="gv-h2">GOVIBE — <span style="color:#DC2626;">Entité Légalement Reconnue</span></h2>
      <p class="gv-sub" style="margin:0 auto;text-align:center;">Enregistré et opérant légalement aux États-Unis et en Haïti.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
      <div class="legal-card slide-up">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.2rem;">
          <div style="width:56px;height:56px;border-radius:12px;background:rgba(220,38,38,.08);display:flex;align-items:center;justify-content:center;font-size:1.8rem;">
            🇺🇸
          </div>
          <div>
            <h3>GOVIBE STARTUP LLC</h3>
            <p style="color:#DC2626;font-size:.82rem;font-weight:700;letter-spacing:.08em;">ÉTATS-UNIS D'AMÉRIQUE</p>
          </div>
        </div>
        <ul style="list-style:none;padding:0;display:flex;flex-direction:column;gap:.6rem;">
          <li style="display:flex;align-items:center;gap:.7rem;color:#475569;font-size:.9rem;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Enregistré auprès de l'IRS (Internal Revenue Service)</li>
          <li style="display:flex;align-items:center;gap:.7rem;color:#475569;font-size:.9rem;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Structure légale : LLC (Limited Liability Company)</li>
          <li style="display:flex;align-items:center;gap:.7rem;color:#475569;font-size:.9rem;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Opérations légales aux USA, Canada et international</li>
          <li style="display:flex;align-items:center;gap:.7rem;color:#475569;font-size:.9rem;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Facturation internationale en USD</li>
        </ul>
      </div>

      <div class="legal-card slide-up delay-2">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.2rem;">
          <div style="width:56px;height:56px;border-radius:12px;background:rgba(220,38,38,.08);display:flex;align-items:center;justify-content:center;font-size:1.8rem;">
            🇭🇹
          </div>
          <div>
            <h3>GOVIBE Haïti</h3>
            <p style="color:#DC2626;font-size:.82rem;font-weight:700;letter-spacing:.08em;">REPUBLIC D'HAÏTI</p>
          </div>
        </div>
        <ul style="list-style:none;padding:0;display:flex;flex-direction:column;gap:.6rem;">
          <li style="display:flex;align-items:center;gap:.7rem;color:#475569;font-size:.9rem;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Enregistré au MCI (Ministère du Commerce et de l'Industrie)</li>
          <li style="display:flex;align-items:center;gap:.7rem;color:#475569;font-size:.9rem;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Patente commerciale et enregistrement fiscal</li>
          <li style="display:flex;align-items:center;gap:.7rem;color:#475569;font-size:.9rem;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Siège social : Port-au-Prince, Haïti</li>
          <li style="display:flex;align-items:center;gap:.7rem;color:#475569;font-size:.9rem;"><i class="fas fa-check-circle" style="color:#DC2626;"></i> Emplois locaux : 13 temps plein, 23 temps partiel</li>
        </ul>
      </div>
    </div>
  </div>
</section>

{{-- FOUNDER & TEAM --}}
<section id="team" style="padding:80px 2rem;background:linear-gradient(135deg,#f8fafc,#fff);">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center;margin-bottom:3.5rem;">
      <span class="gv-stag"><i class="fas fa-users"></i> NOTRE ÉQUIPE</span>
      <h2 class="gv-h2">Les <span style="color:#DC2626;">Visionnaires</span> derrière GOVIBE</h2>
      <p class="gv-sub" style="margin:0 auto;text-align:center;">13 employés à temps plein + 23 à temps partiel = 36 experts passionnés</p>
    </div>

    {{-- Founder --}}
    <div class="slide-up" style="background:linear-gradient(135deg,#0a0000,#1a0004);border-radius:24px;padding:3rem;margin-bottom:3rem;color:#fff;display:flex;flex-wrap:wrap;gap:2.5rem;align-items:center;">
      <div style="width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#DC2626,#991b1b);display:flex;align-items:center;justify-content:center;font-family:'Anton',sans-serif;font-size:3rem;color:#fff;flex-shrink:0;box-shadow:0 0 40px rgba(220,38,38,.4);">RF</div>
      <div style="flex:1;min-width:260px;">
        <div style="font-size:.75rem;font-weight:700;letter-spacing:.2em;color:rgba(255,255,255,.4);text-transform:uppercase;margin-bottom:.3rem;">Founder &amp; CEO</div>
        <h2 style="font-family:'Anton',sans-serif;font-size:2rem;color:#fff;margin-bottom:.5rem;">Roosevelt Forestal</h2>
        <p style="color:rgba(255,255,255,.65);line-height:1.75;font-size:.95rem;max-width:580px;">
          Entrepreneur haïtien visionnaire, Roosevelt Forestal a fondé GOVIBE en 2020 avec la mission de créer un écosystème d'innovation complet pour les entrepreneurs haïtiens.
          Sa vision : positionner Haïti comme un hub technologique de la Caraïbe, en connectant les talents locaux aux opportunités mondiales.
        </p>
        <div style="display:flex;gap:1rem;margin-top:1.2rem;flex-wrap:wrap;">
          <span style="background:rgba(220,38,38,.2);border:1px solid rgba(220,38,38,.3);border-radius:50px;padding:.3rem .9rem;font-size:.78rem;color:#DC2626;font-weight:700;">GOVIBE STARTUP LLC</span>
          <span style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:50px;padding:.3rem .9rem;font-size:.78rem;color:rgba(255,255,255,.7);font-weight:700;">Port-au-Prince, Haïti</span>
          <span style="background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);border-radius:50px;padding:.3rem .9rem;font-size:.78rem;color:#10B981;font-weight:700;">Fondateur 2020</span>
        </div>
      </div>
    </div>

    {{-- Team stats --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.5rem;margin-bottom:3rem;">
      <div class="team-card slide-up">
        <div class="team-avatar" style="background:linear-gradient(135deg,#DC2626,#991b1b);">13</div>
        <h4 style="font-family:'Anton',sans-serif;font-size:1.1rem;color:#0f172a;margin-bottom:.3rem;">Temps Plein</h4>
        <p style="color:#64748b;font-size:.85rem;">Employés permanents</p>
      </div>
      <div class="team-card slide-up delay-1">
        <div class="team-avatar" style="background:linear-gradient(135deg,#10B981,#059669);">23</div>
        <h4 style="font-family:'Anton',sans-serif;font-size:1.1rem;color:#0f172a;margin-bottom:.3rem;">Temps Partiel</h4>
        <p style="color:#64748b;font-size:.85rem;">Collaborateurs experts</p>
      </div>
      <div class="team-card slide-up delay-2">
        <div class="team-avatar" style="background:linear-gradient(135deg,#2563EB,#1e40af);">50+</div>
        <h4 style="font-family:'Anton',sans-serif;font-size:1.1rem;color:#0f172a;margin-bottom:.3rem;">Experts</h4>
        <p style="color:#64748b;font-size:.85rem;">Réseau de spécialistes</p>
      </div>
      <div class="team-card slide-up delay-3">
        <div class="team-avatar" style="background:linear-gradient(135deg,#a855f7,#7c3aed);">+9</div>
        <h4 style="font-family:'Anton',sans-serif;font-size:1.1rem;color:#0f172a;margin-bottom:.3rem;">Pays</h4>
        <p style="color:#64748b;font-size:.85rem;">Présence internationale</p>
      </div>
    </div>
  </div>
</section>

{{-- CLIENTS & PARTNERS --}}
<section id="clients" style="padding:80px 2rem;background:#fff;">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center;margin-bottom:3rem;">
      <span class="gv-stag"><i class="fas fa-handshake"></i> NOS CLIENTS &amp; PARTENAIRES</span>
      <h2 class="gv-h2">Ils font confiance à <span style="color:#DC2626;">GOVIBE</span></h2>
      <p class="gv-sub" style="margin:0 auto;text-align:center;">Des grandes marques en Haïti, au Canada, aux USA et en Afrique</p>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:1rem;justify-content:center;margin-bottom:3rem;" class="slide-up">
      <span class="client-badge"><i class="fas fa-store"></i> Delimart Haiti</span>
      <span class="client-badge"><i class="fas fa-beer"></i> Prestige Bière</span>
      <span class="client-badge"><i class="fas fa-plane"></i> Sunrise Airways</span>
      <span class="client-badge"><i class="fas fa-wifi"></i> Access Haiti</span>
      <span class="client-badge"><i class="fas fa-building"></i> Clients Haïti</span>
      <span class="client-badge"><i class="fas fa-flag"></i> Clients Canada</span>
      <span class="client-badge"><i class="fas fa-flag-usa"></i> Clients USA</span>
      <span class="client-badge"><i class="fas fa-globe-africa"></i> Clients Afrique</span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;" class="slide-up">
      <div style="text-align:center;padding:2rem;background:#f8fafc;border-radius:16px;border:1px solid #e2e8f0;">
        <div style="font-family:'Anton',sans-serif;font-size:2.5rem;color:#DC2626;">Haïti</div>
        <p style="color:#64748b;font-size:.88rem;margin-top:.5rem;">Delimart, Prestige Bière, Sunrise Airways, Access Haiti et des centaines d'entreprises locales</p>
      </div>
      <div style="text-align:center;padding:2rem;background:#f8fafc;border-radius:16px;border:1px solid #e2e8f0;">
        <div style="font-family:'Anton',sans-serif;font-size:2.5rem;color:#10B981;">USA &amp; Canada</div>
        <p style="color:#64748b;font-size:.88rem;margin-top:.5rem;">Diaspora haïtienne, PME et entreprises technologiques en Amérique du Nord</p>
      </div>
      <div style="text-align:center;padding:2rem;background:#f8fafc;border-radius:16px;border:1px solid #e2e8f0;">
        <div style="font-family:'Anton',sans-serif;font-size:2.5rem;color:#2563EB;">Afrique</div>
        <p style="color:#64748b;font-size:.88rem;margin-top:.5rem;">Partenariats avec organisations et entrepreneurs africains partageant notre vision</p>
      </div>
    </div>
  </div>
</section>

{{-- CTA --}}
<section style="padding:70px 2rem;background:linear-gradient(135deg,#0a0000,#1a0004);">
  <div class="gv-wrap">
    <div class="slide-up" style="text-align:center;">
      <h2 style="font-family:'Anton',sans-serif;font-size:clamp(1.8rem,4vw,2.8rem);color:#fff;margin-bottom:1rem;">
        Prêt à rejoindre<br><span style="color:#DC2626;">l'écosystème GOVIBE ?</span>
      </h2>
      <p style="color:rgba(255,255,255,.65);max-width:500px;margin:0 auto 2rem;line-height:1.75;">
        Que vous soyez entrepreneur, startup, institution ou entreprise — GOVIBE a la solution pour vous.
      </p>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
        <a href="{{ route('home') }}#reservation" class="gv-btn-prim"><i class="fas fa-calendar-alt"></i> Prendre contact</a>
        <a href="{{ route('inscription.create') }}" class="gv-btn-prim green"><i class="fas fa-graduation-cap"></i> GOVIBE Academy</a>
        <a href="{{ route('coworking') }}" class="gv-btn-outline" style="color:#fff;border-color:rgba(255,255,255,.3);"><i class="fas fa-building"></i> Coworking Space</a>
      </div>
    </div>
  </div>
</section>

@endsection

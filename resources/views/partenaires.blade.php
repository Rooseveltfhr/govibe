@extends('layouts.public')

@section('title', 'Partenaires — GOVIBE Innovation Hub')

@section('content')
<style>
/* ── Hero ── */
.part-hero {
    background: linear-gradient(135deg, #0a0f1e 0%, #1a1f2e 60%, #2a0a0a 100%);
    padding: 120px 0 80px;
    position: relative;
    overflow: hidden;
}
.part-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 55% 65% at 75% 45%, rgba(220,38,38,.13) 0%, transparent 70%);
}
.part-hero-inner { position: relative; z-index: 1; }
.part-hero-tag {
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
.part-hero h1 {
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    margin-bottom: 20px;
}
.part-hero h1 span { color: #DC2626; }
.part-hero p {
    font-size: 1.05rem;
    color: rgba(255,255,255,.7);
    max-width: 580px;
    line-height: 1.7;
}

/* ── Partners list ── */
.part-list-section {
    padding: 80px 0;
    background: #fff;
}
.dark .part-list-section { background: #111827; }
.part-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 24px;
    margin-top: 48px;
}
.part-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 28px 20px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    transition: box-shadow .25s, transform .25s;
}
.dark .part-card { background: #1e293b; border-color: #334155; }
.part-card:hover {
    box-shadow: 0 8px 32px rgba(220,38,38,.1);
    transform: translateY(-4px);
}
.part-logo-box {
    width: 80px;
    height: 80px;
    border-radius: 14px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.dark .part-logo-box { background: #0f172a; }
.part-logo-box img {
    max-width: 70px;
    max-height: 70px;
    /* contain : un logo large ou haut garde ses proportions sans être rogné. */
    object-fit: contain;
}
.part-logo-box .part-logo-placeholder {
    font-size: 2rem;
    color: #DC2626;
}
/* Repli quand un partenaire publié n'a pas encore de logo. */
.part-logo-box .part-logo-initiale {
    font-family: 'Anton', sans-serif;
    font-size: 2.2rem;
    color: #DC2626;
    line-height: 1;
}
/* La carte devient un lien quand un site web est renseigné. */
a.part-card { text-decoration: none; color: inherit; }
.part-card-link {
    font-size: .75rem;
    color: #DC2626;
    font-weight: 600;
    opacity: 0;
    transition: opacity .2s;
}
a.part-card:hover .part-card-link { opacity: 1; }
.part-card-cta {
    border: 2px dashed #DC2626;
    background: rgba(220,38,38,.02);
}
.dark .part-card-cta { background: rgba(220,38,38,.06); }
.part-card-name {
    font-weight: 700;
    font-size: .95rem;
    color: #0f172a;
    line-height: 1.3;
}
.dark .part-card-name { color: #f1f5f9; }
.part-card-type {
    font-size: .78rem;
    color: #64748b;
    background: rgba(220,38,38,.08);
    color: #DC2626;
    border-radius: 100px;
    padding: 3px 10px;
}

/* Partenaires vides */
.part-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 48px;
    color: #64748b;
}
.dark .part-empty { color: #94a3b8; }
.part-empty i { font-size: 3rem; margin-bottom: 16px; display: block; color: #DC2626; opacity: .5; }

/* ── Become a partner ── */
.become-section {
    padding: 80px 0;
    background: #f8f9fa;
}
.dark .become-section { background: #0f172a; }
.become-layout {
    display: grid;
    grid-template-columns: 1fr 1.3fr;
    gap: 60px;
    align-items: start;
}
.become-info h2 {
    font-size: 1.8rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 16px;
}
.dark .become-info h2 { color: #f1f5f9; }
.become-info p {
    color: #475569;
    line-height: 1.7;
    margin-bottom: 28px;
    font-size: .95rem;
}
.dark .become-info p { color: #94a3b8; }
.become-perks { display: flex; flex-direction: column; gap: 16px; }
.become-perk {
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
.perk-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    border-radius: 10px;
    background: rgba(220,38,38,.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #DC2626;
    font-size: 1rem;
}
.perk-text strong {
    display: block;
    font-size: .9rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 3px;
}
.dark .perk-text strong { color: #f1f5f9; }
.perk-text span { font-size: .83rem; color: #64748b; }
.dark .perk-text span { color: #94a3b8; }

/* form */
.become-form-box {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 40px;
}
.dark .become-form-box { background: #1e293b; border-color: #334155; }
.become-form-box h3 {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 6px;
}
.dark .become-form-box h3 { color: #f1f5f9; }
.become-form-box .form-sub {
    font-size: .85rem;
    color: #64748b;
    margin-bottom: 28px;
}
.dark .become-form-box .form-sub { color: #94a3b8; }

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block;
    font-size: .82rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}
.dark .form-group label { color: #cbd5e1; }
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: .9rem;
    color: #0f172a;
    background: #fff;
    transition: border-color .2s, box-shadow .2s;
    font-family: inherit;
    box-sizing: border-box;
}
.dark .form-group input,
.dark .form-group select,
.dark .form-group textarea {
    background: #0f172a;
    border-color: #475569;
    color: #f1f5f9;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #DC2626;
    box-shadow: 0 0 0 3px rgba(220,38,38,.12);
}
.form-group textarea { resize: vertical; min-height: 90px; }
.form-group .err { font-size: .78rem; color: #DC2626; margin-top: 4px; display: block; }

.form-submit-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    background: linear-gradient(135deg, #DC2626, #b91c1c);
    color: #fff;
    font-weight: 700;
    font-size: .95rem;
    padding: 13px 24px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: opacity .2s, transform .2s;
    margin-top: 8px;
}
.form-submit-btn:hover { opacity: .9; transform: translateY(-2px); }

.form-success {
    background: rgba(22,163,74,.08);
    border: 1px solid rgba(22,163,74,.3);
    color: #16A34A;
    padding: 16px 20px;
    border-radius: 10px;
    font-size: .9rem;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.form-error-box {
    background: rgba(220,38,38,.08);
    border: 1px solid rgba(220,38,38,.3);
    color: #DC2626;
    padding: 14px 18px;
    border-radius: 10px;
    font-size: .88rem;
    margin-bottom: 16px;
}

@media (max-width: 900px) {
    .become-layout { grid-template-columns: 1fr; gap: 40px; }
    .part-hero { padding: 90px 0 60px; }
}
@media (max-width: 768px) {
    .part-grid { grid-template-columns: repeat(2, 1fr); }
    .form-row { grid-template-columns: 1fr; }
    .become-form-box { padding: 28px 20px; }
}
@media (max-width: 480px) {
    .part-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<!-- HERO -->
<section class="part-hero">
    <div class="gv-wrap part-hero-inner">
        <div class="part-hero-tag">
            <img src="{{ asset('images/govibe-icon.svg') }}" alt="" style="height:16px;">
            Partenaires & Collaborateurs
        </div>
        <h1>Construisons ensemble<br><span>l'avenir numérique</span></h1>
        <p>GOVIBE Innovation Hub s'appuie sur un réseau de partenaires stratégiques, institutionnels et techniques engagés pour le développement de l'écosystème entrepreneurial haïtien.</p>
    </div>
</section>

<!-- LISTE PARTENAIRES -->
<section class="part-list-section">
    <div class="gv-wrap">
        <div class="gv-stag">Notre réseau</div>
        <h2 class="section-title">Nos partenaires & collaborateurs</h2>
        <p class="section-subtitle">Des organisations qui partagent notre vision pour l'innovation et le développement en Haïti.</p>

        <div class="part-grid">
            @forelse ($partenaires as $partenaire)
                @php
                    // Un logo peut manquer même sur un partenaire publié :
                    // on retombe alors sur l'initiale de son nom.
                    $lien = $partenaire->site_web;
                    $tag  = $lien ? 'a' : 'div';
                @endphp
                <{{ $tag }} class="part-card slide-up"
                    @if ($lien) href="{{ $lien }}" target="_blank" rel="noopener" @endif>
                    <div class="part-logo-box">
                        @if ($partenaire->logo_url)
                            <img src="{{ $partenaire->logo_url }}"
                                 alt="{{ $partenaire->nom_vitrine }}"
                                 loading="lazy">
                        @else
                            <span class="part-logo-initiale">{{ mb_strtoupper(mb_substr($partenaire->nom_vitrine, 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="part-card-name">{{ $partenaire->nom_vitrine }}</div>
                    <div class="part-card-type">{{ $partenaire->type_libelle }}</div>
                    @if ($lien)
                        <span class="part-card-link"><i class="fas fa-arrow-up-right-from-square"></i> Visiter</span>
                    @endif
                </{{ $tag }}>
            @empty
                {{-- Aucun partenaire publié : on présente les catégories
                     recherchées plutôt qu'une grille vide. --}}
                <div class="part-card slide-up">
                    <div class="part-logo-box">
                        <span class="part-logo-placeholder"><i class="fas fa-university"></i></span>
                    </div>
                    <div class="part-card-name">Institutions Académiques</div>
                    <div class="part-card-type">Partenaire institutionnel</div>
                </div>

                <div class="part-card slide-up">
                    <div class="part-logo-box">
                        <span class="part-logo-placeholder"><i class="fas fa-building"></i></span>
                    </div>
                    <div class="part-card-name">Secteur Privé</div>
                    <div class="part-card-type">Sponsor</div>
                </div>

                <div class="part-card slide-up">
                    <div class="part-logo-box">
                        <span class="part-logo-placeholder"><i class="fas fa-globe"></i></span>
                    </div>
                    <div class="part-card-name">ONG Internationales</div>
                    <div class="part-card-type">Partenaire stratégique</div>
                </div>

                <div class="part-card slide-up">
                    <div class="part-logo-box">
                        <span class="part-logo-placeholder"><i class="fas fa-laptop-code"></i></span>
                    </div>
                    <div class="part-card-name">Tech Companies</div>
                    <div class="part-card-type">Collaborateur technique</div>
                </div>

                <div class="part-card slide-up">
                    <div class="part-logo-box">
                        <span class="part-logo-placeholder"><i class="fas fa-chalkboard-teacher"></i></span>
                    </div>
                    <div class="part-card-name">Experts &amp; Mentors</div>
                    <div class="part-card-type">Mentor / Formateur</div>
                </div>
            @endforelse

            <a href="#devenir-partenaire" class="part-card part-card-cta slide-up">
                <div class="part-logo-box" style="background: rgba(220,38,38,.08);">
                    <span class="part-logo-placeholder" style="opacity:.5;"><i class="fas fa-plus"></i></span>
                </div>
                <div class="part-card-name" style="color:#DC2626;">Votre organisation</div>
                <div class="part-card-type">Rejoignez-nous</div>
            </a>
        </div>
    </div>
</section>

<!-- DEVENIR PARTENAIRE -->
<section class="become-section" id="devenir-partenaire">
    <div class="gv-wrap">
        <div class="become-layout">

            <!-- Left: info -->
            <div class="become-info slide-up">
                <div class="gv-stag">Collaboration</div>
                <h2>Devenez partenaire de GOVIBE Innovation Hub</h2>
                <p>Que vous soyez une entreprise, une institution académique, une ONG ou un expert indépendant, GOVIBE vous offre un cadre de collaboration unique pour amplifier votre impact dans l'écosystème entrepreneurial haïtien.</p>

                <div class="become-perks">
                    <div class="become-perk">
                        <div class="perk-icon"><i class="fas fa-bullseye"></i></div>
                        <div class="perk-text">
                            <strong>Visibilité & Notoriété</strong>
                            <span>Présence sur nos supports, événements et communications digitales.</span>
                        </div>
                    </div>
                    <div class="become-perk">
                        <div class="perk-icon"><i class="fas fa-network-wired"></i></div>
                        <div class="perk-text">
                            <strong>Accès au réseau</strong>
                            <span>Connectez-vous avec des entrepreneurs, investisseurs et institutions.</span>
                        </div>
                    </div>
                    <div class="become-perk">
                        <div class="perk-icon"><i class="fas fa-users"></i></div>
                        <div class="perk-text">
                            <strong>Co-création de programmes</strong>
                            <span>Participez à la conception de formations et d'événements adaptés.</span>
                        </div>
                    </div>
                    <div class="become-perk">
                        <div class="perk-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="perk-text">
                            <strong>Impact mesurable</strong>
                            <span>Contribuez directement à la transformation numérique d'Haïti.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: form -->
            <div class="become-form-box slide-up">
                <h3>Soumettre une demande de partenariat</h3>
                <p class="form-sub">Notre équipe vous contactera sous 48 heures ouvrables.</p>

                @if (session('success'))
                    <div class="form-success">
                        <i class="fas fa-check-circle fa-lg"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="form-error-box">
                        <strong>Veuillez corriger les erreurs ci-dessous :</strong>
                        <ul style="margin:6px 0 0 16px;padding:0;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('partenaires.store') }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prenom">Prénom <span style="color:#DC2626;">*</span></label>
                            <input type="text" id="prenom" name="prenom" value="{{ old('prenom') }}" placeholder="Jean" required>
                            @error('prenom')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="nom">Nom <span style="color:#DC2626;">*</span></label>
                            <input type="text" id="nom" name="nom" value="{{ old('nom') }}" placeholder="Dupont" required>
                            @error('nom')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="organisation">Organisation / Institution</label>
                        <input type="text" id="organisation" name="organisation" value="{{ old('organisation') }}" placeholder="Nom de votre organisation (optionnel)">
                        @error('organisation')<span class="err">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="pays">Pays <span style="color:#DC2626;">*</span></label>
                            <input type="text" id="pays" name="pays" value="{{ old('pays', 'Haïti') }}" placeholder="Haïti" required>
                            @error('pays')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="ville">Ville <span style="color:#DC2626;">*</span></label>
                            <input type="text" id="ville" name="ville" value="{{ old('ville') }}" placeholder="Port-au-Prince" required>
                            @error('ville')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="telephone">Téléphone / WhatsApp <span style="color:#DC2626;">*</span></label>
                            <input type="tel" id="telephone" name="telephone" value="{{ old('telephone') }}" placeholder="+509 3700 0000" required>
                            @error('telephone')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span style="color:#DC2626;">*</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="votre@email.com" required>
                            @error('email')<span class="err">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="type_partenariat">Type de partenariat <span style="color:#DC2626;">*</span></label>
                        <select id="type_partenariat" name="type_partenariat" required>
                            <option value="">— Sélectionnez un type —</option>
                            <option value="partenaire_strategique" {{ old('type_partenariat') == 'partenaire_strategique' ? 'selected' : '' }}>Partenaire Stratégique</option>
                            <option value="sponsor" {{ old('type_partenariat') == 'sponsor' ? 'selected' : '' }}>Sponsor / Commanditaire</option>
                            <option value="partenaire_institutionnel" {{ old('type_partenariat') == 'partenaire_institutionnel' ? 'selected' : '' }}>Partenaire Institutionnel</option>
                            <option value="collaborateur_technique" {{ old('type_partenariat') == 'collaborateur_technique' ? 'selected' : '' }}>Collaborateur Technique</option>
                            <option value="mentor_formateur" {{ old('type_partenariat') == 'mentor_formateur' ? 'selected' : '' }}>Mentor / Formateur</option>
                            <option value="investisseur" {{ old('type_partenariat') == 'investisseur' ? 'selected' : '' }}>Investisseur</option>
                            <option value="media_presse" {{ old('type_partenariat') == 'media_presse' ? 'selected' : '' }}>Média / Presse</option>
                            <option value="autre" {{ old('type_partenariat') == 'autre' ? 'selected' : '' }}>Autre</option>
                        </select>
                        @error('type_partenariat')<span class="err">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="description">Description de la collaboration souhaitée</label>
                        <textarea id="description" name="description" placeholder="Décrivez votre organisation, vos objectifs et comment vous imaginez cette collaboration...">{{ old('description') }}</textarea>
                        @error('description')<span class="err">{{ $message }}</span>@enderror
                    </div>

                    <button type="submit" class="form-submit-btn">
                        <i class="fas fa-handshake"></i>
                        Soumettre ma demande de partenariat
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection

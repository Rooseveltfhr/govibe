@extends('layouts.public')

@section('title', 'AI Agents pour entreprises | GOVIBE Innovation Hub')
@section('description', "Créez un Agent IA pour automatiser votre service client, WhatsApp, réservations, ventes et opérations. GOVIBE conçoit et déploie des solutions AI adaptées à votre entreprise.")

@section('head')
<meta name="keywords" content="AI Agent Haiti, Agent IA Haiti, AI chatbot Haiti, WhatsApp AI Agent, Agent IA WhatsApp, AI automation Haiti, AI customer service, AI receptionist, AI for restaurants, AI for hotels">
<link rel="canonical" href="{{ route('agents-ia.index') }}">
<style>
  /* ── En-tête ─────────────────────────────────────────── */
  .ia-hero {
    position: relative; overflow: hidden;
    background: linear-gradient(135deg, #080002 0%, #1a0004 52%, #050505 100%);
    padding: 96px 1.5rem 78px;
  }
  .ia-hero::before {
    content: ''; position: absolute; inset: 0;
    background:
      radial-gradient(ellipse 50% 55% at 78% 38%, rgba(220,38,38,.20) 0%, transparent 70%),
      radial-gradient(ellipse 40% 45% at 12% 78%, rgba(220,38,38,.10) 0%, transparent 70%);
  }
  /* Trame technique discrète : évoque l'infrastructure sans surcharger. */
  .ia-hero::after {
    content: ''; position: absolute; inset: 0; opacity: .35;
    background-image:
      linear-gradient(rgba(255,255,255,.028) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,.028) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 70% 70% at 50% 40%, #000 30%, transparent 100%);
    -webkit-mask-image: radial-gradient(ellipse 70% 70% at 50% 40%, #000 30%, transparent 100%);
  }
  .ia-hero-in {
    position: relative; z-index: 1; max-width: 1120px; margin: 0 auto;
    display: grid; grid-template-columns: 1.08fr .92fr; gap: 3rem; align-items: center;
  }
  .ia-tag {
    display: inline-flex; align-items: center; gap: .5rem;
    background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.4);
    color: #f87171; font-family: 'Anton', sans-serif; font-weight: 400;
    font-size: .76rem; letter-spacing: .16em; text-transform: uppercase;
    padding: .38rem 1rem; border-radius: 50px; margin-bottom: 1.4rem;
  }
  .ia-hero h1 {
    font-family: 'Anton', sans-serif; font-size: clamp(2.1rem, 5.4vw, 3.6rem);
    color: #fff; line-height: 1.04; letter-spacing: .01em; margin: 0 0 1.1rem;
    text-wrap: balance;
  }
  .ia-hero h1 span { color: #DC2626; }
  .ia-hero .ia-sub {
    color: rgba(255,255,255,.74); font-size: 1.05rem; line-height: 1.75;
    max-width: 520px; margin: 0 0 1.9rem;
  }
  .ia-cta-row { display: flex; flex-wrap: wrap; gap: .8rem; }
  .ia-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .55rem;
    background: linear-gradient(135deg, #DC2626, #991b1b); color: #fff;
    font-family: 'Anton', sans-serif; letter-spacing: .045em; font-size: 1rem;
    padding: .92rem 1.9rem; border-radius: 50px; text-decoration: none;
    box-shadow: 0 10px 30px -10px rgba(220,38,38,.6);
  }
  .ia-btn:hover { opacity: .94; color: #fff; }
  .ia-btn-ghost {
    display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
    border: 1px solid rgba(255,255,255,.26); color: rgba(255,255,255,.88);
    font-family: 'Anton', sans-serif; letter-spacing: .045em; font-size: 1rem;
    padding: .92rem 1.7rem; border-radius: 50px; text-decoration: none;
  }
  .ia-btn-ghost:hover { background: rgba(255,255,255,.09); color: #fff; }

  /* Vignette de conversation : montre le produit au lieu de le décrire. */
  .ia-visuel {
    background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.11);
    border-radius: 20px; padding: 1.3rem; backdrop-filter: blur(6px);
  }
  .ia-visuel-tete {
    display: flex; align-items: center; gap: .7rem;
    padding-bottom: .9rem; margin-bottom: .9rem; border-bottom: 1px solid rgba(255,255,255,.08);
  }
  .ia-avatar {
    width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0;
    background: linear-gradient(135deg, #DC2626, #991b1b);
    display: flex; align-items: center; justify-content: center; color: #fff; font-size: .95rem;
  }
  .ia-visuel-tete strong { display: block; color: #fff; font-size: .9rem; }
  .ia-visuel-tete span { display: block; color: #4ade80; font-size: .74rem; }
  .ia-bulle {
    border-radius: 13px; padding: .68rem .9rem; font-size: .85rem;
    line-height: 1.55; margin-bottom: .55rem; max-width: 86%;
  }
  .ia-bulle-client {
    background: rgba(255,255,255,.09); color: rgba(255,255,255,.9);
    margin-left: auto; border-bottom-right-radius: 4px;
  }
  .ia-bulle-agent {
    background: rgba(220,38,38,.17); border: 1px solid rgba(220,38,38,.3);
    color: rgba(255,255,255,.93); border-bottom-left-radius: 4px;
  }
  .ia-canaux {
    display: flex; gap: .45rem; flex-wrap: wrap; margin-top: 1rem;
    padding-top: .9rem; border-top: 1px solid rgba(255,255,255,.08);
  }
  .ia-canal {
    display: inline-flex; align-items: center; gap: .35rem;
    background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
    color: rgba(255,255,255,.72); font-size: .72rem; padding: .3rem .7rem; border-radius: 50px;
  }

  /* ── Bandeau de preuve ───────────────────────────────── */
  .ia-atouts { background: #0f0f10; padding: 2.2rem 1.5rem; }
  .ia-atouts-in {
    max-width: 1120px; margin: 0 auto;
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.4rem;
  }
  .ia-atout { display: flex; align-items: flex-start; gap: .7rem; }
  .ia-atout i { color: #DC2626; font-size: 1.05rem; margin-top: .15rem; }
  .ia-atout strong { display: block; color: #fff; font-size: .88rem; margin-bottom: .12rem; }
  .ia-atout span { color: rgba(255,255,255,.5); font-size: .78rem; line-height: 1.5; }

  /* ── Catalogue ───────────────────────────────────────── */
  .ia-cat { background: #f8fafc; padding: 74px 1.5rem 80px; }
  .ia-cat-in { max-width: 1120px; margin: 0 auto; }
  .ia-titre-sect { text-align: center; margin-bottom: 2.6rem; }
  .ia-titre-sect h2 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.7rem, 4vw, 2.5rem);
    color: #0f172a; margin: 0 0 .7rem; letter-spacing: .015em; text-wrap: balance;
  }
  .ia-titre-sect p { color: #64748b; max-width: 580px; margin: 0 auto; line-height: 1.7; font-size: .96rem; }

  .ia-grille { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.3rem; }
  .ia-carte {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 18px;
    padding: 1.6rem; display: flex; flex-direction: column;
    transition: border-color .18s, transform .18s, box-shadow .18s;
  }
  .ia-carte:hover {
    border-color: #fca5a5; transform: translateY(-3px);
    box-shadow: 0 16px 34px -20px rgba(15,23,42,.28);
  }
  .ia-carte-sur-mesure {
    background: linear-gradient(150deg, #1a0004 0%, #0a0000 100%);
    border-color: rgba(220,38,38,.35);
  }
  .ia-ico {
    width: 46px; height: 46px; border-radius: 13px; margin-bottom: 1rem;
    background: #fef2f2; color: #DC2626;
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
  }
  .ia-carte-sur-mesure .ia-ico { background: rgba(220,38,38,.18); color: #f87171; }
  .ia-carte h3 {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1.12rem;
    color: #0f172a; margin: 0 0 .1rem; letter-spacing: .015em; line-height: 1.25;
  }
  .ia-carte-sur-mesure h3 { color: #fff; }
  .ia-cat-label {
    display: block; font-size: .7rem; letter-spacing: .13em; text-transform: uppercase;
    color: #94a3b8; margin-bottom: .7rem;
  }
  .ia-carte p.ia-desc { color: #64748b; font-size: .88rem; line-height: 1.68; margin: 0 0 1rem; }
  .ia-carte-sur-mesure p.ia-desc { color: rgba(255,255,255,.7); }

  .ia-capacites { list-style: none; padding: 0; margin: 0 0 1.1rem; }
  .ia-capacites li {
    display: flex; gap: .5rem; align-items: flex-start;
    color: #475569; font-size: .83rem; line-height: 1.55; padding: .17rem 0;
  }
  .ia-capacites li i { color: #DC2626; font-size: .68rem; margin-top: .38rem; flex-shrink: 0; }
  .ia-carte-sur-mesure .ia-capacites li { color: rgba(255,255,255,.72); }
  .ia-plus { font-size: .78rem; color: #94a3b8; padding-left: 1.05rem; }

  .ia-avert {
    background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;
    padding: .6rem .75rem; font-size: .76rem; color: #92400e;
    line-height: 1.55; margin-bottom: 1rem;
  }

  .ia-tags-canaux { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: 1.1rem; }
  .ia-tag-canal {
    background: #f1f5f9; color: #475569; font-size: .72rem;
    padding: .22rem .6rem; border-radius: 50px;
  }
  .ia-carte-sur-mesure .ia-tag-canal { background: rgba(255,255,255,.08); color: rgba(255,255,255,.7); }

  .ia-pied { margin-top: auto; padding-top: 1.05rem; border-top: 1px solid #f1f5f9; }
  .ia-carte-sur-mesure .ia-pied { border-top-color: rgba(255,255,255,.1); }
  .ia-prix { font-family: 'Anton', sans-serif; font-size: 1.02rem; color: #0f172a; letter-spacing: .015em; }
  .ia-carte-sur-mesure .ia-prix { color: #fff; }
  .ia-prix-mois { display: block; font-family: 'DM Sans', sans-serif; font-size: .76rem; color: #94a3b8; margin-top: .1rem; }
  .ia-carte-sur-mesure .ia-prix-mois { color: rgba(255,255,255,.5); }
  .ia-carte-cta {
    display: flex; align-items: center; justify-content: center; gap: .45rem;
    width: 100%; margin-top: .9rem; background: #0f172a; color: #fff;
    font-family: 'Anton', sans-serif; letter-spacing: .04em; font-size: .9rem;
    padding: .72rem 1.2rem; border-radius: 50px; text-decoration: none;
  }
  .ia-carte-cta:hover { background: #DC2626; color: #fff; }
  .ia-carte-sur-mesure .ia-carte-cta { background: linear-gradient(135deg, #DC2626, #991b1b); }
  .ia-carte-sur-mesure .ia-carte-cta:hover { opacity: .92; }

  /* ── Comment ça se passe ─────────────────────────────── */
  .ia-etapes { background: #fff; padding: 70px 1.5rem; }
  .ia-etapes-in { max-width: 1120px; margin: 0 auto; }
  .ia-etapes-grille { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; }
  .ia-etape { position: relative; padding-top: 1.6rem; }
  .ia-etape::before {
    content: attr(data-n); position: absolute; top: 0; left: 0;
    font-family: 'Anton', sans-serif; font-size: 1.6rem; color: #fee2e2; line-height: 1;
  }
  .ia-etape h4 {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1rem;
    color: #0f172a; margin: 0 0 .35rem; letter-spacing: .02em;
  }
  .ia-etape p { color: #64748b; font-size: .85rem; line-height: 1.65; margin: 0; }

  /* ── Tarification ────────────────────────────────────── */
  .ia-tarifs { background: #f8fafc; padding: 66px 1.5rem; }
  .ia-tarifs-in { max-width: 780px; margin: 0 auto; }
  .ia-deux-frais { display: grid; grid-template-columns: 1fr 1fr; gap: 1.1rem; margin-bottom: 1.2rem; }
  .ia-frais { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 1.4rem; }
  .ia-frais i { color: #DC2626; font-size: 1.1rem; margin-bottom: .6rem; display: block; }
  .ia-frais h4 {
    font-family: 'Anton', sans-serif; font-weight: 400; font-size: 1rem;
    color: #0f172a; margin: 0 0 .3rem; letter-spacing: .02em;
  }
  .ia-frais p { color: #64748b; font-size: .85rem; line-height: 1.65; margin: 0; }
  .ia-note-prix {
    background: #fff; border: 1px solid #e5e7eb; border-left: 4px solid #DC2626;
    border-radius: 12px; padding: 1.05rem 1.3rem; color: #64748b;
    font-size: .87rem; line-height: 1.7;
  }

  /* ── Appel final ─────────────────────────────────────── */
  .ia-final {
    background: linear-gradient(135deg, #080002 0%, #1a0004 55%, #050505 100%);
    padding: 80px 1.5rem; text-align: center;
  }
  .ia-final h2 {
    font-family: 'Anton', sans-serif; font-size: clamp(1.8rem, 4.4vw, 2.7rem);
    color: #fff; margin: 0 0 .8rem; letter-spacing: .015em; text-wrap: balance;
  }
  .ia-final p { color: rgba(255,255,255,.7); max-width: 560px; margin: 0 auto 1.9rem; line-height: 1.75; }
  .ia-final .ia-cta-row { justify-content: center; }

  @media (max-width: 1000px) {
    .ia-hero-in { grid-template-columns: 1fr; gap: 2.4rem; }
    .ia-grille { grid-template-columns: repeat(2, 1fr); }
    .ia-atouts-in { grid-template-columns: repeat(2, 1fr); }
    .ia-etapes-grille { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 680px) {
    .ia-hero { padding: 70px 1.2rem 56px; }
    .ia-grille { grid-template-columns: 1fr; }
    .ia-atouts-in { grid-template-columns: 1fr; gap: 1rem; }
    .ia-etapes-grille { grid-template-columns: 1fr; }
    .ia-deux-frais { grid-template-columns: 1fr; }
    .ia-cat { padding: 52px 1.1rem 60px; }
    .ia-btn, .ia-btn-ghost { width: 100%; }
    .ia-visuel { padding: 1rem; }
  }
</style>
@endsection

@section('content')

<section class="ia-hero">
  <div class="ia-hero-in">
    <div>
      <span class="ia-tag"><i class="fas fa-bolt"></i> GOVIBE AI Agents</span>
      <h1>Automatisez votre entreprise avec un <span>Agent IA</span></h1>
      <p class="ia-sub">
        Un assistant intelligent disponible 24h/24 pour répondre à vos clients,
        automatiser vos tâches et améliorer votre service.
      </p>
      <div class="ia-cta-row">
        <a href="{{ route('agents-ia.demande') }}" class="ia-btn">
          <i class="fas fa-robot"></i> Demander un Agent IA
        </a>
        <a href="#catalogue" class="ia-btn-ghost">Découvrir les solutions</a>
      </div>
    </div>

    <div class="ia-visuel">
      <div class="ia-visuel-tete">
        <div class="ia-avatar"><i class="fas fa-robot"></i></div>
        <div>
          <strong>Agent GOVIBE</strong>
          <span><i class="fas fa-circle" style="font-size:.4rem;vertical-align:middle"></i> en ligne 24h/24</span>
        </div>
      </div>

      <div class="ia-bulle ia-bulle-client">Bonjou, èske nou gen tab pou 4 moun samdi swa ?</div>
      <div class="ia-bulle ia-bulle-agent">
        Bonjou ! Wi, nou gen tab disponib samdi a 19h ak 20h30.
        Ki lè ki pi bon pou ou ?
      </div>
      <div class="ia-bulle ia-bulle-client">20h30 souple</div>
      <div class="ia-bulle ia-bulle-agent">
        Nòte. Ban m non ou ak yon nimewo, epi rezèvasyon an fèt.
      </div>

      <div class="ia-canaux">
        <span class="ia-canal"><i class="fab fa-whatsapp"></i> WhatsApp</span>
        <span class="ia-canal"><i class="fas fa-globe"></i> Site web</span>
        <span class="ia-canal"><i class="fas fa-microphone"></i> Voix</span>
        <span class="ia-canal"><i class="fas fa-gears"></i> Automatisation</span>
      </div>
    </div>
  </div>
</section>

<section class="ia-atouts">
  <div class="ia-atouts-in">
    @foreach ([
      ['fa-clock', 'Disponible 24h/24', "Vos clients sont servis la nuit, le week-end et les jours fériés."],
      ['fab fa-whatsapp', 'Là où sont vos clients', "Sur WhatsApp d'abord, puis sur votre site et par la voix."],
      ['fa-language', 'Créole, français, anglais', "L'agent répond dans la langue du client, sans changer de numéro."],
      ['fa-user-check', "Passe la main quand il faut", "Un cas complexe est transmis à votre équipe, jamais bloqué."],
    ] as [$ico, $titre, $texte])
      <div class="ia-atout">
        <i class="{{ str_starts_with($ico, 'fab') ? $ico : 'fas '.$ico }}"></i>
        <div>
          <strong>{{ $titre }}</strong>
          <span>{{ $texte }}</span>
        </div>
      </div>
    @endforeach
  </div>
</section>

<section class="ia-cat" id="catalogue">
  <div class="ia-cat-in">
    <div class="ia-titre-sect">
      <h2>Un employé numérique par métier</h2>
      <p>
        Chaque agent est conçu pour un secteur précis : il connaît son vocabulaire,
        ses questions courantes et ce qu'il doit transmettre à votre équipe.
      </p>
    </div>

    <div class="ia-grille">
      @foreach ($agents as $agent)
        @php $surMesure = $agent->sur_devis; @endphp
        <article class="ia-carte {{ $surMesure ? 'ia-carte-sur-mesure' : '' }}">
          <div class="ia-ico"><i class="fas {{ $agent->icone }}"></i></div>

          @if ($agent->categorie)<span class="ia-cat-label">{{ $agent->categorie }}</span>@endif
          <h3>{{ $agent->nom }}</h3>
          <p class="ia-desc">{{ $agent->description_courte }}</p>

          @if ($agent->avertissement)
            <p class="ia-avert"><i class="fas fa-circle-info"></i> {{ $agent->avertissement }}</p>
          @endif

          @if ($agent->capacites)
            <ul class="ia-capacites">
              @foreach (array_slice($agent->capacites, 0, 5) as $c)
                <li><i class="fas fa-check"></i> {{ $c }}</li>
              @endforeach
            </ul>
            @if (count($agent->capacites) > 5)
              <p class="ia-plus">+ {{ count($agent->capacites) - 5 }} autres capacités</p>
            @endif
          @endif

          @if ($agent->canaux)
            <div class="ia-tags-canaux">
              @foreach ($agent->canaux as $canal)
                <span class="ia-tag-canal">{{ $canal }}</span>
              @endforeach
            </div>
          @endif

          <div class="ia-pied">
            <span class="ia-prix">{{ $agent->prix_affiche }}</span>
            @if ($agent->prix_mensuel_affiche)
              <span class="ia-prix-mois">puis {{ $agent->prix_mensuel_affiche }}</span>
            @endif
            <a href="{{ route('agents-ia.demande', ['agent' => $agent->slug]) }}" class="ia-carte-cta">
              {{ $surMesure ? 'Créer mon Agent personnalisé' : 'Demander cet Agent' }}
            </a>
          </div>
        </article>
      @endforeach
    </div>
  </div>
</section>

<section class="ia-etapes">
  <div class="ia-etapes-in">
    <div class="ia-titre-sect">
      <h2>De la demande à l'agent en service</h2>
      <p>Quatre étapes, et votre équipe garde la main à chacune.</p>
    </div>
    <div class="ia-etapes-grille">
      @foreach ([
        ['Vous décrivez votre besoin', "Le formulaire prend dix minutes. Vous dites ce que l'agent doit savoir faire."],
        ['GOVIBE conçoit l'."'".'agent', "Nous rédigeons ses réponses à partir de vos documents et de votre façon de travailler."],
        ['Vous testez avant tout le monde', "L'agent tourne sur un numéro de test. Vous corrigez ce qui ne vous convient pas."],
        ['Mise en service et suivi', "L'agent passe en ligne. Nous suivons ses conversations et l'ajustons dans le temps."],
      ] as $i => [$titre, $texte])
        <div class="ia-etape" data-n="0{{ $i + 1 }}">
          <h4>{{ $titre }}</h4>
          <p>{{ $texte }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

<section class="ia-tarifs">
  <div class="ia-tarifs-in">
    <div class="ia-titre-sect">
      <h2>Deux lignes, pas une</h2>
      <p>Un Agent IA n'est pas un logiciel qu'on achète une fois : il consomme de l'infrastructure tous les jours.</p>
    </div>

    <div class="ia-deux-frais">
      <div class="ia-frais">
        <i class="fas fa-screwdriver-wrench"></i>
        <h4>Installation et configuration</h4>
        <p>
          Frais unique. Conception de l'agent, rédaction de ses réponses,
          branchement de vos canaux et de vos outils, tests avec votre équipe.
        </p>
      </div>
      <div class="ia-frais">
        <i class="fas fa-arrows-rotate"></i>
        <h4>Service IA mensuel</h4>
        <p>
          Abonnement mensuel. Infrastructure, consommation IA, maintenance,
          corrections et support tant que l'agent est en service.
        </p>
      </div>
    </div>

    <p class="ia-note-prix">
      Le prix final peut varier selon les fonctionnalités, les intégrations,
      le volume d'utilisation et le niveau de personnalisation. Les montants
      affichés sont un point de départ : GOVIBE vous confirme le devis exact
      après avoir lu votre demande.
    </p>
  </div>
</section>

<section class="ia-final">
  <h2>Prêt à automatiser votre entreprise ?</h2>
  <p>Parlez-nous de votre besoin et GOVIBE vous proposera l'Agent IA adapté à votre activité.</p>
  <div class="ia-cta-row">
    <a href="{{ route('agents-ia.demande') }}" class="ia-btn">
      <i class="fas fa-robot"></i> Demander mon Agent IA
    </a>
    <a href="https://wa.me/50933988754" target="_blank" rel="noopener" class="ia-btn-ghost">
      <i class="fab fa-whatsapp"></i> Parler à GOVIBE
    </a>
  </div>
</section>

@endsection

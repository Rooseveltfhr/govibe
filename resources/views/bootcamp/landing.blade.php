@extends('layouts.public')
@section('title','GOVIBE AI Bootcamp 2026 — Maîtrisez l\'IA en pratique')

@push('head')
<style>
:root{--red:#DC2626;--dark:#0A0A0A;--gold:#D97706;--gre:#16A34A;--bg:#0D0D0D;--card:#141414;--bd:rgba(255,255,255,.08)}
.bc-hero{background:linear-gradient(135deg,#0D0D0D 0%,#1a0a0a 50%,#0D0D0D 100%);padding:100px 0 60px;position:relative;overflow:hidden}
.bc-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(220,38,38,.18),transparent)}
.bc-badge{display:inline-block;background:rgba(220,38,38,.15);border:1px solid rgba(220,38,38,.4);color:#f87171;font-size:13px;font-weight:700;padding:6px 16px;border-radius:999px;letter-spacing:.5px;margin-bottom:18px}
.bc-hero h1{font-family:'Anton',sans-serif;font-size:clamp(38px,7vw,76px);color:#fff;line-height:1.05;margin-bottom:20px}
.bc-hero h1 span{color:var(--red)}
.bc-hero p.sub{font-size:clamp(16px,2vw,20px);color:#ccc;max-width:580px;line-height:1.6;margin-bottom:36px}
.btn-red{display:inline-flex;align-items:center;gap:8px;background:var(--red);color:#fff;font-weight:700;font-size:16px;padding:14px 30px;border-radius:12px;text-decoration:none;border:0;cursor:pointer;transition:background .2s}
.btn-red:hover{background:#b91c1c}
.btn-outline-w{display:inline-flex;align-items:center;gap:8px;border:2px solid rgba(255,255,255,.3);color:#fff;font-weight:600;font-size:15px;padding:13px 26px;border-radius:12px;text-decoration:none;transition:border-color .2s}
.btn-outline-w:hover{border-color:#fff}
.bc-stats{display:flex;flex-wrap:wrap;gap:24px;margin-top:40px}
.bc-stat{text-align:center}.bc-stat .n{font-family:'Anton',sans-serif;font-size:32px;color:var(--red)}.bc-stat .l{font-size:13px;color:#888}
.section-dark{background:var(--dark);padding:80px 0}
.section-gray{background:#0D0D0D;padding:80px 0}
.sec-title{font-family:'Anton',sans-serif;font-size:clamp(28px,4vw,44px);color:#fff;margin-bottom:8px}
.sec-sub{color:#888;font-size:16px;margin-bottom:48px}
.benefit-card{background:var(--card);border:1px solid var(--bd);border-radius:16px;padding:28px 24px;transition:border-color .2s}
.benefit-card:hover{border-color:rgba(220,38,38,.4)}
.benefit-card .icon{width:52px;height:52px;border-radius:12px;background:rgba(220,38,38,.12);display:flex;align-items:center;justify-content:center;margin-bottom:16px;font-size:24px}
.benefit-card h4{color:#fff;font-size:16px;font-weight:700;margin-bottom:8px}
.benefit-card p{color:#888;font-size:14px;line-height:1.6}
.module-card{background:var(--card);border:1px solid var(--bd);border-radius:20px;padding:28px;display:flex;flex-direction:column;gap:16px;transition:transform .2s,border-color .2s}
.module-card:hover{transform:translateY(-4px);border-color:rgba(220,38,38,.4)}
.module-num{font-size:12px;font-weight:700;color:var(--red);letter-spacing:1px}
.module-card h3{font-family:'Anton',sans-serif;font-size:22px;color:#fff;line-height:1.2}
.module-card .price-tag{font-size:28px;font-weight:800;color:#fff}.module-card .price-tag span{font-size:14px;color:#888;font-weight:400}
.module-card ul{list-style:none;display:flex;flex-direction:column;gap:6px}
.module-card ul li{font-size:13px;color:#bbb;display:flex;align-items:flex-start;gap:8px}
.module-card ul li::before{content:'✓';color:var(--gre);font-weight:700;flex-shrink:0;margin-top:1px}
.module-card .btn-red{width:100%;justify-content:center;margin-top:auto}
.premium-box{background:linear-gradient(135deg,#1a0505,#200808);border:2px solid var(--red);border-radius:24px;padding:48px 40px;position:relative;overflow:hidden}
.premium-box::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;background:radial-gradient(circle,rgba(220,38,38,.25),transparent);border-radius:50%}
.best-badge{position:absolute;top:24px;right:24px;background:var(--gold);color:#000;font-weight:800;font-size:12px;padding:6px 14px;border-radius:999px;letter-spacing:.5px}
.premium-price{font-family:'Anton',sans-serif;font-size:clamp(48px,8vw,80px);color:#fff;line-height:1}
.premium-price sup{font-size:32px;color:var(--red)}
.premium-price sub{font-size:16px;color:#888}
.strike{text-decoration:line-through;color:#555;font-size:18px}
.premium-feat{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin:28px 0}
.premium-feat li{list-style:none;color:#ddd;font-size:14px;display:flex;align-items:center;gap:8px}
.premium-feat li::before{content:'✓';color:var(--gre);font-weight:800}
.pay-card{background:var(--card);border:1px solid var(--bd);border-radius:16px;padding:24px}
.pay-card .pay-logo{font-weight:800;font-size:18px;color:#fff;margin-bottom:12px}
.pay-card .pay-info{font-size:13px;color:#bbb;line-height:1.6}
.pay-card .pay-acct{font-size:15px;font-weight:700;color:var(--red);margin:8px 0}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:13px;font-weight:600;color:#ccc;margin-bottom:6px}
.form-input{width:100%;background:#1a1a1a;border:1.5px solid var(--bd);border-radius:10px;padding:12px 16px;color:#fff;font-size:15px;outline:none;transition:border-color .2s}
.form-input:focus{border-color:var(--red)}
.form-input option{background:#1a1a1a}
textarea.form-input{resize:vertical;min-height:90px}
.price-display{background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.25);border-radius:10px;padding:12px 16px;text-align:center}
.price-display .amount{font-size:28px;font-weight:800;color:#fff}
.price-display .note{font-size:12px;color:#888;margin-top:4px}
.alert-success{background:rgba(22,163,74,.12);border:1px solid rgba(22,163,74,.3);color:#86efac;padding:16px 20px;border-radius:12px;margin-bottom:24px}
</style>
@endpush

@section('content')

{{-- ── HERO ──────────────────────────────────────────────────────────── --}}
<section class="bc-hero">
    <div class="container mx-auto px-4 max-w-6xl">
        @if(session('success'))
        <div class="alert-success mb-8"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</div>
        @endif
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div>
                <div class="bc-badge">🚀 GOVIBE ACADEMY · ÉDITION 2026</div>
                <h1>GOVIBE AI<br>Bootcamp <span>2026</span></h1>
                <p class="sub">Maîtrisez l'Intelligence Artificielle grâce à une formation <strong style="color:#fff">100% pratique</strong> — de zéro à la création d'AI Agents en 7 modules intensifs.</p>
                <div class="flex flex-wrap gap-4">
                    <a href="#inscription" class="btn-red"><i class="fas fa-rocket"></i> S'inscrire maintenant</a>
                    <a href="#programme" class="btn-outline-w"><i class="fas fa-file-alt"></i> Voir le programme</a>
                </div>
                <div class="bc-stats">
                    <div class="bc-stat"><div class="n">7</div><div class="l">Modules</div></div>
                    <div class="bc-stat"><div class="n">100%</div><div class="l">Pratique</div></div>
                    <div class="bc-stat"><div class="n">100$</div><div class="l">Tout inclus</div></div>
                    <div class="bc-stat"><div class="n">🏆</div><div class="l">Certificat</div></div>
                </div>
            </div>
            <div class="relative">
                {{-- Visuel IA hero --}}
                <div style="background:linear-gradient(135deg,#1a0a0a,#0a0a1a);border:1px solid rgba(220,38,38,.3);border-radius:24px;padding:40px;text-align:center;position:relative">
                    <div style="font-size:80px;line-height:1;margin-bottom:16px">🤖</div>
                    <div style="font-family:'Anton',sans-serif;font-size:28px;color:#fff">Intelligence<br><span style="color:var(--red)">Artificielle</span></div>
                    <div style="margin-top:20px;display:flex;flex-wrap:wrap;gap:8px;justify-content:center">
                        @foreach(['ChatGPT','Claude','Gemini','Midjourney','Stable Diffusion','n8n','Make','Zapier'] as $tool)
                        <span style="background:rgba(255,255,255,.07);color:#ccc;font-size:11px;padding:4px 12px;border-radius:999px">{{ $tool }}</span>
                        @endforeach
                    </div>
                    {{-- Vidéo placeholder --}}
                    <div style="margin-top:24px;background:#000;border-radius:12px;padding:20px;cursor:pointer;border:1px solid rgba(255,255,255,.1)" onclick="document.getElementById('videoModal').style.display='flex'">
                        <div style="width:48px;height:48px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 8px"><i class="fas fa-play" style="color:#fff;margin-left:3px"></i></div>
                        <div style="color:#888;font-size:13px">Regarder la présentation</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── POURQUOI ────────────────────────────────────────────────────── --}}
<section class="section-dark" id="pourquoi">
    <div class="container mx-auto px-4 max-w-6xl">
        <div class="text-center mb-12">
            <h2 class="sec-title">Pourquoi ce Bootcamp ?</h2>
            <p class="sec-sub">6 raisons de rejoindre la première promotion GOVIBE AI Bootcamp</p>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            @foreach([
                ['🎯','Formation 100% pratique','Pas de théorie inutile. Chaque module = projets réels, exercices concrets, résultats mesurables.'],
                ['🛠️','Projets réels','Vous repartez avec des créations concrètes : chatbots, assistants IA, agents automatisés, contenus générés.'],
                ['🏆','Certificat GOVIBE','Un certificat reconnu dans l\'écosystème GOVIBE, valide pour CV, portfolio et opportunités professionnelles.'],
                ['🤝','Accompagnement personnalisé','Nos formateurs vous suivent tout au long du parcours. Questions, feedback, orientations — nous sommes là.'],
                ['👥','Communauté privée','Rejoignez un groupe exclusif de professionnels haïtiens qui maîtrisent l\'IA. Networking, opportunités, collaborations.'],
                ['💡','Adapté à tous les métiers','Enseignants, entrepreneurs, ONG, RH, santé, commerce — l\'IA s\'applique dans tous les secteurs.'],
            ] as [$icon, $title, $desc])
            <div class="benefit-card">
                <div class="icon">{{ $icon }}</div>
                <h4>{{ $title }}</h4>
                <p>{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── PROGRAMME ───────────────────────────────────────────────────── --}}
<section class="section-gray" id="programme">
    <div class="container mx-auto px-4 max-w-6xl">
        <div class="text-center mb-12">
            <h2 class="sec-title">Le Programme Complet</h2>
            <p class="sec-sub">7 modules intensifs · De débutant à créateur d'AI Agents</p>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

            @php
            $modules_data = [
                ['n'=>1,'title'=>'Comprendre l\'IA','color'=>'#DC2626','items'=>['Comprendre l\'IA et son histoire','Types d\'IA : ML, Deep Learning, LLM','ChatGPT, Gemini, Claude — comparatif','Prompt Engineering pratique','Utilisation professionnelle de l\'IA']],
                ['n'=>2,'title'=>'IA Générative','color'=>'#9333EA','items'=>['Génération d\'images & flyers','Création de logos et branding complet','Mockups & shooting photo IA','Génération et montage vidéo IA','Vidéos publicitaires & réseaux sociaux']],
                ['n'=>3,'title'=>'Productivité avec l\'IA','color'=>'#2563EB','items'=>['Rédiger et résumer des documents PDF','Générer des présentations automatiquement','Automatiser emails et tâches','IA pour Excel & recherche intelligente','Organisation et planification IA']],
                ['n'=>4,'title'=>'IA pour les Métiers','color'=>'#D97706','items'=>['Applications : Enseignants & Étudiants','Entrepreneurs & ONG','Marketing, RH & Santé','Commerce, Église & Agriculture','Cas pratiques par secteur']],
                ['n'=>5,'title'=>'Création de Chatbots IA','color'=>'#059669','items'=>['Chatbot professionnel avec base de connaissances','FAQ intelligente & support automatique','Chatbot Web (intégration site)','Chatbot WhatsApp & Messenger','Déploiement et maintenance']],
                ['n'=>6,'title'=>'Création d\'Assistants IA','color'=>'#0891B2','items'=>['Assistant personnel & entreprise','Assistant RH & commercial','Assistant marketing & administratif','Personnalisation avancée','Intégration dans vos outils existants']],
                ['n'=>7,'title'=>'Création d\'AI Agents','color'=>'#DC2626','items'=>['AI Agent Entreprise & ONG','AI Agent Éducation & Santé','AI Agent Comptable & Commercial','Automatisation intelligente multi-agents','Projet final : votre AI Agent déployé']],
            ];
            @endphp

            @foreach($modules_data as $m)
            <div class="module-card">
                <div class="module-num">MODULE {{ $m['n'] }}</div>
                <h3>{{ $m['title'] }}</h3>
                <div style="height:3px;background:{{ $m['color'] }};border-radius:2px;width:48px"></div>
                <ul>
                    @foreach($m['items'] as $item)
                    <li>{{ $item }}</li>
                    @endforeach
                </ul>
                <div class="price-tag">3 500 <span>HTG / module</span></div>
                <a href="#inscription" class="btn-red" onclick="document.getElementById('sel-module').value='module_{{ $m['n'] }}'">
                    <i class="fas fa-arrow-right"></i> S'inscrire à ce module
                </a>
            </div>
            @endforeach

        </div>
    </div>
</section>

{{-- ── PREMIUM ─────────────────────────────────────────────────────── --}}
<section class="section-dark" id="premium">
    <div class="container mx-auto px-4 max-w-4xl">
        <div class="premium-box">
            <div class="best-badge">⭐ MEILLEURE OFFRE</div>
            <div style="color:#888;font-size:13px;font-weight:700;letter-spacing:1px;margin-bottom:12px">BOOTCAMP PREMIUM</div>
            <h2 style="font-family:'Anton',sans-serif;font-size:clamp(28px,4vw,40px);color:#fff;margin-bottom:20px">Tous les 7 modules.<br>Tout inclus. Un seul prix.</h2>
            <div style="display:flex;align-items:baseline;gap:16px;flex-wrap:wrap;margin-bottom:8px">
                <div class="premium-price"><sup>$</sup>100<sub> USD</sub></div>
                <div>
                    <div class="strike">≈ 24 500 HTG (modules séparés)</div>
                    <div style="color:#22c55e;font-size:14px;font-weight:700">Économisez plus de 50% !</div>
                </div>
            </div>
            <ul class="premium-feat">
                @foreach(['Les 7 modules complets','Tous les supports de cours','Tous les ateliers pratiques','Tous les projets guidés','Certificat GOVIBE officiel','Accompagnement personnalisé','Communauté privée exclusive','Projet final déployé','Support prioritaire','Accès à vie aux mises à jour'] as $feat)
                <li>{{ $feat }}</li>
                @endforeach
            </ul>
            <a href="#inscription" class="btn-red" style="font-size:18px;padding:18px 40px" onclick="document.getElementById('sel-module').value='premium'">
                <i class="fas fa-rocket mr-2"></i>Je veux le Bootcamp Premium
            </a>
        </div>
    </div>
</section>

{{-- ── MOYENS DE PAIEMENT ──────────────────────────────────────────── --}}
<section class="section-gray" id="paiement">
    <div class="container mx-auto px-4 max-w-6xl">
        <div class="text-center mb-12">
            <h2 class="sec-title">Comment Payer ?</h2>
            <p class="sec-sub">Choisissez votre moyen de paiement préféré — validation manuelle sous 24h</p>
        </div>
        <div class="grid md:grid-cols-3 gap-5">
            @foreach($paymentSettings as $key => $pay)
            <div class="pay-card">
                <div class="pay-logo">{{ ['moncash'=>'🟡','natcash'=>'🔵','paypal'=>'🔷','zelle'=>'🟣','unibank'=>'🏦','chase'=>'🏦'][$key] ?? '💳' }} {{ $pay['label'] }}</div>
                <div class="pay-info">{{ $pay['instructions'] }}</div>
                <div class="pay-acct">{{ $pay['account'] }}</div>
                <div style="font-size:12px;color:#666">{{ $pay['name'] }}</div>
            </div>
            @endforeach
        </div>
        <p style="text-align:center;color:#666;font-size:13px;margin-top:24px">
            <i class="fas fa-info-circle mr-1"></i>
            Après paiement, remplissez le formulaire ci-dessous et téléversez votre preuve de paiement.
            Confirmation par email/WhatsApp sous 24h.
        </p>
    </div>
</section>

{{-- ── FORMULAIRE D'INSCRIPTION ────────────────────────────────────── --}}
<section class="section-dark" id="inscription">
    <div class="container mx-auto px-4 max-w-3xl">
        <div class="text-center mb-12">
            <h2 class="sec-title">S'inscrire au Bootcamp</h2>
            <p class="sec-sub">Remplissez le formulaire · Téléversez votre preuve de paiement · Recevez votre confirmation</p>
        </div>

        @if($errors->any())
        <div style="background:rgba(220,38,38,.1);border:1px solid rgba(220,38,38,.4);color:#fca5a5;padding:16px;border-radius:12px;margin-bottom:24px">
            <ul style="margin:0;padding-left:20px">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('bootcamp.register') }}" method="POST" enctype="multipart/form-data"
              style="background:var(--card);border:1px solid var(--bd);border-radius:20px;padding:40px">
            @csrf
            <div class="grid md:grid-cols-2 gap-x-6">
                <div class="form-group">
                    <label>Nom complet *</label>
                    <input type="text" name="full_name" class="form-input" value="{{ old('full_name') }}" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-input" value="{{ old('email') }}" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="phone" class="form-input" value="{{ old('phone') }}">
                </div>
                <div class="form-group">
                    <label>Pays</label>
                    <input type="text" name="country" class="form-input" value="{{ old('country','Haïti') }}">
                </div>
                <div class="form-group">
                    <label>Profession</label>
                    <input type="text" name="profession" class="form-input" value="{{ old('profession') }}" placeholder="ex: Enseignant, Entrepreneur…">
                </div>
                <div class="form-group">
                    <label>Organisation / École</label>
                    <input type="text" name="organization" class="form-input" value="{{ old('organization') }}">
                </div>
            </div>

            <div class="form-group">
                <label>Module choisi *</label>
                <select name="module_choice" id="sel-module" class="form-input" onchange="updatePrice(this.value)" required>
                    @foreach($modules as $key => $mod)
                    <option value="{{ $key }}" {{ old('module_choice','premium')===$key?'selected':'' }}>
                        {{ $mod['label'] }} —
                        @if(isset($mod['price_usd']))
                            ${{ $mod['price_usd'] }} USD
                        @else
                            {{ number_format($mod['price_htg'],0,'.',',') }} HTG
                        @endif
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="price-display mb-5" id="price-display">
                <div class="amount" id="price-amount">$100 USD</div>
                <div class="note">Bootcamp Premium · Tous modules inclus</div>
            </div>

            <div class="form-group">
                <label>Moyen de paiement *</label>
                <select name="payment_method" class="form-input" required>
                    @foreach($paymentSettings as $key => $pay)
                    <option value="{{ $key }}" {{ old('payment_method')===$key?'selected':'' }}>{{ $pay['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Motivation <span style="color:#666">(optionnel)</span></label>
                <textarea name="motivation" class="form-input" placeholder="Pourquoi voulez-vous apprendre l'IA ?">{{ old('motivation') }}</textarea>
            </div>

            <div class="form-group">
                <label>Preuve de paiement <span style="color:#666">(photo ou PDF — max 5 Mo)</span></label>
                <input type="file" name="payment_proof" class="form-input" accept=".jpg,.jpeg,.png,.pdf"
                       style="padding:10px 16px;cursor:pointer">
            </div>

            <button type="submit" class="btn-red w-full justify-center" style="font-size:17px;padding:16px">
                <i class="fas fa-paper-plane mr-2"></i>Envoyer mon inscription
            </button>
            <p style="text-align:center;color:#555;font-size:12px;margin-top:12px">
                Confirmation par email et WhatsApp sous 24 heures ouvrables.
            </p>
        </form>
    </div>
</section>

{{-- ── VIDEO MODAL ─────────────────────────────────────────────────── --}}
<div id="videoModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#000;border-radius:16px;padding:24px;max-width:700px;width:94%;position:relative">
        <button onclick="document.getElementById('videoModal').style.display='none'" style="position:absolute;top:12px;right:12px;background:rgba(255,255,255,.15);border:0;color:#fff;border-radius:50%;width:32px;height:32px;cursor:pointer;font-size:18px">×</button>
        <div style="aspect-ratio:16/9;background:#111;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#666">
            <div style="text-align:center"><div style="font-size:48px">🎬</div><p style="margin-top:8px">Vidéo de présentation<br><small>À intégrer depuis YouTube / Vimeo</small></p></div>
        </div>
    </div>
</div>

<script>
const PRICES = @json(collect($modules)->map(fn($m) => isset($m['price_usd']) ? '$'.$m['price_usd'].' USD' : number_format($m['price_htg'],0,'.',',').' HTG')->toArray());
const LABELS = @json(collect($modules)->pluck('label')->toArray());
function updatePrice(val){
    document.getElementById('price-amount').textContent = PRICES[val] || '';
    document.getElementById('price-display').querySelector('.note').textContent = LABELS[val] || '';
}
</script>
@endsection

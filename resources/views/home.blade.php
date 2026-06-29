<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GOVIBE Innovation Hub — Haïti</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .hero-bg { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-6px); box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        .nav-blur { backdrop-filter: blur(12px); background: rgba(15,23,42,0.92); }
        @keyframes fadeUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
        .fade-up { animation: fadeUp 0.7s ease forwards; }
        .d1 { animation-delay:0.1s; opacity:0; }
        .d2 { animation-delay:0.2s; opacity:0; }
        .d3 { animation-delay:0.3s; opacity:0; }
        .d4 { animation-delay:0.4s; opacity:0; }
        .red-glow { box-shadow: 0 0 30px rgba(220,38,38,0.4); }
        .gradient-text { background: linear-gradient(135deg, #DC2626, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .service-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(10px); }
        .service-card:hover { background: rgba(255,255,255,0.07); border-color: rgba(220,38,38,0.3); }
    </style>
</head>
<body class="bg-slate-950 text-white">

<!-- Navbar -->
<nav class="nav-blur fixed top-0 w-full z-50 border-b border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <a href="/" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 red-glow" style="background:#DC2626">
                    <i class="bi bi-lightning-charge-fill text-white text-base"></i>
                </div>
                <div>
                    <p class="font-black text-xl leading-none tracking-wide">
                        <span style="color:#3B82F6">G</span><span style="color:#DC2626">O</span><span style="color:#F59E0B">V</span><span style="color:#22C55E">I</span><span style="color:#3B82F6">BE</span>
                    </p>
                    <p class="text-slate-400 text-xs tracking-widest uppercase leading-none">Innovation Hub</p>
                </div>
            </a>

            <!-- Nav Links -->
            <div class="hidden md:flex items-center gap-6 text-sm text-slate-300">
                <a href="#services" class="hover:text-white transition-colors">Services</a>
                <a href="#about" class="hover:text-white transition-colors">À propos</a>
                <a href="#contact" class="hover:text-white transition-colors">Contact</a>
                <a href="{{ route('inscription.create') }}" class="hover:text-white transition-colors text-yellow-400">Academy</a>
            </div>

            <!-- CTA -->
            <div class="flex items-center gap-3">
                <a href="{{ route('erp.login') }}" class="hidden sm:block text-slate-400 hover:text-white text-sm transition-colors">
                    <i class="bi bi-grid-fill mr-1"></i>ERP
                </a>
                <a href="{{ route('inscription.create') }}" class="bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all hover:shadow-lg hover:shadow-red-600/30">
                    S'inscrire
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Hero -->
<section class="hero-bg min-h-screen flex items-center pt-16 relative overflow-hidden">
    <!-- Background circles -->
    <div class="absolute top-20 right-0 w-96 h-96 rounded-full opacity-10" style="background:radial-gradient(circle,#DC2626,transparent)"></div>
    <div class="absolute bottom-0 left-0 w-80 h-80 rounded-full opacity-10" style="background:radial-gradient(circle,#3B82F6,transparent)"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 w-full">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div>
                <div class="inline-flex items-center gap-2 bg-red-600/10 border border-red-600/20 rounded-full px-4 py-1.5 mb-6 fade-up d1">
                    <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                    <span class="text-red-400 text-sm font-medium">Hub d'Innovation #1 en Haïti</span>
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black leading-tight mb-6 fade-up d2">
                    Bienvenue chez<br>
                    <span class="font-black text-5xl sm:text-6xl lg:text-7xl">
                        <span style="color:#3B82F6">G</span><span style="color:#DC2626">O</span><span style="color:#F59E0B">V</span><span style="color:#22C55E">I</span><span style="color:#3B82F6">BE</span>
                    </span>
                    <br>
                    <span class="text-3xl sm:text-4xl text-slate-300 font-light">Innovation Hub</span>
                </h1>

                <p class="text-slate-400 text-lg leading-relaxed mb-8 max-w-lg fade-up d3">
                    Votre écosystème numérique complet en Haïti — formation, développement logiciel, coworking, intelligence artificielle, médias et bien plus.
                </p>

                <div class="flex flex-wrap gap-4 fade-up d4">
                    <a href="{{ route('inscription.create') }}" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-500 text-white px-6 py-3 rounded-xl font-semibold transition-all hover:shadow-lg hover:shadow-red-600/40">
                        <i class="bi bi-mortarboard-fill"></i>
                        GOVIBE Academy
                    </a>
                    <a href="#services" class="inline-flex items-center gap-2 border border-white/20 hover:border-white/40 text-white px-6 py-3 rounded-xl font-semibold transition-all">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                        Nos Services
                    </a>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-6 mt-12 pt-8 border-t border-white/10 fade-up d4">
                    @foreach([['14+', 'Unités Business'], ['500+', 'Clients Servis'], ['2019', 'Fondée']] as [$num, $label])
                    <div>
                        <p class="text-2xl font-black text-white">{{ $num }}</p>
                        <p class="text-slate-400 text-xs mt-0.5">{{ $label }}</p>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Logo grande -->
            <div class="hidden lg:flex items-center justify-center">
                <div class="relative">
                    <div class="w-72 h-72 rounded-full flex items-center justify-center" style="background:rgba(220,38,38,0.08); border: 2px solid rgba(220,38,38,0.15)">
                        <div class="w-52 h-52 rounded-full flex items-center justify-center red-glow" style="background:rgba(220,38,38,0.15); border: 2px solid rgba(220,38,38,0.3)">
                            <div>
                                <p class="font-black text-5xl leading-none text-center">
                                    <span style="color:#3B82F6">G</span><span style="color:#DC2626">O</span><span style="color:#F59E0B">V</span><span style="color:#22C55E">I</span><span style="color:#3B82F6">BE</span>
                                </p>
                                <p class="text-slate-300 text-xs text-center tracking-widest uppercase mt-2">Innovation Hub</p>
                                <div class="flex justify-center mt-3">
                                    <div class="w-10 h-10 rounded-full bg-red-600 flex items-center justify-center red-glow">
                                        <i class="bi bi-lightning-charge-fill text-white text-lg"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Services / Business Units -->
<section id="services" class="py-20 bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14">
            <span class="text-red-500 text-sm font-semibold uppercase tracking-widest">Ce que nous faisons</span>
            <h2 class="text-3xl sm:text-4xl font-black text-white mt-2">Nos Unités Business</h2>
            <p class="text-slate-400 mt-3 max-w-xl mx-auto">Un écosystème complet pour accélérer votre croissance numérique</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @php
            $units = [
                ['GOVIBE Coworking',      'bi-building',              '#3B82F6', 'Espaces de travail modernes et partagés'],
                ['GOVIBE Academy',        'bi-mortarboard-fill',      '#DC2626', 'Formations professionnelles en tech'],
                ['GOVIBE AI Lab',         'bi-cpu-fill',              '#8B5CF6', 'Intelligence artificielle et ML'],
                ['GOVIBE Media',          'bi-camera-video-fill',     '#EC4899', 'Production audiovisuelle et contenu'],
                ['Digital Services',      'bi-globe2',                '#06B6D4', 'Marketing digital et SEO'],
                ['Software Dev',          'bi-code-slash',            '#22C55E', 'Développement de logiciels sur mesure'],
                ['Mobile Dev',            'bi-phone-fill',            '#F59E0B', 'Applications iOS et Android'],
                ['Website Dev',           'bi-window-stack',          '#6366F1', 'Création de sites web professionnels'],
                ['Cybersecurity',         'bi-shield-fill-check',     '#DC2626', 'Sécurité informatique et audit'],
                ['AI Agents',             'bi-robot',                 '#7C3AED', 'Agents IA et automatisation'],
                ['Automation',            'bi-gear-wide-connected',   '#0891B2', 'Automatisation de processus métier'],
                ['Startup Incubator',     'bi-rocket-takeoff-fill',   '#EA580C', 'Incubation et accélération de startups'],
                ['Events',                'bi-calendar-event-fill',   '#DB2777', 'Événements et conférences tech'],
                ['Consulting',            'bi-briefcase-fill',        '#64748B', 'Conseil stratégique digital'],
            ];
            @endphp

            @foreach($units as [$name, $icon, $color, $desc])
            <div class="service-card rounded-2xl p-5 card-hover cursor-pointer">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-4" style="background:{{ $color }}20">
                    <i class="bi {{ $icon }} text-xl" style="color:{{ $color }}"></i>
                </div>
                <h3 class="font-bold text-white text-sm mb-1">{{ $name }}</h3>
                <p class="text-slate-400 text-xs leading-relaxed">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Academy CTA -->
<section class="py-20 bg-slate-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-3xl overflow-hidden relative" style="background:linear-gradient(135deg,#7c0000,#DC2626,#b91c1c)">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-0 right-0 w-64 h-64 rounded-full" style="background:rgba(255,255,255,0.3)"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 rounded-full" style="background:rgba(255,255,255,0.2)"></div>
            </div>
            <div class="relative p-10 sm:p-16 text-center">
                <i class="bi bi-mortarboard-fill text-5xl text-white/80 mb-4 block"></i>
                <h2 class="text-3xl sm:text-4xl font-black text-white mb-4">GOVIBE Academy</h2>
                <p class="text-red-100 text-lg mb-8 max-w-xl mx-auto">Formations professionnelles en développement web, mobile, IA et plus. Inscrivez-vous dès maintenant.</p>
                <a href="{{ route('inscription.create') }}" class="inline-flex items-center gap-2 bg-white text-red-600 px-8 py-4 rounded-xl font-bold text-lg hover:bg-red-50 transition-all hover:shadow-2xl">
                    <i class="bi bi-pencil-fill"></i>
                    S'inscrire à une Formation
                </a>
            </div>
        </div>
    </div>
</section>

<!-- About -->
<section id="about" class="py-20 bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div>
                <span class="text-red-500 text-sm font-semibold uppercase tracking-widest">À propos</span>
                <h2 class="text-3xl sm:text-4xl font-black text-white mt-2 mb-6">Qui sommes-nous ?</h2>
                <p class="text-slate-400 leading-relaxed mb-6">
                    <strong class="text-white">GOVIBE Innovation Hub</strong> est le premier hub technologique d'Haïti regroupant sous un même toit tous les services numériques dont les entreprises, startups et professionnels ont besoin.
                </p>
                <p class="text-slate-400 leading-relaxed mb-8">
                    De la formation professionnelle au développement d'applications mobiles, en passant par l'intelligence artificielle et la cybersécurité — nous accompagnons votre transformation digitale de A à Z.
                </p>
                <div class="space-y-3">
                    @foreach(['Formation professionnelle certifiée', 'Développement logiciel sur mesure', 'Espace coworking moderne', 'Incubation de startups', 'Intelligence artificielle et automatisation', 'Production média et contenu digital'] as $item)
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full bg-red-600/20 flex items-center justify-center flex-shrink-0">
                            <i class="bi bi-check text-red-500 text-xs"></i>
                        </div>
                        <span class="text-slate-300 text-sm">{{ $item }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                @foreach([
                    ['#DC2626','bi-lightning-charge-fill','Innovation','Toujours à la pointe de la technologie'],
                    ['#3B82F6','bi-people-fill','Communauté','Un réseau de professionnels engagés'],
                    ['#22C55E','bi-graph-up-arrow','Croissance','Des résultats mesurables pour votre business'],
                    ['#F59E0B','bi-shield-check','Qualité','Des standards d\'excellence dans chaque projet'],
                ] as [$color, $icon, $title, $desc])
                <div class="service-card rounded-2xl p-6">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background:{{ $color }}20">
                        <i class="bi {{ $icon }}" style="color:{{ $color }}"></i>
                    </div>
                    <h3 class="font-bold text-white text-sm mb-1">{{ $title }}</h3>
                    <p class="text-slate-400 text-xs">{{ $desc }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<!-- Contact -->
<section id="contact" class="py-20 bg-slate-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="text-red-500 text-sm font-semibold uppercase tracking-widest">Contactez-nous</span>
        <h2 class="text-3xl sm:text-4xl font-black text-white mt-2 mb-4">Prêt à commencer ?</h2>
        <p class="text-slate-400 mb-10 max-w-lg mx-auto">Discutons de votre projet. Notre équipe est disponible pour vous accompagner.</p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="mailto:govibeht@gmail.com" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white px-6 py-3 rounded-xl font-medium transition-all border border-white/10">
                <i class="bi bi-envelope-fill text-red-500"></i>
                govibeht@gmail.com
            </a>
            <a href="https://wa.me/50937000000" class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-500 text-white px-6 py-3 rounded-xl font-medium transition-all">
                <i class="bi bi-whatsapp"></i>
                WhatsApp
            </a>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="bg-slate-950 border-t border-white/5 py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-600 flex items-center justify-center">
                    <i class="bi bi-lightning-charge-fill text-white text-xs"></i>
                </div>
                <div>
                    <p class="font-black text-sm">
                        <span style="color:#3B82F6">G</span><span style="color:#DC2626">O</span><span style="color:#F59E0B">V</span><span style="color:#22C55E">I</span><span style="color:#3B82F6">BE</span>
                        <span class="text-slate-400 font-light ml-1 text-xs">Innovation Hub</span>
                    </p>
                    <p class="text-slate-500 text-xs">© {{ date('Y') }} Tous droits réservés</p>
                </div>
            </div>
            <div class="flex gap-6 text-slate-400 text-sm">
                <a href="{{ route('inscription.create') }}" class="hover:text-white transition-colors">Academy</a>
                <a href="{{ route('admin.login') }}" class="hover:text-white transition-colors">Admin</a>
                <a href="{{ route('erp.login') }}" class="hover:text-white transition-colors">ERP</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>

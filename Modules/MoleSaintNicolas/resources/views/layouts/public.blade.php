<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Môle-Saint-Nicolas — Découvrez le berceau d\'Haïti')</title>
    <meta name="description" content="@yield('meta_description', "Môle-Saint-Nicolas, Haïti : histoire, territoire, sites historiques, hébergements et activités touristiques.")">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-msn-sea-950 text-msn-sand-100 font-sans antialiased">
    @php
        $navLinks = [
            ['route' => 'histoire.index', 'label' => 'Histoire'],
            ['route' => 'lieux-historiques.index', 'label' => 'Lieux historiques'],
            ['route' => 'territoire.index', 'label' => 'Sections communales'],
            ['route' => 'etablissements.index', 'label' => 'Établissements'],
            ['route' => 'actualites.index', 'label' => 'Actualités'],
            ['route' => 'projets.index', 'label' => 'Projets communautaires'],
        ];
    @endphp

    <header class="sticky top-0 z-50 bg-msn-sea-950/95 text-msn-sand-100 backdrop-blur" x-data="{ open: false }">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-wide">
                Môle-Saint-Nicolas
            </a>

            <div class="hidden items-center gap-6 text-sm md:flex">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="{{ request()->routeIs($link['route']) ? 'text-msn-gold-400' : 'hover:text-msn-gold-400' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>

            <button type="button" @click="open = !open" class="text-msn-sand-100 md:hidden" aria-label="Ouvrir le menu">
                <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display: none;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </nav>

        <div x-show="open" @click.outside="open = false" class="border-t border-msn-sand-100/10 px-4 pb-4 md:hidden" style="display: none;">
            <div class="flex flex-col gap-1 pt-2 text-sm">
                @foreach ($navLinks as $link)
                    <a href="{{ route($link['route']) }}"
                       class="rounded-lg px-3 py-2 {{ request()->routeIs($link['route']) ? 'bg-msn-sea-900 text-msn-gold-400' : 'hover:bg-msn-sea-900' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="bg-msn-sea-950 text-msn-sand-200">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-4 lg:px-8">
            <div>
                <p class="text-lg font-semibold text-msn-sand-100">Môle-Saint-Nicolas</p>
                <p class="mt-3 text-sm text-msn-sand-200/80">
                    La porte historique d'Haïti — histoire, territoire, patrimoine et tourisme
                    réunis dans une seule plateforme, alimentée et vérifiée progressivement par
                    l'équipe éditoriale locale.
                </p>
            </div>

            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-msn-gold-400">Navigation</p>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($navLinks as $link)
                        <li><a href="{{ route($link['route']) }}" class="hover:text-msn-gold-400">{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-msn-gold-400">Informations</p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('carte.index') }}" class="hover:text-msn-gold-400">Carte interactive</a></li>
                    <li><a href="{{ route('pages.about') }}" class="hover:text-msn-gold-400">À propos de la plateforme</a></li>
                    <li><a href="{{ route('pages.legal') }}" class="hover:text-msn-gold-400">Mentions légales</a></li>
                    <li><a href="{{ route('admin.login') }}" class="hover:text-msn-gold-400">Espace administrateur</a></li>
                </ul>
            </div>

            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-msn-gold-400">Contact</p>
                <ul class="mt-4 space-y-2 text-sm text-msn-sand-200/80">
                    <li>Môle-Saint-Nicolas, Nord-Ouest, Haïti</li>
                    <li>[Information à compléter — téléphone/WhatsApp]</li>
                    <li>[Information à compléter — email de contact]</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-msn-sand-100/10">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-msn-sand-200/70 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p>&copy; {{ date('Y') }} Môle-Saint-Nicolas. Plateforme en construction — contenu progressivement enrichi.</p>
                <p>Contenu marqué « à vérifier » tant qu'il n'a pas été confirmé par l'équipe éditoriale.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

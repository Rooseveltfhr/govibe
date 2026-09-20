<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Môle-Saint-Nicolas — Découvrez le berceau d\'Haïti')</title>
    <meta name="description" content="@yield('meta_description', "Môle-Saint-Nicolas, Haïti : histoire, territoire, sites historiques, hébergements et activités touristiques.")">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-msn-sand-100 text-msn-sea-950 font-sans antialiased">
    @php
        $navLinksBefore = [
            ['route' => 'histoire.index', 'label' => 'Histoire'],
            ['route' => 'lieux-historiques.index', 'label' => 'Lieux historiques'],
        ];
        $navLinksAfter = [
            ['route' => 'etablissements.index', 'label' => 'Établissements'],
            ['route' => 'actualites.index', 'label' => 'Actualités'],
            ['route' => 'projets.index', 'label' => 'Projets communautaires'],
            ['route' => 'pages.about', 'label' => 'À propos'],
            ['route' => 'contact.show', 'label' => 'Contact'],
        ];
        $moleCommune = $navArrondissement?->communes->firstWhere('slug', 'mole-saint-nicolas');
        $autresCommunes = $navArrondissement?->communes->reject(fn ($c) => $c->slug === 'mole-saint-nicolas') ?? collect();
    @endphp

    <header class="sticky top-0 z-50 bg-msn-sea-950/95 text-msn-sand-100 backdrop-blur" x-data="{ open: false }">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-wide">
                Môle-Saint-Nicolas
            </a>

            <div class="hidden items-center gap-6 overflow-x-auto text-sm md:flex">
                @foreach ($navLinksBefore as $link)
                    <a href="{{ route($link['route']) }}"
                       class="whitespace-nowrap {{ request()->routeIs($link['route']) ? 'text-msn-gold-400' : 'hover:text-msn-gold-400' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach

                <div x-data="{ open: false, top: 0, left: 0 }">
                    <button type="button" x-ref="arrondissementBtn"
                            @click="const r = $refs.arrondissementBtn.getBoundingClientRect(); top = r.bottom + 8; left = r.left; open = !open"
                            @click.outside="open = false"
                            class="flex items-center gap-1 whitespace-nowrap {{ request()->routeIs('territoire.*') ? 'text-msn-gold-400' : 'hover:text-msn-gold-400' }}">
                        Arrondissement Môle
                        <svg class="h-3.5 w-3.5 transition-transform" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.148l3.71-3.918a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <!-- position: fixed (calculée depuis le bouton) plutôt qu'absolute :
                         le conteneur du menu défile horizontalement (overflow-x-auto) sur
                         écran moyen, ce qui force aussi le clip vertical (règle CSS : un axe
                         non-visible force l'autre en "auto") et couperait un panneau absolute. -->
                    <div x-show="open" x-transition x-cloak :style="`position: fixed; top: ${top}px; left: ${left}px;`"
                         style="display: none"
                         class="z-50 w-72 rounded-xl border border-msn-sand-200 bg-white py-2 text-msn-ink-900 shadow-xl">
                        <a href="{{ route('territoire.index') }}" class="block px-4 py-2 text-sm font-semibold hover:bg-msn-sand-100">
                            Vue d'ensemble de l'arrondissement
                        </a>
                        @if ($moleCommune)
                            <p class="mt-1 px-4 pt-2 text-xs font-semibold tracking-wide text-msn-ink-700/60 uppercase">
                                Sections de Môle-Saint-Nicolas
                            </p>
                            @foreach ($moleCommune->sectionsCommunales as $section)
                                <a href="{{ route('territoire.section', [$moleCommune->slug, $section->slug]) }}"
                                   class="block px-4 py-2 text-sm hover:bg-msn-sand-100">
                                    {{ $section->name }}
                                </a>
                            @endforeach
                        @endif
                        @if ($autresCommunes->isNotEmpty())
                            <p class="mt-1 px-4 pt-2 text-xs font-semibold tracking-wide text-msn-ink-700/60 uppercase">
                                Autres communes
                            </p>
                            @foreach ($autresCommunes as $commune)
                                <a href="{{ route('territoire.commune', $commune->slug) }}"
                                   class="block px-4 py-2 text-sm hover:bg-msn-sand-100">
                                    {{ $commune->name }}
                                </a>
                            @endforeach
                        @endif
                    </div>
                </div>

                @foreach ($navLinksAfter as $link)
                    <a href="{{ route($link['route']) }}"
                       class="whitespace-nowrap {{ request()->routeIs($link['route']) ? 'text-msn-gold-400' : 'hover:text-msn-gold-400' }}">
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
                @foreach ($navLinksBefore as $link)
                    <a href="{{ route($link['route']) }}"
                       class="rounded-lg px-3 py-2 {{ request()->routeIs($link['route']) ? 'bg-msn-sea-900 text-msn-gold-400' : 'hover:bg-msn-sea-900' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach

                <div class="rounded-lg px-3 py-2 {{ request()->routeIs('territoire.*') ? 'bg-msn-sea-900 text-msn-gold-400' : '' }}">
                    <p class="text-xs font-semibold tracking-wide text-msn-sand-200/70 uppercase">Arrondissement Môle</p>
                    <a href="{{ route('territoire.index') }}" class="mt-1 block rounded-lg px-2 py-1.5 hover:bg-msn-sea-900">
                        Vue d'ensemble
                    </a>
                    @if ($moleCommune)
                        @foreach ($moleCommune->sectionsCommunales as $section)
                            <a href="{{ route('territoire.section', [$moleCommune->slug, $section->slug]) }}"
                               class="block rounded-lg px-2 py-1.5 hover:bg-msn-sea-900">
                                {{ $section->name }}
                            </a>
                        @endforeach
                    @endif
                    @foreach ($autresCommunes as $commune)
                        <a href="{{ route('territoire.commune', $commune->slug) }}"
                           class="block rounded-lg px-2 py-1.5 hover:bg-msn-sea-900">
                            {{ $commune->name }}
                        </a>
                    @endforeach
                </div>

                @foreach ($navLinksAfter as $link)
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
        <div class="mx-auto px-4 py-6 text-center text-sm sm:px-6 lg:px-8">
            &copy; {{ date('Y') }} Môle-Saint-Nicolas. Tous droits réservés.
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

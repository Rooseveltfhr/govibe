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
    <header class="sticky top-0 z-50 bg-msn-sea-950/95 text-msn-sand-100 backdrop-blur">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-wide">
                Môle-Saint-Nicolas
            </a>
            <div class="hidden items-center gap-6 text-sm md:flex">
                <a href="{{ route('home') }}#histoire" class="hover:text-msn-gold-400">Histoire</a>
                <a href="{{ route('home') }}#territoire" class="hover:text-msn-gold-400">Territoire</a>
                <a href="{{ route('home') }}#sejour" class="hover:text-msn-gold-400">Où séjourner</a>
                <a href="{{ route('home') }}#restaurants" class="hover:text-msn-gold-400">Restaurants</a>
            </div>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="bg-msn-sea-950 py-10 text-msn-sand-200">
        <div class="mx-auto max-w-7xl px-4 text-sm sm:px-6 lg:px-8">
            <p>&copy; {{ date('Y') }} Môle-Saint-Nicolas. Plateforme en construction — contenu progressivement enrichi.</p>
        </div>
    </footer>
</body>
</html>

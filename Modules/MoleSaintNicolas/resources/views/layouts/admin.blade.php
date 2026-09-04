<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Administration — Môle-Saint-Nicolas')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-msn-sand-100 text-msn-sea-950 font-sans antialiased">
    @auth
        <nav class="border-b border-msn-sand-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center gap-6 px-4 py-3 text-sm sm:px-6 lg:px-8">
                <a href="{{ route('admin.dashboard') }}" class="font-semibold text-msn-sea-900">Administration</a>
                <a href="{{ route('admin.territoire.communes.index') }}" class="text-msn-sea-700 hover:text-msn-sea-900">Communes</a>
                <a href="{{ route('admin.territoire.sections.index') }}" class="text-msn-sea-700 hover:text-msn-sea-900">Sections communales</a>
                <a href="{{ route('admin.histoire.periods.index') }}" class="text-msn-sea-700 hover:text-msn-sea-900">Périodes</a>
                <a href="{{ route('admin.histoire.events.index') }}" class="text-msn-sea-700 hover:text-msn-sea-900">Événements</a>
                <a href="{{ route('admin.histoire.figures.index') }}" class="text-msn-sea-700 hover:text-msn-sea-900">Personnages</a>
                <a href="{{ route('admin.etablissements.index') }}" class="text-msn-sea-700 hover:text-msn-sea-900">Établissements</a>
                <a href="{{ route('admin.reservations.index') }}" class="text-msn-sea-700 hover:text-msn-sea-900">Réservations</a>
            </div>
        </nav>
    @endauth

    @if (session('status'))
        <div class="mx-auto mt-4 max-w-5xl rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 sm:px-6 lg:px-8">
            {{ session('status') }}
        </div>
    @endif

    @yield('content')
    @livewireScripts
</body>
</html>

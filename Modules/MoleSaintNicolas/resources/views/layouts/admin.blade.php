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
    @yield('content')
    @livewireScripts
</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Connexion') — GOVIBE ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>* { font-family:'Inter',sans-serif; }</style>
</head>
<body class="min-h-screen flex" style="background:linear-gradient(135deg,#091929 0%,#0f2236 50%,#1e3a5f 100%)">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full" style="background:rgba(212,160,23,0.06)"></div>
        <div class="absolute top-1/2 -left-32 w-80 h-80 rounded-full" style="background:rgba(212,160,23,0.04)"></div>
        <div class="absolute -bottom-32 right-1/3 w-72 h-72 rounded-full" style="background:rgba(30,58,95,0.3)"></div>
    </div>
    <div class="relative w-full flex">
        {{-- Left: branding --}}
        <div class="hidden lg:flex flex-col justify-center items-start p-16 w-1/2">
            <div class="mb-8">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-6" style="background:linear-gradient(135deg,#d4a017,#f5c518)">
                    <span class="font-black text-2xl" style="color:#0f2236">G</span>
                </div>
                <h1 class="text-4xl font-extrabold text-white mb-3">GOVIBE ERP</h1>
                <p class="text-blue-300 text-lg max-w-md">Plateforme de gestion complète pour GOVIBE Innovation Hub</p>
            </div>
            <div class="space-y-4">
                @foreach(['CRM & Gestion Clients', 'Projets & Tâches', 'Finance & Facturation', 'RH & Employés', 'Point de Vente', 'GOVIBE Academy', 'Rapports & Analytics', 'Super Admin Panel'] as $feat)
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0" style="background:rgba(212,160,23,0.2)">
                        <i class="bi bi-check text-yellow-400 text-xs font-bold"></i>
                    </div>
                    <span class="text-blue-200 text-sm">{{ $feat }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Right: form --}}
        <div class="flex-1 flex items-center justify-center p-8">
            @yield('content')
        </div>
    </div>
</body>
</html>

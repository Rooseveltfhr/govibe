<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — GOVIBE Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: { DEFAULT: '#1e3a5f', 50: '#f0f4f8', 100: '#d9e2ec', 700: '#334e68', 800: '#243b53', 900: '#102a43' },
                        gold: { DEFAULT: '#d4a017', 300: '#f9d03d', 400: '#f5c518' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { width: 260px; min-height: 100vh; background: linear-gradient(180deg, #1e3a5f 0%, #102a43 100%); }
        .sidebar-link { transition: all 0.2s ease; border-radius: 8px; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(212,160,23,0.15); color: #f5c518; }
        .sidebar-link.active { border-left: 3px solid #f5c518; }
        .gold-gradient { background: linear-gradient(135deg, #d4a017, #f5c518); }
        .stat-card { background: white; border-radius: 16px; padding: 24px; border: 1px solid #e5e7eb; transition: all 0.3s; }
        .stat-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .table-row:hover { background: #f8fafc; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-100">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="sidebar fixed left-0 top-0 h-full z-40 flex flex-col">
        <!-- Logo -->
        <div class="p-6 border-b border-blue-800">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl gold-gradient flex items-center justify-center shadow-lg">
                    <span class="font-black text-lg" style="color:#1e3a5f">G</span>
                </div>
                <div>
                    <p class="text-white font-bold text-base leading-none">GOVIBE</p>
                    <p class="text-yellow-400 text-xs leading-none mt-1">Admin Panel</p>
                </div>
            </a>
        </div>

        <!-- Nav -->
        <nav class="flex-1 p-4 space-y-1">
            <p class="text-blue-400 text-xs font-semibold uppercase tracking-wider px-3 mb-2">Navigation</p>

            <a href="{{ route('admin.dashboard') }}" class="sidebar-link flex items-center space-x-3 px-3 py-2.5 text-blue-100 {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home w-5 text-center"></i>
                <span class="text-sm font-medium">Tableau de bord</span>
            </a>

            <a href="{{ route('admin.inscriptions.index') }}" class="sidebar-link flex items-center space-x-3 px-3 py-2.5 text-blue-100 {{ request()->routeIs('admin.inscriptions.*') ? 'active' : '' }}">
                <i class="fas fa-users w-5 text-center"></i>
                <span class="text-sm font-medium">Participants</span>
                <span class="ml-auto bg-yellow-400 text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full">{{ \App\Models\Inscription::count() }}</span>
            </a>

            <a href="{{ route('admin.formations.index') }}" class="sidebar-link flex items-center space-x-3 px-3 py-2.5 text-blue-100 {{ request()->routeIs('admin.formations.*') ? 'active' : '' }}">
                <i class="fas fa-graduation-cap w-5 text-center"></i>
                <span class="text-sm font-medium">Formations</span>
            </a>

            <div class="pt-4 border-t border-blue-800 mt-4">
                <p class="text-blue-400 text-xs font-semibold uppercase tracking-wider px-3 mb-2">Exports</p>
                <a href="{{ route('admin.inscriptions.export.excel') }}" class="sidebar-link flex items-center space-x-3 px-3 py-2.5 text-blue-100">
                    <i class="fas fa-file-excel w-5 text-center text-green-400"></i>
                    <span class="text-sm font-medium">Exporter Excel</span>
                </a>
                <a href="{{ route('admin.inscriptions.export.csv') }}" class="sidebar-link flex items-center space-x-3 px-3 py-2.5 text-blue-100">
                    <i class="fas fa-file-csv w-5 text-center text-blue-400"></i>
                    <span class="text-sm font-medium">Exporter CSV</span>
                </a>
            </div>
        </nav>

        <!-- User info -->
        <div class="p-4 border-t border-blue-800">
            <div class="flex items-center space-x-3 mb-3">
                <div class="w-9 h-9 rounded-full gold-gradient flex items-center justify-center">
                    <span class="font-bold text-sm" style="color:#1e3a5f">{{ substr(auth()->user()->name ?? 'A', 0, 1) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ auth()->user()->name ?? 'Admin' }}</p>
                    <p class="text-blue-300 text-xs truncate">{{ auth()->user()->email ?? '' }}</p>
                </div>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full text-left flex items-center space-x-2 text-red-400 hover:text-red-300 text-sm px-3 py-2 rounded-lg hover:bg-red-500/10 transition-colors">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Content -->
    <div class="flex-1 ml-64">
        <!-- Top bar -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
            <div class="flex items-center justify-between px-6 h-14">
                <h1 class="text-lg font-semibold text-gray-800">@yield('page-title', 'Tableau de bord')</h1>
                <div class="flex items-center space-x-3">
                    <span class="text-xs text-gray-500">{{ now()->format('d/m/Y') }}</span>
                    <a href="{{ route('inscription.create') }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                        <i class="fas fa-external-link-alt text-xs"></i>
                        <span>Formulaire public</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Alerts -->
        <div class="px-6 pt-4">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center space-x-2 mb-4">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span class="text-sm">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center space-x-2 mb-4">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span class="text-sm">{{ session('error') }}</span>
                </div>
            @endif
        </div>

        <main class="p-6">
            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>

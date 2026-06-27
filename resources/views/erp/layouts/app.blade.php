<!DOCTYPE html>
<html lang="fr" x-data="{ sidebarOpen: true, darkMode: false, notifOpen: false }" :class="darkMode ? 'dark' : ''" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ERP') — GOVIBE Hub</title>

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    navy:   { DEFAULT:'#1e3a5f', 50:'#f0f4f8', 100:'#d9e2ec', 700:'#334e68', 800:'#243b53', 900:'#102a43', 950:'#091929' },
                    gold:   { DEFAULT:'#d4a017', 300:'#f9d03d', 400:'#f5c518', 500:'#d4a017', 600:'#b8860b' },
                    sidebar:'#0f2236',
                },
                fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] },
            }
        }
    }
    </script>

    {{-- Fonts & Icons --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    {{-- AlpineJS --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        * { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        .sidebar { background: #0f2236; width: 240px; transition: width 0.3s ease; }
        .sidebar.collapsed { width: 68px; }
        .sidebar-link { display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-radius: 8px; color: #94a3b8; font-size: 13px; font-weight: 500; transition: all 0.2s; cursor: pointer; white-space: nowrap; overflow: hidden; margin: 1px 8px; }
        .sidebar-link:hover { background: rgba(212,160,23,0.12); color: #f5c518; }
        .sidebar-link.active { background: rgba(212,160,23,0.18); color: #f5c518; border-left: 3px solid #f5c518; }
        .sidebar-link .icon { font-size: 16px; min-width: 20px; text-align: center; }
        .sidebar-link .label { transition: opacity 0.2s; }
        .sidebar.collapsed .sidebar-link .label { opacity: 0; width: 0; }
        .sidebar.collapsed .sidebar-link { padding: 10px; justify-content: center; }
        .sidebar-section { padding: 8px 16px 4px; font-size: 9px; font-weight: 700; letter-spacing: 1.5px; color: #475569; text-transform: uppercase; white-space: nowrap; overflow: hidden; }
        .sidebar.collapsed .sidebar-section { opacity: 0; }

        .stat-card { background: white; border-radius: 14px; padding: 22px; border: 1px solid #e5e7eb; transition: all 0.25s; }
        .stat-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .dark .stat-card { background: #1e293b; border-color: #334155; }

        .content-card { background: white; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; }
        .dark .content-card { background: #1e293b; border-color: #334155; }

        .badge { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, #1e3a5f, #2d5a8e); color: white; padding: 8px 18px; border-radius: 9px; font-size: 13px; font-weight: 600; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(30,58,95,0.35); }
        .btn-gold { background: linear-gradient(135deg, #d4a017, #f5c518); color: #1e3a5f; padding: 8px 18px; border-radius: 9px; font-size: 13px; font-weight: 700; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(212,160,23,0.4); }

        .table-row:hover { background: #f8fafc; }
        .dark .table-row:hover { background: #1e293b; }

        /* Notifications slide */
        [x-cloak] { display: none !important; }

        /* Status badges */
        .status-active    { background:#dcfce7; color:#166534; }
        .status-inactive  { background:#fee2e2; color:#991b1b; }
        .status-pending   { background:#fef9c3; color:#854d0e; }
        .status-draft     { background:#f1f5f9; color:#475569; }
        .status-completed { background:#dbeafe; color:#1d4ed8; }

        /* Priority */
        .priority-critical { color:#dc2626; }
        .priority-high     { color:#ea580c; }
        .priority-medium   { color:#ca8a04; }
        .priority-low      { color:#16a34a; }

        /* Kanban */
        .kanban-col { background:#f8fafc; border-radius:12px; min-height:400px; }
        .kanban-card { background:white; border-radius:10px; border:1px solid #e5e7eb; padding:12px; margin-bottom:8px; cursor:grab; transition:all 0.2s; }
        .kanban-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.1); transform:translateY(-1px); }

        /* Progress bar */
        .progress-bar { height:6px; background:#e2e8f0; border-radius:999px; overflow:hidden; }
        .progress-fill { height:100%; background:linear-gradient(90deg,#1e3a5f,#2d5a8e); border-radius:999px; transition:width 0.5s ease; }

        /* Avatar */
        .avatar { border-radius:50%; display:flex;align-items:center;justify-content:center;font-weight:700;color:white; }
        .avatar-navy { background:#1e3a5f; }
        .avatar-gold  { background:#d4a017; color:#1e3a5f; }
    </style>

    @livewireStyles
    @stack('styles')
</head>
<body class="bg-gray-100 dark:bg-slate-900 h-full" x-cloak>

<div class="flex h-screen overflow-hidden">

    {{-- ============= SIDEBAR ============= --}}
    <aside class="sidebar flex flex-col flex-shrink-0 h-full overflow-y-auto overflow-x-hidden"
           :class="{ 'collapsed': !sidebarOpen }">

        {{-- Logo --}}
        <div class="flex items-center gap-3 px-4 py-5 border-b border-white/10">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#d4a017,#f5c518)">
                <span class="font-black text-base" style="color:#0f2236">G</span>
            </div>
            <div x-show="sidebarOpen" x-transition class="overflow-hidden">
                <p class="text-white font-bold text-sm leading-none">GOVIBE</p>
                <p class="text-yellow-400 text-xs">Innovation Hub ERP</p>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 py-3 overflow-y-auto overflow-x-hidden">

            <div class="sidebar-section">Principal</div>

            <a href="{{ route('erp.dashboard') }}" class="sidebar-link {{ request()->routeIs('erp.dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2-fill icon"></i>
                <span class="label">Dashboard</span>
            </a>

            <div class="sidebar-section mt-2">Business</div>

            <a href="{{ route('erp.crm.clients.index') }}" class="sidebar-link {{ request()->routeIs('erp.crm.*') ? 'active' : '' }}">
                <i class="bi bi-people-fill icon"></i>
                <span class="label">CRM / Clients</span>
            </a>
            <a href="{{ route('erp.projects.index') }}" class="sidebar-link {{ request()->routeIs('erp.projects.*') ? 'active' : '' }}">
                <i class="bi bi-kanban-fill icon"></i>
                <span class="label">Projets</span>
            </a>
            <a href="{{ route('erp.services.index') }}" class="sidebar-link {{ request()->routeIs('erp.services.*') ? 'active' : '' }}">
                <i class="bi bi-stars icon"></i>
                <span class="label">Services</span>
            </a>
            <a href="{{ route('erp.booking.index') }}" class="sidebar-link {{ request()->routeIs('erp.booking.*') ? 'active' : '' }}">
                <i class="bi bi-calendar-check-fill icon"></i>
                <span class="label">Réservations</span>
            </a>

            <div class="sidebar-section mt-2">Finance</div>

            <a href="{{ route('erp.finance.index') }}" class="sidebar-link {{ request()->routeIs('erp.finance.*') ? 'active' : '' }}">
                <i class="bi bi-graph-up-arrow icon"></i>
                <span class="label">Finance</span>
            </a>
            <a href="{{ route('erp.invoices.index') }}" class="sidebar-link {{ request()->routeIs('erp.invoices.*') ? 'active' : '' }}">
                <i class="bi bi-receipt icon"></i>
                <span class="label">Factures</span>
            </a>
            <a href="{{ route('erp.pos.index') }}" class="sidebar-link {{ request()->routeIs('erp.pos.*') ? 'active' : '' }}">
                <i class="bi bi-shop-window icon"></i>
                <span class="label">Point de Vente</span>
            </a>

            <div class="sidebar-section mt-2">Opérations</div>

            <a href="{{ route('erp.hr.index') }}" class="sidebar-link {{ request()->routeIs('erp.hr.*') ? 'active' : '' }}">
                <i class="bi bi-person-workspace icon"></i>
                <span class="label">Ressources Humaines</span>
            </a>
            <a href="{{ route('erp.academy.index') }}" class="sidebar-link {{ request()->routeIs('erp.academy.*') ? 'active' : '' }}">
                <i class="bi bi-mortarboard-fill icon"></i>
                <span class="label">GOVIBE Academy</span>
            </a>
            <a href="{{ route('erp.inventory.index') }}" class="sidebar-link {{ request()->routeIs('erp.inventory.*') ? 'active' : '' }}">
                <i class="bi bi-box-seam-fill icon"></i>
                <span class="label">Inventaire</span>
            </a>

            <div class="sidebar-section mt-2">Analyse</div>

            <a href="{{ route('erp.reports.index') }}" class="sidebar-link {{ request()->routeIs('erp.reports.*') ? 'active' : '' }}">
                <i class="bi bi-bar-chart-fill icon"></i>
                <span class="label">Rapports</span>
            </a>

            <div class="sidebar-section mt-2">Admin</div>

            <a href="{{ route('erp.admin.users.index') }}" class="sidebar-link {{ request()->routeIs('erp.admin.*') ? 'active' : '' }}">
                <i class="bi bi-shield-fill-check icon"></i>
                <span class="label">Super Admin</span>
            </a>
            <a href="{{ route('erp.admin.business-units.index') }}" class="sidebar-link">
                <i class="bi bi-building icon"></i>
                <span class="label">Unités Business</span>
            </a>
            <a href="{{ route('erp.admin.services.index') }}" class="sidebar-link">
                <i class="bi bi-gear-fill icon"></i>
                <span class="label">Gérer Services</span>
            </a>
        </nav>

        {{-- User bottom --}}
        <div class="border-t border-white/10 p-3" x-show="sidebarOpen">
            <div class="flex items-center gap-2">
                <div class="avatar avatar-gold w-8 h-8 text-xs flex-shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-xs font-medium truncate">{{ auth()->user()->name ?? 'Admin' }}</p>
                    <p class="text-slate-400 text-xs truncate">{{ auth()->user()->email ?? '' }}</p>
                </div>
            </div>
        </div>
    </aside>

    {{-- ============= MAIN CONTENT ============= --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        {{-- Top bar --}}
        <header class="bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between px-5 h-14 flex-shrink-0 shadow-sm z-20">
            <div class="flex items-center gap-3">
                {{-- Toggle sidebar --}}
                <button @click="sidebarOpen = !sidebarOpen"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 transition-colors">
                    <i class="bi bi-list text-lg"></i>
                </button>
                <div>
                    <h1 class="font-semibold text-gray-800 dark:text-white text-sm">@yield('page-title', 'Dashboard')</h1>
                    <p class="text-gray-400 text-xs">@yield('page-subtitle', 'GOVIBE Innovation Hub ERP')</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- Search --}}
                <div class="hidden md:flex items-center bg-gray-100 dark:bg-slate-700 rounded-xl px-3 py-2 gap-2 w-56">
                    <i class="bi bi-search text-gray-400 text-sm"></i>
                    <input type="text" placeholder="Rechercher..." class="bg-transparent text-sm text-gray-600 dark:text-gray-300 outline-none w-full placeholder-gray-400">
                </div>

                {{-- Dark mode --}}
                <button @click="darkMode = !darkMode"
                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 dark:text-gray-400 transition-colors">
                    <i class="bi" :class="darkMode ? 'bi-sun-fill text-yellow-400' : 'bi-moon-fill'"></i>
                </button>

                {{-- Notifications --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                            class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-500 relative">
                        <i class="bi bi-bell-fill text-base"></i>
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span>
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition
                         class="absolute right-0 mt-2 w-72 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 z-50">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700">
                            <p class="font-semibold text-sm text-gray-800 dark:text-white">Notifications</p>
                        </div>
                        <div class="p-3 text-center text-gray-400 text-sm py-8">
                            <i class="bi bi-bell-slash text-2xl block mb-2 opacity-40"></i>
                            Aucune notification
                        </div>
                    </div>
                </div>

                {{-- Profile --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                            class="avatar avatar-navy w-8 h-8 text-xs flex-shrink-0 cursor-pointer">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition
                         class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-200 dark:border-slate-700 z-50 py-2">
                        <div class="px-4 py-2 border-b border-gray-100 dark:border-slate-700 mb-1">
                            <p class="font-medium text-sm text-gray-800 dark:text-white">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-400">{{ auth()->user()->getRoleNames()->first() ?? 'Admin' }}</p>
                        </div>
                        <a href="{{ route('erp.admin.users.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700">
                            <i class="bi bi-person"></i> Profil
                        </a>
                        <form action="{{ route('erp.logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full text-left flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="bi bi-box-arrow-right"></i> Déconnexion
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Alerts --}}
        @if(session('success'))
        <div class="mx-5 mt-4" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)">
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm"><i class="bi bi-check-circle-fill text-green-500"></i> {{ session('success') }}</div>
                <button @click="show = false"><i class="bi bi-x text-green-600"></i></button>
            </div>
        </div>
        @endif
        @if(session('error'))
        <div class="mx-5 mt-4" x-data="{ show: true }" x-show="show">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-center justify-between">
                <div class="flex items-center gap-2 text-sm"><i class="bi bi-exclamation-triangle-fill text-red-500"></i> {{ session('error') }}</div>
                <button @click="show = false"><i class="bi bi-x text-red-600"></i></button>
            </div>
        </div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 overflow-y-auto p-5 dark:bg-slate-900">
            @yield('content')
        </main>
    </div>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>

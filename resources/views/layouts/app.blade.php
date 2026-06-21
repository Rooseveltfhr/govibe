<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GOVIBE Academy')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'navy': { 50: '#f0f4f8', 100: '#d9e2ec', 200: '#bcccdc', 300: '#9fb3c8', 400: '#829ab1', 500: '#627d98', 600: '#486581', 700: '#334e68', 800: '#243b53', 900: '#102a43', DEFAULT: '#1e3a5f' },
                        'gold': { 100: '#fef9e7', 200: '#fdeaa8', 300: '#f9d03d', 400: '#f5c518', 500: '#d4a017', 600: '#b8860b', DEFAULT: '#d4a017' },
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .gradient-hero { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8e 50%, #1e3a5f 100%); }
        .gold-gradient { background: linear-gradient(135deg, #d4a017 0%, #f5c518 50%, #d4a017 100%); }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
        .form-input { transition: all 0.2s ease; }
        .form-input:focus { box-shadow: 0 0 0 3px rgba(212, 160, 23, 0.2); }
        .btn-primary { background: linear-gradient(135deg, #d4a017, #f5c518); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(212, 160, 23, 0.4); }
        .navbar-blur { backdrop-filter: blur(10px); background: rgba(30, 58, 95, 0.95); }
        @keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.6s ease forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Navbar -->
    <nav class="navbar-blur fixed top-0 w-full z-50 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="{{ route('inscription.create') }}" class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full gold-gradient flex items-center justify-center">
                        <span class="text-navy-900 font-black text-lg" style="color:#1e3a5f">G</span>
                    </div>
                    <div>
                        <span class="text-white font-bold text-lg leading-none">GOVIBE</span>
                        <span class="text-yellow-400 font-light text-sm block leading-none">Academy</span>
                    </div>
                </a>
                <div class="flex items-center space-x-4">
                    @auth
                        @if(auth()->user()->is_admin)
                            <a href="{{ route('admin.dashboard') }}" class="text-yellow-400 hover:text-yellow-300 text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-1"></i>Admin
                            </a>
                        @endif
                    @endauth
                    <a href="{{ route('inscription.create') }}" class="bg-yellow-400 text-gray-900 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-yellow-300 transition-colors">
                        S'inscrire
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <main class="pt-16">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-navy-900 text-white py-8 mt-12" style="background:#102a43">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="flex items-center justify-center space-x-2 mb-3">
                <div class="w-8 h-8 rounded-full gold-gradient flex items-center justify-center">
                    <span class="font-black text-sm" style="color:#1e3a5f">G</span>
                </div>
                <span class="font-bold text-lg">GOVIBE Academy</span>
            </div>
            <p class="text-gray-400 text-sm">© {{ date('Y') }} GOVIBE Academy. Tous droits réservés.</p>
            <p class="text-gray-500 text-xs mt-1">Formation professionnelle en Haïti</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

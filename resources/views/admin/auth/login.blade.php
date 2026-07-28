<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin — GOVIBE Academy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #1e3a5f 0%, #102a43 100%); }
        .gold-gradient { background: linear-gradient(135deg, #d4a017, #f5c518); }
        .glass-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(20px); }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 rounded-full" style="background:rgba(212,160,23,0.08)"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 rounded-full" style="background:rgba(212,160,23,0.05)"></div>
    </div>

    <div class="relative w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl gold-gradient flex items-center justify-center mx-auto mb-4 shadow-2xl">
                <span class="font-black text-2xl" style="color:#1e3a5f">G</span>
            </div>
            <h1 class="text-2xl font-bold text-white">GOVIBE Academy</h1>
            <p class="text-blue-300 text-sm mt-1">Espace Administrateur</p>
        </div>

        <!-- Card -->
        <div class="glass-card rounded-2xl p-8 shadow-2xl">
            <h2 class="text-xl font-semibold text-white mb-6">Connexion</h2>

            @if($errors->any())
                <div class="bg-red-500/20 border border-red-500/30 rounded-xl p-4 mb-5">
                    @foreach($errors->all() as $error)
                        <p class="text-red-300 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('admin.login.post') }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-blue-200 text-sm font-medium mb-2">Adresse e-mail</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-blue-400 text-sm"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="w-full bg-white/10 border border-white/20 rounded-xl pl-11 pr-4 py-3 text-white placeholder-blue-300 focus:outline-none focus:border-yellow-400 focus:bg-white/15 transition-all"
                               placeholder="admin@govibe.ht">
                    </div>
                </div>

                <div>
                    <label class="block text-blue-200 text-sm font-medium mb-2">Mot de passe</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-blue-400 text-sm"></i>
                        <input type="password" name="password" required
                               class="w-full bg-white/10 border border-white/20 rounded-xl pl-11 pr-4 py-3 text-white placeholder-blue-300 focus:outline-none focus:border-yellow-400 focus:bg-white/15 transition-all"
                               placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="remember" id="remember" class="rounded">
                    <label for="remember" class="text-blue-200 text-sm">Se souvenir de moi</label>
                </div>

                <button type="submit"
                        class="w-full font-bold py-3.5 px-6 rounded-xl text-gray-900 text-sm transition-all hover:shadow-xl hover:scale-[1.02] active:scale-[0.98]"
                        style="background: linear-gradient(135deg, #d4a017, #f5c518)">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Se connecter
                </button>
            </form>
        </div>

        <p class="text-center text-blue-400 text-xs mt-6">
            <a href="{{ route('inscription.create') }}" class="hover:text-yellow-400 transition-colors">
                <i class="fas fa-arrow-left mr-1"></i>Retour au formulaire public
            </a>
        </p>
    </div>
</body>
</html>

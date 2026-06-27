@extends('erp.layouts.auth')

@section('title','Connexion ERP')

@section('content')
<div class="w-full max-w-md">
    <div class="bg-white/5 border border-white/10 backdrop-blur-xl rounded-2xl p-8 shadow-2xl">
        <div class="mb-7">
            <h2 class="text-2xl font-bold text-white">Bienvenue 👋</h2>
            <p class="text-blue-300 text-sm mt-1">Connectez-vous à votre espace ERP</p>
        </div>

        @if($errors->any())
        <div class="bg-red-500/20 border border-red-500/30 rounded-xl p-3 mb-5">
            @foreach($errors->all() as $e)
            <p class="text-red-300 text-sm"><i class="bi bi-exclamation-triangle mr-1"></i>{{ $e }}</p>
            @endforeach
        </div>
        @endif

        <form action="{{ route('erp.login.post') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-blue-200 text-sm font-medium mb-1.5">Adresse e-mail</label>
                <div class="relative">
                    <i class="bi bi-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-blue-400 text-sm"></i>
                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                           class="w-full bg-white/10 border border-white/20 rounded-xl pl-10 pr-4 py-3 text-white placeholder-blue-300/60 text-sm focus:outline-none focus:border-yellow-400 focus:bg-white/15 transition-all"
                           placeholder="admin@govibe.ht">
                </div>
            </div>
            <div>
                <label class="block text-blue-200 text-sm font-medium mb-1.5">Mot de passe</label>
                <div class="relative" x-data="{ show: false }">
                    <i class="bi bi-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-blue-400 text-sm"></i>
                    <input :type="show ? 'text' : 'password'" name="password" required
                           class="w-full bg-white/10 border border-white/20 rounded-xl pl-10 pr-10 py-3 text-white placeholder-blue-300/60 text-sm focus:outline-none focus:border-yellow-400 focus:bg-white/15 transition-all"
                           placeholder="••••••••">
                    <button type="button" @click="show = !show" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-blue-400">
                        <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-blue-200 text-sm cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded">
                    <span>Se souvenir</span>
                </label>
            </div>
            <button type="submit"
                    class="w-full font-bold py-3.5 px-6 rounded-xl text-sm transition-all hover:shadow-xl hover:scale-[1.02] active:scale-[0.98] mt-2"
                    style="background:linear-gradient(135deg,#d4a017,#f5c518);color:#0f2236">
                <i class="bi bi-box-arrow-in-right mr-2"></i>Se connecter
            </button>
        </form>

        <div class="mt-6 pt-5 border-t border-white/10 text-center">
            <a href="{{ route('inscription.create') }}" class="text-blue-400 hover:text-blue-300 text-xs transition-colors">
                <i class="bi bi-arrow-left mr-1"></i>Retour au site public
            </a>
        </div>
    </div>
</div>
@endsection

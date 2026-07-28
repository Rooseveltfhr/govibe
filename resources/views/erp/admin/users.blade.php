@extends('erp.layouts.app')
@section('title','Utilisateurs')
@section('page-title','Super Admin — Utilisateurs')
@section('page-subtitle','Gestion des accès et permissions')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Create user --}}
    <div class="content-card p-5 h-fit">
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="bi bi-person-plus-fill text-yellow-500"></i> Nouvel utilisateur
        </h3>
        <form action="{{ route('erp.admin.users.store') }}" method="POST" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nom complet *</label>
                <input type="text" name="name" required placeholder="Jean PAUL"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Email *</label>
                <input type="email" name="email" required placeholder="jean@govibe.ht"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Mot de passe *</label>
                <input type="password" name="password" required placeholder="Min. 6 caractères"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Confirmer mot de passe *</label>
                <input type="password" name="password_confirmation" required
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_admin" value="1" class="rounded" id="is_admin">
                <label for="is_admin" class="text-sm text-gray-600 dark:text-gray-400">Accès Administrateur ERP</label>
            </div>
            <button type="submit" class="btn-gold w-full"><i class="bi bi-person-plus mr-2"></i>Créer l'utilisateur</button>
        </form>
    </div>

    {{-- Users list --}}
    <div class="lg:col-span-2 content-card">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-semibold text-gray-800 dark:text-white">
                Utilisateurs ERP
                <span class="ml-2 bg-gray-100 dark:bg-slate-700 text-gray-500 text-xs px-2 py-0.5 rounded-full">{{ $users->total() }}</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-800/60">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Utilisateur</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Rôle</th>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Créé le</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    @foreach($users as $user)
                    <tr class="table-row">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="avatar {{ $user->is_admin ? 'avatar-gold' : 'avatar-navy' }} w-9 h-9 text-sm flex-shrink-0">
                                    {{ strtoupper(substr($user->name,0,1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-1">
                                        {{ $user->name }}
                                        @if($user->id === auth()->id())
                                        <span class="text-xs text-blue-500">(vous)</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="badge text-xs {{ $user->is_admin ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600' }}">
                                <i class="bi {{ $user->is_admin ? 'bi-shield-fill-check' : 'bi-person' }} mr-1"></i>
                                {{ $user->is_admin ? 'Administrateur' : 'Utilisateur' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-xs text-gray-400">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td class="px-5 py-3.5">
                            @if($user->id !== auth()->id())
                            <div class="flex items-center gap-1">
                                <form action="{{ route('erp.admin.users.toggle-admin', $user) }}" method="POST" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors" title="{{ $user->is_admin ? 'Retirer admin' : 'Rendre admin' }}">
                                        <i class="bi {{ $user->is_admin ? 'bi-shield-minus' : 'bi-shield-plus' }} text-xs"></i>
                                    </button>
                                </form>
                                <form action="{{ route('erp.admin.users.destroy', $user) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Supprimer {{ addslashes($user->name) }} ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                        <i class="bi bi-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection

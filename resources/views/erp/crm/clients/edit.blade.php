@extends('erp.layouts.app')
@section('title','Modifier — '.$client->name)
@section('page-title','Modifier client')
@section('page-subtitle', $client->name)

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('erp.crm.clients.show',$client) }}" class="p-2 hover:bg-gray-100 rounded-xl"><i class="bi bi-arrow-left text-gray-500"></i></a>
        <h2 class="font-bold text-gray-800 dark:text-white">{{ $client->name }}</h2>
    </div>
    <div class="content-card p-7">
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            @foreach($errors->all() as $e)<p class="text-red-600 text-sm">• {{ $e }}</p>@endforeach
        </div>
        @endif
        <form action="{{ route('erp.crm.clients.update',$client) }}" method="POST" class="space-y-5">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nom / Raison sociale *</label>
                    <input type="text" name="name" value="{{ old('name',$client->name) }}" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Type *</label>
                    <select name="type" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        @foreach(['individual'=>'Particulier','company'=>'Entreprise','ngo'=>'ONG','government'=>'Gouvernement','university'=>'Université','association'=>'Association'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('type',$client->type)===$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Statut *</label>
                    <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        @foreach(['prospect'=>'Prospect','active'=>'Actif','inactive'=>'Inactif'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('status',$client->status)===$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Téléphone</label><input type="text" name="phone" value="{{ old('phone',$client->phone) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email</label><input type="email" name="email" value="{{ old('email',$client->email) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Ville</label><input type="text" name="city" value="{{ old('city',$client->city) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Site web</label><input type="url" name="website" value="{{ old('website',$client->website) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white"></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Adresse</label><input type="text" name="address" value="{{ old('address',$client->address) }}" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white"></div>
                <div><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Source</label>
                    <select name="source" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        <option value="">--</option>
                        @foreach(['Réseaux sociaux','Site web','Référence','Événement','Appel direct','Email','Autre'] as $s)
                        <option value="{{ $s }}" {{ old('source',$client->source)===$s?'selected':'' }}>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label><textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 resize-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('notes',$client->notes) }}</textarea></div>
            </div>
            <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-slate-700">
                <button type="submit" class="btn-primary"><i class="bi bi-check-lg mr-2"></i>Enregistrer</button>
                <a href="{{ route('erp.crm.clients.show',$client) }}" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium transition-colors">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endsection

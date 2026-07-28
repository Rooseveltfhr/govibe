@extends('erp.layouts.app')
@section('title','Nouvelle réservation')
@section('page-title','Nouvelle réservation')
@section('page-subtitle','Réserver un espace coworking')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('erp.booking.index') }}" class="p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl">
            <i class="bi bi-arrow-left text-gray-500"></i>
        </a>
        <h2 class="font-bold text-gray-800 dark:text-white">Nouvelle réservation</h2>
    </div>
    <div class="content-card p-7">
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
            @foreach($errors->all() as $e)<p class="text-red-600 text-sm">• {{ $e }}</p>@endforeach
        </div>
        @endif
        <form action="{{ route('erp.booking.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Titre *</label>
                <input type="text" name="title" value="{{ old('title') }}" required placeholder="Réunion, formation, événement..."
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Client</label>
                <select name="client_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    <option value="">-- Aucun --</option>
                    @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ old('client_id')==$c->id?'selected':'' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Début *</label>
                    <input type="datetime-local" name="start_at" value="{{ old('start_at') }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Fin *</label>
                    <input type="datetime-local" name="end_at" value="{{ old('end_at') }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Espace</label>
                <select name="space" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    <option value="">-- Sélectionner --</option>
                    @foreach(['Salle de réunion A','Salle de réunion B','Espace open coworking','Salle de formation','Studio Media','Lab IA','Salle de conférence'] as $s)
                    <option value="{{ $s }}" {{ old('space')===$s?'selected':'' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Notes</label>
                <textarea name="notes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 resize-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('notes') }}</textarea>
            </div>
            <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-slate-700">
                <button type="submit" class="btn-primary"><i class="bi bi-check-lg mr-2"></i>Créer la réservation</button>
                <a href="{{ route('erp.booking.index') }}" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endsection

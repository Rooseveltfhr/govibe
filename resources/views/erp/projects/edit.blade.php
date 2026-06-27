@extends('erp.layouts.app')
@section('title','Modifier — '.$project->name)
@section('page-title','Modifier projet')
@section('page-subtitle', $project->name)

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('erp.projects.show',$project) }}" class="p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl">
            <i class="bi bi-arrow-left text-gray-500"></i>
        </a>
        <h2 class="font-bold text-gray-800 dark:text-white">{{ $project->name }}</h2>
    </div>

    <div class="content-card p-7">
        @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            @foreach($errors->all() as $e)<p class="text-red-600 text-sm">• {{ $e }}</p>@endforeach
        </div>
        @endif

        <form action="{{ route('erp.projects.update',$project) }}" method="POST" class="space-y-5">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nom du projet *</label>
                    <input type="text" name="name" value="{{ old('name',$project->name) }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Client</label>
                    <select name="client_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        <option value="">-- Aucun --</option>
                        @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ old('client_id',$project->client_id)==$c->id?'selected':'' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Unité business</label>
                    <select name="business_unit_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        <option value="">-- Aucune --</option>
                        @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" {{ old('business_unit_id',$project->business_unit_id)==$bu->id?'selected':'' }}>{{ $bu->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Statut *</label>
                    <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        @foreach(['planning'=>'Planification','active'=>'Actif','on_hold'=>'En attente','completed'=>'Terminé','cancelled'=>'Annulé'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('status',$project->status)===$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Priorité *</label>
                    <select name="priority" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        @foreach(['low'=>'Basse','medium'=>'Moyenne','high'=>'Haute','critical'=>'Critique'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('priority',$project->priority)===$v?'selected':'' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Chef de projet</label>
                    <select name="manager_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        <option value="">-- Aucun --</option>
                        @foreach($managers as $m)
                        <option value="{{ $m->id }}" {{ old('manager_id',$project->manager_id)==$m->id?'selected':'' }}>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Budget (HTG)</label>
                    <input type="number" name="budget" value="{{ old('budget',$project->budget) }}" min="0"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Progrès (%)</label>
                    <input type="number" name="progress" value="{{ old('progress',$project->progress) }}" min="0" max="100"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date début</label>
                    <input type="date" name="start_date" value="{{ old('start_date',$project->start_date?->format('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date fin</label>
                    <input type="date" name="end_date" value="{{ old('end_date',$project->end_date?->format('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Description</label>
                    <textarea name="description" rows="4"
                              class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400 resize-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('description',$project->description) }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-slate-700">
                <button type="submit" class="btn-primary"><i class="bi bi-check-lg mr-2"></i>Enregistrer</button>
                <a href="{{ route('erp.projects.show',$project) }}" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-medium transition-colors dark:bg-slate-700 dark:text-gray-300">Annuler</a>
            </div>
        </form>
    </div>
</div>
@endsection

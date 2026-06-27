@extends('erp.layouts.app')
@section('title',$project->name)
@section('page-title',$project->name)
@section('page-subtitle',$project->reference)

@section('content')
@php
$sColors=['planning'=>'bg-gray-100 text-gray-600','active'=>'bg-blue-100 text-blue-700','on_hold'=>'bg-yellow-100 text-yellow-700','completed'=>'bg-green-100 text-green-700','cancelled'=>'bg-red-100 text-red-700'];
$pColors=['low'=>'text-green-600','medium'=>'text-yellow-600','high'=>'text-orange-600','critical'=>'text-red-600'];
@endphp

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('erp.projects.index') }}" class="p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl">
        <i class="bi bi-arrow-left text-gray-500"></i>
    </a>
    <div class="flex-1">
        <div class="flex items-center gap-3">
            <h2 class="font-bold text-gray-800 dark:text-white text-xl">{{ $project->name }}</h2>
            <span class="badge text-xs {{ $sColors[$project->status] ?? 'bg-gray-100 text-gray-600' }}">
                {{ ucfirst(str_replace('_',' ',$project->status)) }}
            </span>
            <span class="text-xs font-semibold {{ $pColors[$project->priority] ?? 'text-gray-500' }}">
                <i class="bi bi-flag-fill mr-1"></i>{{ ucfirst($project->priority) }}
            </span>
        </div>
        <p class="text-sm text-gray-400 font-mono mt-0.5">{{ $project->reference }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('erp.projects.edit',$project) }}" class="btn-gold text-sm">
            <i class="bi bi-pencil mr-1"></i> Modifier
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main info --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Progress --}}
        <div class="content-card p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-800 dark:text-white">Progrès global</h3>
                <span class="text-2xl font-extrabold" style="color:#d4a017">{{ $project->progress }}%</span>
            </div>
            <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-3">
                <div class="h-3 rounded-full transition-all" style="width:{{ $project->progress }}%;background:#d4a017"></div>
            </div>
            @if($project->description)
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-4 leading-relaxed">{{ $project->description }}</p>
            @endif
        </div>

        {{-- Tasks --}}
        <div class="content-card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="bi bi-check2-square text-blue-500"></i> Tâches
                    <span class="bg-gray-100 dark:bg-slate-700 text-gray-500 text-xs px-2 py-0.5 rounded-full">{{ $project->tasks?->count() ?? 0 }}</span>
                </h3>
            </div>
            @if($project->tasks && $project->tasks->count())
            <div class="divide-y divide-gray-100 dark:divide-slate-700">
                @foreach($project->tasks as $task)
                <div class="flex items-center gap-3 px-5 py-3">
                    <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $task->status==='done'?'bg-green-500':($task->status==='in_progress'?'bg-blue-500':'bg-gray-300') }}"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300 flex-1">{{ $task->title }}</span>
                    @if($task->due_date)
                    <span class="text-xs text-gray-400">{{ $task->due_date->format('d/m') }}</span>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="px-5 py-10 text-center text-gray-400">
                <i class="bi bi-check2-square text-3xl block mb-2 opacity-30"></i>
                <p class="text-sm">Aucune tâche pour ce projet.</p>
            </div>
            @endif
        </div>

        {{-- Comments --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="bi bi-chat-dots text-indigo-500"></i> Notes & commentaires
                </h3>
            </div>
            <div class="px-5 py-4 text-center text-gray-400 text-sm">
                <i class="bi bi-chat text-3xl block mb-2 opacity-30"></i>
                Aucun commentaire.
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-5">

        {{-- Details --}}
        <div class="content-card p-5">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4">Détails</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-400">Client</dt>
                    <dd class="font-medium text-gray-800 dark:text-white">
                        @if($project->client)
                        <a href="{{ route('erp.crm.clients.show',$project->client) }}" class="hover:text-blue-600">{{ $project->client->name }}</a>
                        @else — @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Unité</dt>
                    <dd class="font-medium text-gray-800 dark:text-white">{{ $project->businessUnit->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Chef de projet</dt>
                    <dd class="font-medium text-gray-800 dark:text-white">{{ $project->manager->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Début</dt>
                    <dd class="font-medium text-gray-800 dark:text-white">{{ $project->start_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Échéance</dt>
                    <dd class="font-medium text-gray-800 dark:text-white">{{ $project->end_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Budget</dt>
                    <dd class="font-medium text-gray-800 dark:text-white">
                        {{ $project->budget ? 'HTG '.number_format($project->budget,0,'.',',') : '—' }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Créé le</dt>
                    <dd class="font-medium text-gray-800 dark:text-white">{{ $project->created_at->format('d/m/Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Quick actions --}}
        <div class="content-card p-5">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-3">Actions</h3>
            <div class="space-y-2">
                <a href="{{ route('erp.projects.edit',$project) }}" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-xl transition-colors">
                    <i class="bi bi-pencil text-yellow-500"></i> Modifier le projet
                </a>
                <a href="{{ route('erp.invoices.create') }}" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-xl transition-colors">
                    <i class="bi bi-receipt text-green-500"></i> Créer une facture
                </a>
                <form action="{{ route('erp.projects.destroy',$project) }}" method="POST"
                      onsubmit="return confirm('Supprimer ce projet ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-colors">
                        <i class="bi bi-trash"></i> Supprimer le projet
                    </button>
                </form>
            </div>
        </div>

        {{-- Milestones --}}
        @if($project->milestones && $project->milestones->count())
        <div class="content-card p-5">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                <i class="bi bi-flag text-orange-500"></i> Jalons
            </h3>
            <div class="space-y-2">
                @foreach($project->milestones as $m)
                <div class="flex items-center gap-2 text-sm">
                    <i class="bi {{ $m->completed ? 'bi-check-circle-fill text-green-500' : 'bi-circle text-gray-300' }}"></i>
                    <span class="text-gray-700 dark:text-gray-300">{{ $m->title }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@extends('erp.layouts.app')
@section('title','Kanban Projets')
@section('page-title','Kanban')
@section('page-subtitle','Vue tableau des projets')

@section('content')
@php
$columns = [
    'planning'  => ['label'=>'Planification', 'color'=>'#6b7280', 'bg'=>'#f3f4f6'],
    'active'    => ['label'=>'Actif',         'color'=>'#1d4ed8', 'bg'=>'#dbeafe'],
    'on_hold'   => ['label'=>'En attente',    'color'=>'#d97706', 'bg'=>'#fef3c7'],
    'completed' => ['label'=>'Terminé',       'color'=>'#059669', 'bg'=>'#d1fae5'],
    'cancelled' => ['label'=>'Annulé',        'color'=>'#dc2626', 'bg'=>'#fee2e2'],
];
$pColors=['low'=>'text-green-600','medium'=>'text-yellow-600','high'=>'text-orange-600','critical'=>'text-red-600'];
@endphp

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('erp.projects.index') }}" class="p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl">
        <i class="bi bi-list-ul text-gray-500"></i>
    </a>
    <h2 class="font-semibold text-gray-800 dark:text-white">Vue Kanban</h2>
    <div class="ml-auto">
        <a href="{{ route('erp.projects.create') }}" class="btn-gold text-sm">
            <i class="bi bi-plus-lg mr-1"></i> Nouveau projet
        </a>
    </div>
</div>

<div class="flex gap-4 overflow-x-auto pb-4" style="min-height:70vh">
    @foreach($columns as $status => $col)
    @php $colProjects = $projects[$status] ?? collect(); @endphp
    <div class="flex-shrink-0 w-72">
        <div class="flex items-center gap-2 mb-3 px-1">
            <div class="w-2.5 h-2.5 rounded-full" style="background:{{ $col['color'] }}"></div>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $col['label'] }}</span>
            <span class="ml-auto text-xs px-2 py-0.5 rounded-full font-medium" style="background:{{ $col['bg'] }};color:{{ $col['color'] }}">
                {{ $colProjects->count() }}
            </span>
        </div>

        <div class="space-y-3">
            @forelse($colProjects as $project)
            <div class="content-card p-4 hover:shadow-md transition-shadow cursor-pointer group">
                <a href="{{ route('erp.projects.show',$project) }}" class="block">
                    <p class="text-sm font-semibold text-gray-800 dark:text-white group-hover:text-blue-600 mb-1">{{ $project->name }}</p>
                    <p class="text-xs text-gray-400 font-mono mb-3">{{ $project->reference }}</p>

                    @if($project->client)
                    <div class="flex items-center gap-1.5 mb-3">
                        <i class="bi bi-building text-xs text-gray-400"></i>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $project->client->name }}</span>
                    </div>
                    @endif

                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold {{ $pColors[$project->priority] ?? 'text-gray-500' }}">
                            <i class="bi bi-flag-fill mr-1"></i>{{ ucfirst($project->priority) }}
                        </span>
                        @if($project->end_date)
                        <span class="text-xs text-gray-400">
                            <i class="bi bi-calendar2 mr-0.5"></i>{{ $project->end_date->format('d/m/Y') }}
                        </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-100 dark:bg-slate-700 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full" style="width:{{ $project->progress }}%;background:#d4a017"></div>
                        </div>
                        <span class="text-xs text-gray-400 w-8 text-right">{{ $project->progress }}%</span>
                    </div>
                </a>
            </div>
            @empty
            <div class="border-2 border-dashed border-gray-200 dark:border-slate-600 rounded-xl p-6 text-center text-gray-300 dark:text-slate-600 text-xs">
                Aucun projet
            </div>
            @endforelse
        </div>
    </div>
    @endforeach
</div>
@endsection

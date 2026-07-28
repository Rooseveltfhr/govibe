@extends('erp.layouts.app')
@section('title', $client->name)
@section('page-title', $client->name)
@section('page-subtitle','Profil client')

@section('content')
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('erp.crm.clients') }}" class="p-2 hover:bg-gray-100 rounded-xl transition-colors"><i class="bi bi-arrow-left text-gray-500"></i></a>
    <h2 class="font-bold text-gray-800 dark:text-white">{{ $client->name }}</h2>
    <span class="font-mono text-xs bg-gray-100 dark:bg-slate-700 text-gray-500 px-2 py-1 rounded-lg">{{ $client->reference_number }}</span>
    <span class="badge text-xs ml-auto {{ $client->status==='active'?'status-active':($client->status==='prospect'?'status-pending':'status-inactive') }}">{{ $client->status }}</span>
    <a href="{{ route('erp.crm.clients.edit',$client) }}" class="btn-primary ml-2"><i class="bi bi-pencil mr-1"></i>Modifier</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    {{-- Left column --}}
    <div class="space-y-5">
        <div class="content-card p-5">
            <div class="flex flex-col items-center text-center mb-4 pt-2">
                <div class="avatar avatar-navy w-16 h-16 text-2xl mb-3">{{ strtoupper(substr($client->name,0,1)) }}</div>
                <h3 class="font-bold text-gray-800 dark:text-white">{{ $client->name }}</h3>
                @php $typeLabels=['individual'=>'Particulier','company'=>'Entreprise','ngo'=>'ONG','government'=>'Gouvernement','university'=>'Université','association'=>'Association']; @endphp
                <span class="badge bg-blue-50 text-blue-700 text-xs mt-1">{{ $typeLabels[$client->type] ?? $client->type }}</span>
            </div>
            <div class="space-y-2 text-sm">
                @if($client->phone)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <i class="bi bi-phone text-gray-400 w-4"></i>{{ $client->phone }}
                </div>
                @endif
                @if($client->email)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <i class="bi bi-envelope text-gray-400 w-4"></i>{{ $client->email }}
                </div>
                @endif
                @if($client->city)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <i class="bi bi-geo-alt text-gray-400 w-4"></i>{{ $client->city }}{{ $client->address ? ' — '.$client->address : '' }}
                </div>
                @endif
                @if($client->website)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <i class="bi bi-globe text-gray-400 w-4"></i>
                    <a href="{{ $client->website }}" target="_blank" class="text-blue-600 hover:underline text-xs">{{ $client->website }}</a>
                </div>
                @endif
                @if($client->source)
                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                    <i class="bi bi-funnel text-gray-400 w-4"></i>Source: {{ $client->source }}
                </div>
                @endif
            </div>
            @if($client->notes)
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
                <p class="text-xs text-gray-400 mb-1">Notes</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $client->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Quick stats --}}
        <div class="content-card p-5">
            <h4 class="font-semibold text-gray-700 dark:text-gray-300 text-sm mb-3">Résumé</h4>
            <div class="space-y-3">
                @foreach([
                    ['Projets', $client->projects->count(), 'bi-kanban','#7c3aed'],
                    ['Factures', $client->invoices->count(), 'bi-receipt','#059669'],
                    ['Devis', $client->quotations->count(), 'bi-file-earmark-text','#d97706'],
                    ['Contrats', $client->contracts->count(), 'bi-file-earmark-check','#0891b2'],
                ] as [$l,$v,$i,$c])
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <i class="bi {{ $i }}" style="color:{{ $c }}"></i>{{ $l }}
                    </div>
                    <span class="font-bold text-gray-800 dark:text-white">{{ $v }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Right column --}}
    <div class="lg:col-span-2 space-y-5">
        {{-- Projects --}}
        <div class="content-card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h4 class="font-semibold text-gray-800 dark:text-white text-sm">Projets</h4>
                <a href="{{ route('erp.projects.create') }}" class="text-blue-600 text-xs hover:underline"><i class="bi bi-plus mr-1"></i>Nouveau</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-slate-700">
                @forelse($client->projects as $p)
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <a href="{{ route('erp.projects.show',$p) }}" class="text-sm font-medium text-gray-800 dark:text-white hover:text-blue-600">{{ $p->name }}</a>
                        <p class="text-xs text-gray-400">{{ $p->reference }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="progress-bar w-20"><div class="progress-fill" style="width:{{ $p->progress }}%"></div></div>
                        <span class="text-xs text-gray-400">{{ $p->progress }}%</span>
                    </div>
                </div>
                @empty
                <div class="px-5 py-6 text-center text-gray-400 text-sm">Aucun projet</div>
                @endforelse
            </div>
        </div>

        {{-- Invoices --}}
        <div class="content-card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h4 class="font-semibold text-gray-800 dark:text-white text-sm">Factures</h4>
                <a href="{{ route('erp.invoices.create') }}" class="text-blue-600 text-xs hover:underline"><i class="bi bi-plus mr-1"></i>Nouvelle</a>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-slate-700">
                @forelse($client->invoices as $inv)
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white font-mono">{{ $inv->reference }}</p>
                        <p class="text-xs text-gray-400">{{ $inv->issued_date?->format('d/m/Y') }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-gray-800 dark:text-white text-sm">HTG {{ number_format($inv->total,2) }}</span>
                        <span class="badge text-xs {{ $inv->status==='paid'?'status-active':($inv->status==='overdue'?'status-inactive':'status-pending') }}">{{ $inv->status }}</span>
                    </div>
                </div>
                @empty
                <div class="px-5 py-6 text-center text-gray-400 text-sm">Aucune facture</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@extends('erp.layouts.app')
@section('title','Inscription #'.$registration->id)
@section('page-title','Inscription Bootcamp')
@section('page-subtitle','Détails du participant')

@section('content')
<div class="grid md:grid-cols-3 gap-6">
    <div class="md:col-span-2 space-y-5">
        <div class="content-card p-6">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4">Informations participant</h3>
            <dl class="grid grid-cols-2 gap-y-4 gap-x-6 text-sm">
                @foreach([
                    ['Nom complet',    $registration->full_name],
                    ['Email',          $registration->email],
                    ['Téléphone',      $registration->phone ?: '—'],
                    ['Pays',           $registration->country ?: '—'],
                    ['Profession',     $registration->profession ?: '—'],
                    ['Organisation',   $registration->organization ?: '—'],
                ] as [$k,$v])
                <div><dt class="text-gray-500">{{ $k }}</dt><dd class="font-medium text-gray-800 dark:text-white mt-0.5">{{ $v }}</dd></div>
                @endforeach
            </dl>
        </div>
        <div class="content-card p-6">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4">Inscription & Paiement</h3>
            <dl class="grid grid-cols-2 gap-y-4 gap-x-6 text-sm">
                @foreach([
                    ['Module',     $registration->module_label],
                    ['Paiement',   strtoupper($registration->payment_method)],
                    ['Montant',    $registration->amount.' '.$registration->currency],
                    ['Inscription',$registration->created_at->format('d/m/Y H:i')],
                ] as [$k,$v])
                <div><dt class="text-gray-500">{{ $k }}</dt><dd class="font-medium text-gray-800 dark:text-white mt-0.5">{{ $v }}</dd></div>
                @endforeach
            </dl>
            @if($registration->payment_proof_path)
            <div class="mt-4">
                <a href="{{ asset('storage/'.$registration->payment_proof_path) }}" target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2 border border-blue-300 text-blue-600 rounded-xl text-sm hover:bg-blue-50">
                    <i class="bi bi-paperclip"></i>Voir la preuve de paiement
                </a>
            </div>
            @else
            <p class="mt-4 text-sm text-red-500"><i class="bi bi-exclamation-triangle mr-1"></i>Aucune preuve de paiement fournie.</p>
            @endif
        </div>
        @if($registration->motivation)
        <div class="content-card p-6">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-3">Motivation</h3>
            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ $registration->motivation }}</p>
        </div>
        @endif
    </div>

    <div class="space-y-5">
        <div class="content-card p-6">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4">Statut</h3>
            <div class="mb-4">
                @if($registration->status === 'approved')
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-700">✓ Approuvé</span>
                    @if($registration->approved_at)<p class="text-xs text-gray-400 mt-2">Le {{ $registration->approved_at->format('d/m/Y') }}</p>@endif
                @elseif($registration->status === 'rejected')
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-red-100 text-red-700">✕ Refusé</span>
                    @if($registration->admin_notes)<p class="text-xs text-gray-500 mt-2">{{ $registration->admin_notes }}</p>@endif
                @else
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-700">⏳ En attente</span>
                @endif
            </div>
            @if($registration->status === 'pending')
            <div class="space-y-2">
                <form action="{{ route('erp.academy.bootcamp.approve', $registration) }}" method="POST">
                    @csrf
                    <button class="w-full py-2.5 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700">
                        <i class="bi bi-check-lg mr-1"></i>Approuver l'inscription
                    </button>
                </form>
                <form action="{{ route('erp.academy.bootcamp.reject', $registration) }}" method="POST">
                    @csrf
                    <textarea name="reason" placeholder="Motif du refus..." rows="2"
                              class="w-full border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:text-white mb-2 focus:outline-none"></textarea>
                    <button class="w-full py-2.5 border border-red-300 text-red-600 rounded-xl text-sm font-medium hover:bg-red-50">
                        <i class="bi bi-x-lg mr-1"></i>Refuser l'inscription
                    </button>
                </form>
            </div>
            @endif
        </div>
        <div class="content-card p-4">
            <a href="{{ route('erp.academy.bootcamp.index') }}" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 hover:text-blue-600">
                <i class="bi bi-arrow-left"></i>Retour à la liste
            </a>
        </div>
    </div>
</div>
@endsection

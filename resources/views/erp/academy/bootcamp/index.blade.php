@extends('erp.layouts.app')
@section('title','Bootcamp AI 2026 — Inscriptions')
@section('page-title','Bootcamp AI 2026')
@section('page-subtitle','Gestion des inscriptions et validations de paiement')

@section('content')
{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
    @foreach([
        ['Total',    $stats['total'],    'bi-people',          'blue'],
        ['En attente', $stats['pending'], 'bi-hourglass-split', 'yellow'],
        ['Approuvés', $stats['approved'],'bi-check-circle',    'green'],
        ['Refusés',  $stats['rejected'], 'bi-x-circle',        'red'],
        ['Premium',  $stats['premium'],  'bi-star',            'purple'],
        ['Rev. USD', '$'.number_format($stats['revenue'],0), 'bi-currency-dollar', 'gold'],
    ] as [$label, $val, $icon, $col])
    <div class="content-card p-4 text-center">
        <i class="bi {{ $icon }} text-2xl text-{{ $col }}-500 mb-1 block"></i>
        <div class="text-xl font-bold text-gray-800 dark:text-white">{{ $val }}</div>
        <div class="text-xs text-gray-500">{{ $label }}</div>
    </div>
    @endforeach
</div>

{{-- Filters + actions --}}
<div class="content-card mb-4 px-5 py-3 flex flex-wrap items-center gap-3">
    <div class="flex gap-2 flex-wrap">
        @foreach(['all'=>'Tous','pending'=>'En attente','approved'=>'Approuvés','rejected'=>'Refusés'] as $s=>$l)
        <a href="{{ route('erp.academy.bootcamp.index', ['status'=>$s]) }}"
           class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                  {{ $status===$s ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-300' }}">
            {{ $l }}
        </a>
        @endforeach
    </div>
    <div class="ml-auto flex gap-2">
        <a href="{{ url('/bootcamp-ai-2026') }}" target="_blank"
           class="px-4 py-2 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-400 dark:hover:bg-slate-700">
            <i class="bi bi-eye mr-1"></i>Voir la page
        </a>
        <a href="{{ route('erp.academy.bootcamp.export') }}"
           class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
            <i class="bi bi-download mr-1"></i>Export CSV
        </a>
    </div>
</div>

{{-- Table --}}
<div class="content-card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-100 dark:border-slate-700">
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Participant</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Module</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Paiement</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Montant</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Statut</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
            @forelse($registrations as $r)
            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-800 dark:text-white">{{ $r->full_name }}</div>
                    <div class="text-xs text-gray-400">{{ $r->email }}</div>
                    @if($r->phone)<div class="text-xs text-gray-400">{{ $r->phone }}</div>@endif
                </td>
                <td class="px-4 py-3">
                    <div class="text-gray-700 dark:text-gray-300">{{ $r->module_label }}</div>
                    @if($r->profession)<div class="text-xs text-gray-400">{{ $r->profession }}</div>@endif
                </td>
                <td class="px-4 py-3">
                    <span class="capitalize">{{ $r->payment_method }}</span>
                    @if($r->payment_proof_path)
                    <a href="{{ asset('storage/'.$r->payment_proof_path) }}" target="_blank"
                       class="ml-1 text-blue-500 hover:underline text-xs"><i class="bi bi-paperclip"></i>Preuve</a>
                    @else
                    <span class="ml-1 text-red-400 text-xs">Sans preuve</span>
                    @endif
                </td>
                <td class="px-4 py-3 font-semibold text-gray-800 dark:text-white">
                    {{ $r->amount }} {{ $r->currency }}
                </td>
                <td class="px-4 py-3">
                    @if($r->status === 'approved')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">✓ Approuvé</span>
                    @elseif($r->status === 'rejected')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">✕ Refusé</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">⏳ En attente</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-gray-400">{{ $r->created_at->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('erp.academy.bootcamp.show', $r) }}"
                           class="text-blue-500 hover:text-blue-700 text-xs"><i class="bi bi-eye"></i></a>
                        @if($r->status === 'pending')
                        <form action="{{ route('erp.academy.bootcamp.approve', $r) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-green-600 hover:text-green-800 text-xs font-semibold">
                                <i class="bi bi-check-lg"></i> Approuver
                            </button>
                        </form>
                        <button onclick="showRejectModal({{ $r->id }})" class="text-red-500 hover:text-red-700 text-xs">
                            <i class="bi bi-x-lg"></i> Refuser
                        </button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Aucune inscription.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3">{{ $registrations->links() }}</div>
</div>

{{-- Reject modal --}}
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:50;align-items:center;justify-content:center">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-2xl">
        <h3 class="font-bold text-gray-800 dark:text-white mb-4">Motif du refus</h3>
        <form id="rejectForm" method="POST">
            @csrf
            <textarea name="reason" rows="3" placeholder="Motif (optionnel)..."
                      class="w-full border border-gray-200 dark:border-slate-600 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:text-white focus:outline-none mb-3"></textarea>
            <div class="flex gap-2">
                <button type="button" onclick="document.getElementById('rejectModal').style.display='none'"
                        class="flex-1 py-2 border border-gray-200 rounded-xl text-sm text-gray-600">Annuler</button>
                <button type="submit" class="flex-1 py-2 bg-red-600 text-white rounded-xl text-sm font-semibold">Refuser</button>
            </div>
        </form>
    </div>
</div>
<script>
function showRejectModal(id) {
    document.getElementById('rejectForm').action = '/erp/academy/bootcamp/' + id + '/reject';
    document.getElementById('rejectModal').style.display = 'flex';
}
</script>
@endsection

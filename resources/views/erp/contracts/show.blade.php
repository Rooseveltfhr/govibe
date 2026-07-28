@extends('erp.layouts.app')
@section('title', 'Contrat '.$contract->reference)

@section('content')

{{-- Screen actions --}}
<div class="content-card mb-4 no-print">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center gap-3">
      <a href="{{ route('erp.contracts.index') }}" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-arrow-left"></i>
      </a>
      <div>
        <h1 class="text-lg font-bold text-gray-900">{{ $contract->reference }}</h1>
        <p class="text-sm text-gray-500">{{ $contract->title }}</p>
      </div>
    </div>

    <div class="flex gap-2 flex-wrap">
      {{-- Print --}}
      <button onclick="window.print()" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-gray-200 transition">
        <i class="fas fa-print mr-1"></i> Imprimer
      </button>

      {{-- Mark signed --}}
      @if(!$contract->signed_at)
      <form method="POST" action="{{ route('erp.contracts.sign', $contract) }}">
        @csrf @method('PATCH')
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-green-700 transition">
          <i class="fas fa-signature mr-1"></i> Marquer signé
        </button>
      </form>
      @else
      <span class="text-green-600 text-sm font-semibold flex items-center gap-1 px-3">
        <i class="fas fa-check-circle"></i> Signé le {{ $contract->signed_at->format('d/m/Y') }}
      </span>
      @endif

      {{-- Send by email --}}
      <form method="POST" action="{{ route('erp.contracts.send', $contract) }}">
        @csrf
        <button type="submit" class="btn-gold text-sm"
                onclick="return confirm('Envoyer le contrat par email à {{ $contract->client?->email }} ?')">
          <i class="fas fa-envelope mr-1"></i> Envoyer par email
        </button>
      </form>

      {{-- Edit --}}
      <a href="{{ route('erp.contracts.edit', $contract) }}"
         class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-blue-700 transition">
        <i class="fas fa-edit mr-1"></i> Modifier
      </a>
    </div>
  </div>

  @if(session('success'))
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mt-4 text-sm">
    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
  </div>
  @endif
  @if(session('error'))
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mt-4 text-sm">
    <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
  </div>
  @endif

  {{-- Meta --}}
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 pt-5 border-t border-gray-100">
    <div>
      <div class="text-xs text-gray-400 uppercase font-semibold mb-0.5">Client</div>
      <div class="font-semibold text-gray-900">{{ $contract->client?->name ?? '—' }}</div>
    </div>
    <div>
      <div class="text-xs text-gray-400 uppercase font-semibold mb-0.5">Statut</div>
      <div>{!! $contract->status_badge !!}</div>
    </div>
    <div>
      <div class="text-xs text-gray-400 uppercase font-semibold mb-0.5">Période</div>
      <div class="text-gray-700 text-sm">
        {{ $contract->start_date->format('d/m/Y') }}
        @if($contract->end_date) → {{ $contract->end_date->format('d/m/Y') }} @endif
      </div>
    </div>
    <div>
      <div class="text-xs text-gray-400 uppercase font-semibold mb-0.5">Valeur</div>
      <div class="font-bold text-gray-900">
        {{ $contract->value ? 'HTG '.number_format($contract->value,0,'.',',') : '—' }}
      </div>
    </div>
  </div>

  @if($contract->sent_at)
  <p class="text-xs text-gray-400 mt-3">
    <i class="fas fa-envelope mr-1"></i>
    Envoyé le {{ $contract->sent_at->format('d/m/Y à H:i') }}
    @if($contract->sentBy) par {{ $contract->sentBy->name }} @endif
  </p>
  @endif
</div>

{{-- Contract body --}}
<div class="bg-white rounded-2xl shadow-sm border border-gray-200" id="print-contract">
  {{-- Print header --}}
  <div class="print-only" style="display:none;">
    <div style="text-align:center;padding:20px 40px 0;border-bottom:3px solid #DC2626;">
      <div style="font-size:22px;font-weight:900;color:#0a0a0a;letter-spacing:.04em;">GOVIBE INNOVATION HUB</div>
      <div style="font-size:11px;color:#DC2626;font-weight:700;margin:.2rem 0 1rem;">
        Port-au-Prince, Haïti · govibeht.com · +509 3398-8754
      </div>
    </div>
  </div>

  <div class="p-8 md:p-12 prose max-w-none" style="font-size:.93rem;line-height:1.85;">
    {!! $contract->content !!}
  </div>
</div>

<style>
@media print {
  .no-print { display:none !important; }
  .erp-sidebar,.erp-topbar,nav,header,footer { display:none !important; }
  body { background:#fff !important; }
  #print-contract {
    border:none !important;
    box-shadow:none !important;
    border-radius:0 !important;
    margin:0 !important;
  }
  .print-only { display:block !important; }
  @page { size:A4; margin:15mm 20mm; }
}
</style>
@endsection

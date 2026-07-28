@extends('erp.layouts.app')

@section('title', 'Centre de notifications')

@section('content')
<div class="content-card">
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-xl font-bold text-gray-900">Centre de notifications</h1>
      <p class="text-sm text-gray-500 mt-0.5">Historique des WhatsApp et emails envoyés</p>
    </div>
    <button onclick="document.getElementById('modal-send').classList.remove('hidden')"
            class="btn-gold text-sm">
      <i class="fas fa-paper-plane mr-1"></i> Envoyer un message
    </button>
  </div>

  {{-- Stats --}}
  <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
    @php $cards = [
      ['label'=>'Total','value'=>$stats['total'],'icon'=>'bell','color'=>'blue'],
      ['label'=>'Envoyés','value'=>$stats['sent'],'icon'=>'check-circle','color'=>'green'],
      ['label'=>'Échecs','value'=>$stats['failed'],'icon'=>'times-circle','color'=>'red'],
      ['label'=>'WhatsApp','value'=>$stats['whatsapp'],'icon'=>'whatsapp','color'=>'green'],
      ['label'=>'Email','value'=>$stats['email'],'icon'=>'envelope','color'=>'blue'],
    ]; @endphp
    @foreach($cards as $c)
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
      <i class="fa{{ $c['icon']==='whatsapp'?'b':'s' }} fa-{{ $c['icon'] }} text-{{ $c['color'] }}-500 text-lg mb-1"></i>
      <div class="text-2xl font-bold text-gray-900">{{ $c['value'] }}</div>
      <div class="text-xs text-gray-500">{{ $c['label'] }}</div>
    </div>
    @endforeach
  </div>

  {{-- Filters --}}
  <form method="GET" class="flex flex-wrap gap-3 mb-5">
    <select name="channel" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      <option value="all" @selected($channel==='all')>Tous les canaux</option>
      <option value="whatsapp" @selected($channel==='whatsapp')>WhatsApp</option>
      <option value="email" @selected($channel==='email')>Email</option>
    </select>
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      <option value="all" @selected($status==='all')>Tous les statuts</option>
      <option value="sent" @selected($status==='sent')>Envoyés</option>
      <option value="failed" @selected($status==='failed')>Échecs</option>
      <option value="pending" @selected($status==='pending')>En attente</option>
    </select>
    <button type="submit" class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-800 transition">
      <i class="fas fa-filter mr-1"></i> Filtrer
    </button>
  </form>

  @if(session('success'))
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-4 text-sm">
    <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
  </div>
  @endif

  {{-- Table --}}
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left border-b border-gray-200 text-xs uppercase text-gray-500 font-semibold">
          <th class="pb-3 pr-4">Canal</th>
          <th class="pb-3 pr-4">Destinataire</th>
          <th class="pb-3 pr-4">Sujet / Contenu</th>
          <th class="pb-3 pr-4">Statut</th>
          <th class="pb-3 pr-4">Envoyé par</th>
          <th class="pb-3">Date</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($messages as $msg)
        <tr class="hover:bg-gray-50 transition">
          <td class="py-3 pr-4">
            <span class="text-lg">{{ $msg->channel_icon }}</span>
            <span class="text-xs font-semibold ml-1 uppercase text-gray-600">{{ $msg->channel }}</span>
          </td>
          <td class="py-3 pr-4">
            <div class="font-medium text-gray-900">{{ $msg->recipient_name ?? '—' }}</div>
            <div class="text-xs text-gray-500">{{ $msg->recipient }}</div>
          </td>
          <td class="py-3 pr-4 max-w-xs">
            @if($msg->subject)
              <div class="font-medium text-gray-800 truncate">{{ $msg->subject }}</div>
            @endif
            <div class="text-xs text-gray-500 truncate">{{ Str::limit($msg->body, 80) }}</div>
          </td>
          <td class="py-3 pr-4">
            {!! $msg->status_badge !!}
            @if($msg->error)
              <div class="text-xs text-red-500 mt-0.5 truncate max-w-[140px]" title="{{ $msg->error }}">
                {{ Str::limit($msg->error, 40) }}
              </div>
            @endif
          </td>
          <td class="py-3 pr-4 text-gray-600">{{ $msg->sentBy?->name ?? 'Auto' }}</td>
          <td class="py-3 text-gray-500 whitespace-nowrap">
            {{ $msg->sent_at?->format('d/m/Y H:i') ?? $msg->created_at->format('d/m/Y H:i') }}
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="py-10 text-center text-gray-400">
            <i class="fas fa-bell-slash text-3xl mb-2 block"></i>
            Aucun message trouvé.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-4">{{ $messages->links() }}</div>
</div>

{{-- Send Modal --}}
<div id="modal-send" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">
    <div class="flex items-center justify-between p-5 border-b border-gray-200">
      <h2 class="font-bold text-gray-900"><i class="fas fa-paper-plane text-red-500 mr-2"></i>Envoyer un message</h2>
      <button onclick="document.getElementById('modal-send').classList.add('hidden')"
              class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
    </div>
    <form action="{{ route('erp.crm.notifications.send') }}" method="POST" class="p-5 space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Canal *</label>
        <select name="channel" required onchange="toggleSubject(this.value)"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
          <option value="whatsapp">💬 WhatsApp</option>
          <option value="email">📧 Email</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Numéro / Email *</label>
        <input type="text" name="recipient" required placeholder="+509XXXXXXXX ou adresse@email.com"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Nom du destinataire</label>
        <input type="text" name="name" placeholder="Nom (optionnel)"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div id="subject-field" class="hidden">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Sujet (email)</label>
        <input type="text" name="subject" placeholder="Objet du message"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Message *</label>
        <textarea name="message" rows="4" required placeholder="Votre message..."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none"></textarea>
      </div>
      <div class="flex gap-3 pt-1">
        <button type="submit" class="btn-gold flex-1 text-sm">
          <i class="fas fa-paper-plane mr-1"></i> Envoyer
        </button>
        <button type="button" onclick="document.getElementById('modal-send').classList.add('hidden')"
                class="flex-1 bg-gray-100 text-gray-700 py-2.5 rounded-xl font-semibold text-sm hover:bg-gray-200 transition">
          Annuler
        </button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function toggleSubject(val) {
  document.getElementById('subject-field').classList.toggle('hidden', val !== 'email');
}
</script>
@endpush
@endsection

@extends('erp.layouts.app')
@section('title','Point de Vente')
@section('page-title','Point de Vente')
@section('page-subtitle','Caisse GOVIBE — ventes rapides')

@section('content')
<div x-data="pos()" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Service catalogue --}}
    <div class="lg:col-span-2">
        <div class="content-card p-5 mb-5">
            <div class="relative mb-4">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" x-model="search" placeholder="Rechercher un service..."
                       class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($services as $s)
                <button type="button"
                        @click="addItem({{ $s->id }}, '{{ addslashes($s->name) }}', {{ $s->price }})"
                        x-show="!search || '{{ strtolower($s->name) }}'.includes(search.toLowerCase())"
                        class="text-left p-3 border border-gray-200 dark:border-slate-600 rounded-xl hover:border-blue-400 hover:bg-blue-50 dark:hover:bg-slate-700 transition-colors group">
                    <p class="text-sm font-semibold text-gray-800 dark:text-white group-hover:text-blue-700 truncate">{{ $s->name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">HTG {{ number_format($s->price,0,'.',',') }}</p>
                    @if($s->category)
                    <span class="mt-1.5 inline-block text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">{{ $s->category->name }}</span>
                    @endif
                </button>
                @endforeach
                @if($services->isEmpty())
                <div class="col-span-3 py-8 text-center text-gray-400">
                    <i class="bi bi-stars text-3xl block mb-2 opacity-30"></i>
                    <p class="text-sm">Aucun service actif. <a href="{{ route('erp.admin.services.index') }}" class="text-blue-600 hover:underline">Ajouter des services</a></p>
                </div>
                @endif
            </div>
        </div>

        {{-- Today sales --}}
        @if($todaySales->count())
        <div class="content-card">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 dark:text-white text-sm">Ventes du jour</h3>
                <span class="font-bold text-green-600">HTG {{ number_format($todayTotal,0,'.',',') }}</span>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-slate-700">
                @foreach($todaySales as $sale)
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $sale->client_name }}</p>
                        <p class="text-xs text-gray-400">{{ $sale->created_at->format('H:i') }} · {{ $sale->payment_method }}</p>
                    </div>
                    <span class="font-semibold text-gray-800 dark:text-white text-sm">HTG {{ number_format($sale->total,0,'.',',') }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Cart --}}
    <div class="content-card p-5 h-fit sticky top-6">
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="bi bi-cart3 text-blue-500"></i> Panier
            <span class="ml-auto bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full" x-text="cart.length"></span>
        </h3>

        <div class="space-y-2 mb-4 min-h-[100px]" x-show="cart.length > 0">
            <template x-for="(item, i) in cart" :key="i">
                <div class="flex items-center gap-2 py-2 border-b border-gray-100 dark:border-slate-700">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700 dark:text-gray-300 truncate" x-text="item.name"></p>
                        <p class="text-xs text-gray-400" x-text="'HTG ' + formatNumber(item.price)"></p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button @click="decQty(i)" class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 text-xs hover:bg-gray-200">−</button>
                        <span class="w-5 text-center text-sm font-semibold text-gray-800 dark:text-white" x-text="item.qty"></span>
                        <button @click="item.qty++" class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 text-xs hover:bg-gray-200">+</button>
                    </div>
                    <button @click="cart.splice(i,1)" class="p-1 text-red-400 hover:text-red-600"><i class="bi bi-x text-xs"></i></button>
                </div>
            </template>
        </div>
        <div x-show="cart.length === 0" class="py-8 text-center text-gray-300 dark:text-slate-600 text-sm">
            <i class="bi bi-cart text-3xl block mb-2"></i>Panier vide
        </div>

        <div class="border-t border-gray-100 dark:border-slate-700 pt-3 mb-4">
            <div class="flex justify-between text-sm font-bold text-gray-800 dark:text-white">
                <span>Total</span>
                <span style="color:#d4a017" x-text="'HTG ' + formatNumber(total())"></span>
            </div>
        </div>

        <div class="space-y-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Client (optionnel)</label>
                <input type="text" x-model="clientName" placeholder="Nom du client..."
                       class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Mode de paiement</label>
                <select x-model="paymentMethod" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    <option value="cash">Espèces</option>
                    <option value="moncash">MonCash</option>
                    <option value="natcash">NatCash</option>
                    <option value="bank">Virement bancaire</option>
                    <option value="card">Carte</option>
                    <option value="paypal">PayPal</option>
                </select>
            </div>
            <button @click="checkout()" :disabled="cart.length === 0"
                    class="btn-gold w-full disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="bi bi-bag-check mr-2"></i>
                Encaisser · <span x-text="'HTG ' + formatNumber(total())"></span>
            </button>
            <button @click="cart = []; clientName = ''" type="button"
                    class="w-full py-2 text-xs text-gray-400 hover:text-red-500 transition-colors">
                Vider le panier
            </button>
        </div>
    </div>
</div>

<script>
function pos() {
    return {
        cart: [],
        search: '',
        clientName: '',
        paymentMethod: 'cash',

        addItem(id, name, price) {
            const existing = this.cart.find(i => i.id === id);
            if (existing) { existing.qty++; return; }
            this.cart.push({ id, name, price, qty: 1 });
        },

        decQty(i) {
            if (this.cart[i].qty <= 1) this.cart.splice(i, 1);
            else this.cart[i].qty--;
        },

        total() {
            return this.cart.reduce((s, i) => s + i.price * i.qty, 0);
        },

        formatNumber(n) {
            return Number(n || 0).toLocaleString('fr-HT', { minimumFractionDigits: 0 });
        },

        async checkout() {
            if (!this.cart.length) return;
            const payload = {
                items: this.cart.map(i => ({ id: i.id, qty: i.qty })),
                payment_method: this.paymentMethod,
                client_name: this.clientName || null,
                _token: document.querySelector('meta[name=csrf-token]').content
            };
            try {
                const r = await fetch('{{ route("erp.pos.sale") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': payload._token },
                    body: JSON.stringify(payload)
                });
                const data = await r.json();
                if (data.success) {
                    alert('Vente enregistrée ! Total: HTG ' + this.formatNumber(data.total));
                    this.cart = [];
                    this.clientName = '';
                    location.reload();
                } else { alert('Erreur: ' + data.error); }
            } catch(e) { alert('Erreur réseau.'); }
        }
    }
}
</script>
@endsection

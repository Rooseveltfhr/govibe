@extends('erp.layouts.app')
@section('title','Nouvelle facture')
@section('page-title','Nouvelle facture')
@section('page-subtitle','Créer une facture client')

@section('content')
<div class="max-w-4xl" x-data="invoiceBuilder()">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('erp.invoices.index') }}" class="p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl">
            <i class="bi bi-arrow-left text-gray-500"></i>
        </a>
        <h2 class="font-bold text-gray-800 dark:text-white">Nouvelle facture</h2>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
        @foreach($errors->all() as $e)<p class="text-red-600 text-sm">• {{ $e }}</p>@endforeach
    </div>
    @endif

    <form action="{{ route('erp.invoices.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left: items --}}
            <div class="lg:col-span-2 space-y-5">
                <div class="content-card p-5">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-4">Lignes de facturation</h3>

                    <div class="space-y-3 mb-4">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="grid grid-cols-12 gap-2 items-start">
                                <div class="col-span-5">
                                    <input type="text" :name="`items[${index}][description]`" x-model="item.description"
                                           placeholder="Description du service..." required
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                                </div>
                                <div class="col-span-2">
                                    <input type="number" :name="`items[${index}][quantity]`" x-model.number="item.quantity"
                                           placeholder="Qté" min="1" required @input="recalc()"
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                                </div>
                                <div class="col-span-3">
                                    <input type="number" :name="`items[${index}][unit_price]`" x-model.number="item.unit_price"
                                           placeholder="Prix unitaire" min="0" step="0.01" required @input="recalc()"
                                           class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                                </div>
                                <div class="col-span-1 flex items-center justify-center pt-2">
                                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300" x-text="formatNumber(item.quantity * item.unit_price)"></span>
                                </div>
                                <div class="col-span-1 flex items-center justify-center">
                                    <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                                            class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition-colors">
                                        <i class="bi bi-x-circle text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addItem()"
                            class="w-full py-2.5 border-2 border-dashed border-gray-200 dark:border-slate-600 rounded-xl text-sm text-gray-400 hover:border-blue-300 hover:text-blue-500 transition-colors">
                        <i class="bi bi-plus-circle mr-2"></i>Ajouter une ligne
                    </button>

                    {{-- Quick add from service --}}
                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
                        <label class="block text-xs font-medium text-gray-500 mb-2">Ajouter un service du catalogue</label>
                        <select @change="addFromService($event)"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            <option value="">-- Choisir un service --</option>
                            @foreach($services as $s)
                            <option value="{{ $s->id }}" data-name="{{ $s->name }}" data-price="{{ $s->price }}">
                                {{ $s->name }} — HTG {{ number_format($s->price,0,'.',',') }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="content-card p-5">
                    <h3 class="font-semibold text-gray-800 dark:text-white mb-3">Notes</h3>
                    <textarea name="notes" rows="3" placeholder="Conditions de paiement, remarques..."
                              class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 resize-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- Right: meta --}}
            <div class="space-y-5">
                <div class="content-card p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Client *</label>
                        <select name="client_id" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            <option value="">-- Sélectionner --</option>
                            @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ old('client_id')==$c->id?'selected':'' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Projet</label>
                        <select name="project_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            <option value="">-- Aucun --</option>
                            @foreach($projects as $p)
                            <option value="{{ $p->id }}" {{ old('project_id')==$p->id?'selected':'' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Date d'échéance *</label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}" required
                               class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Taxe (%)</label>
                            <input type="number" name="tax_rate" x-model.number="taxRate" @input="recalc()"
                                   value="{{ old('tax_rate',0) }}" min="0" max="100" step="0.01"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Remise (HTG)</label>
                            <input type="number" name="discount" x-model.number="discount" @input="recalc()"
                                   value="{{ old('discount',0) }}" min="0" step="0.01"
                                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                        </div>
                    </div>
                </div>

                {{-- Totals --}}
                <div class="content-card p-5">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-500 dark:text-gray-400">
                            <span>Sous-total</span>
                            <span x-text="'HTG ' + formatNumber(subtotal)">HTG 0</span>
                        </div>
                        <div class="flex justify-between text-gray-500 dark:text-gray-400" x-show="taxRate > 0">
                            <span x-text="'Taxe (' + taxRate + '%)'">Taxe</span>
                            <span x-text="'HTG ' + formatNumber(taxAmount)">HTG 0</span>
                        </div>
                        <div class="flex justify-between text-gray-500 dark:text-gray-400" x-show="discount > 0">
                            <span>Remise</span>
                            <span x-text="'- HTG ' + formatNumber(discount)">HTG 0</span>
                        </div>
                        <div class="flex justify-between font-bold text-gray-800 dark:text-white text-base pt-2 border-t border-gray-100 dark:border-slate-700">
                            <span>Total</span>
                            <span x-text="'HTG ' + formatNumber(total)">HTG 0</span>
                        </div>
                    </div>
                    <button type="submit" class="btn-gold w-full mt-4">
                        <i class="bi bi-check-lg mr-2"></i>Créer la facture
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function invoiceBuilder() {
    return {
        items: [{ description: '', quantity: 1, unit_price: 0 }],
        taxRate: {{ old('tax_rate', 0) }},
        discount: {{ old('discount', 0) }},
        subtotal: 0, taxAmount: 0, total: 0,

        addItem() { this.items.push({ description: '', quantity: 1, unit_price: 0 }); },
        removeItem(i) { this.items.splice(i, 1); this.recalc(); },

        addFromService(e) {
            const opt = e.target.selectedOptions[0];
            if (!opt.value) return;
            this.items.push({
                description: opt.dataset.name,
                quantity: 1,
                unit_price: parseFloat(opt.dataset.price) || 0
            });
            e.target.value = '';
            this.recalc();
        },

        recalc() {
            this.subtotal = this.items.reduce((s, i) => s + (i.quantity * i.unit_price), 0);
            this.taxAmount = this.subtotal * (this.taxRate / 100);
            this.total = this.subtotal + this.taxAmount - this.discount;
        },

        formatNumber(n) {
            return Number(n || 0).toLocaleString('fr-HT', { minimumFractionDigits: 2 });
        }
    }
}
</script>
@endsection

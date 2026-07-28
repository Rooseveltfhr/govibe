@extends('erp.layouts.app')
@section('title','Point de Vente')
@section('page-title','Point de Vente')
@section('page-subtitle','Caisse GOVIBE — ventes rapides')

@push('head')
<style>
/* ── Impression thermique 80 mm ─────────────────────────────────── */
@media print {
    @page { size: 80mm auto; margin: 4mm 3mm; }
    body, html { background: #fff !important; }
    .erp-sidebar, .erp-topbar, nav, header, footer,
    .no-print, [x-data] { display: none !important; }
    #print-target { display: block !important; }
    #print-target * { color: #000 !important; }
}
#print-target {
    display: none;
    width: 74mm;
    font-family: 'Courier New', Courier, monospace;
    font-size: 10pt;
    line-height: 1.45;
    color: #000;
}
#print-target .rh { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 3mm; margin-bottom: 3mm; }
#print-target .rh .logo { font-size: 15pt; font-weight: 700; letter-spacing: 1px; }
#print-target .rh .sub  { font-size: 8pt; }
#print-target .rm div   { display: flex; justify-content: space-between; font-size: 9pt; }
#print-target table     { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin: 2mm 0; }
#print-target table th  { border-bottom: 1px solid #000; text-align: left; padding: 1mm 0; font-size: 8.5pt; }
#print-target table td  { padding: 0.8mm 0; vertical-align: top; }
#print-target .qty      { width: 7mm; text-align: center; }
#print-target .upr      { width: 16mm; text-align: right; }
#print-target .ltl      { width: 18mm; text-align: right; }
#print-target .rt       { border-top: 1px solid #000; padding-top: 2mm; margin-top: 2mm; font-size: 10pt; }
#print-target .rt div   { display: flex; justify-content: space-between; }
#print-target .rtbig    { font-size: 13pt; font-weight: 700; border-top: 1px dashed #000; padding-top: 2mm; margin-top: 1mm; }
#print-target .rpm div  { display: flex; justify-content: space-between; font-size: 9pt; border-top: 1px dashed #000; padding-top: 2mm; margin-top: 2mm; }
#print-target .rf       { text-align: center; font-size: 8pt; border-top: 1px dashed #000; padding-top: 2mm; margin-top: 3mm; }
#print-target .stamp    { text-align: center; font-size: 16pt; font-weight: 700; letter-spacing: 3px; border: 2px solid #000; padding: 2mm; margin-bottom: 3mm; }
</style>
@endpush

@section('content')
<div x-data="pos()" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Catalogue de services ────────────────────────────────── --}}
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

        {{-- ── Ventes du jour ───────────────────────────────────── --}}
        @if($todaySales->count())
        <div class="content-card no-print">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 dark:text-white text-sm">Ventes du jour</h3>
                <span class="font-bold text-green-600">HTG {{ number_format($todayTotal,0,'.',',') }}</span>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-slate-700">
                @foreach($todaySales as $tx)
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $tx->reference }}
                            @if($tx->client_id) · {{ $tx->client->name ?? '' }} @endif
                        </p>
                        <p class="text-xs text-gray-400">
                            {{ $tx->created_at->format('H:i') }} ·
                            {{ $tx->payments->first()->method ?? '—' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-gray-800 dark:text-white text-sm">HTG {{ number_format($tx->total,0,'.',',') }}</span>
                        <a href="{{ route('erp.pos.receipt', $tx->reference) }}" target="_blank"
                           class="text-gray-400 hover:text-blue-500 text-xs" title="Imprimer reçu">
                            <i class="bi bi-printer"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- ── Panier ───────────────────────────────────────────────── --}}
    <div class="content-card p-5 h-fit sticky top-6 no-print">
        <h3 class="font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
            <i class="bi bi-cart3 text-blue-500"></i> Panier
            <span class="ml-auto bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full" x-text="cart.length"></span>
        </h3>

        <div class="space-y-2 mb-4 min-h-[80px]" x-show="cart.length > 0">
            <template x-for="(item, i) in cart" :key="i">
                <div class="flex items-center gap-2 py-2 border-b border-gray-100 dark:border-slate-700">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700 dark:text-gray-300 truncate" x-text="item.name"></p>
                        <p class="text-xs text-gray-400" x-text="'HTG ' + fmt(item.price)"></p>
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

        <div class="border-t border-gray-100 dark:border-slate-700 pt-3 mb-3">
            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                <span>Sous-total</span>
                <span x-text="'HTG ' + fmt(subtotal())"></span>
            </div>
            <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                <label class="shrink-0">Remise (HTG)</label>
                <input type="number" x-model.number="discount" min="0" :max="subtotal()"
                       class="w-24 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white focus:outline-none">
            </div>
            <div class="flex justify-between font-bold text-gray-800 dark:text-white">
                <span>Total</span>
                <span style="color:#d4a017" x-text="'HTG ' + fmt(total())"></span>
            </div>
        </div>

        <div class="space-y-2">
            <input type="text" x-model="clientName" placeholder="Nom du client (optionnel)"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            <select x-model="paymentMethod" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                <option value="cash">Espèces</option>
                <option value="moncash">MonCash</option>
                <option value="natcash">NatCash</option>
                <option value="bank">Virement bancaire</option>
                <option value="card">Carte bancaire</option>
                <option value="paypal">PayPal</option>
                <option value="zelle">Zelle</option>
                <option value="usdt">USDT</option>
            </select>

            <button @click="checkout()" :disabled="cart.length === 0 || loading"
                    class="btn-gold w-full disabled:opacity-50 disabled:cursor-not-allowed">
                <template x-if="!loading"><span><i class="bi bi-bag-check mr-2"></i>Encaisser · <span x-text="'HTG ' + fmt(total())"></span></span></template>
                <template x-if="loading"><span><i class="bi bi-hourglass-split mr-2 animate-spin"></i>Traitement…</span></template>
            </button>
            <button @click="printProforma()" :disabled="cart.length === 0" type="button"
                    class="w-full py-2 text-xs border border-gray-300 rounded-xl text-gray-600 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-400 dark:hover:bg-slate-700 disabled:opacity-40">
                <i class="bi bi-file-earmark-text mr-1"></i>Imprimer proforma
            </button>
            <button @click="cart = []; clientName = ''; discount = 0" type="button"
                    class="w-full py-1.5 text-xs text-gray-400 hover:text-red-500 transition-colors">
                Vider le panier
            </button>
        </div>
    </div>
</div>

{{-- ── Modal reçu après vente ─────────────────────────────────────── --}}
<div x-show="receipt" x-cloak
     class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 no-print"
     @click.self="receipt = null">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 max-w-sm w-full mx-4 shadow-2xl">
        <div class="text-center mb-4">
            <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                <i class="bi bi-check-circle-fill text-green-500 text-3xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white" x-text="receipt?.reference"></h3>
            <p class="text-sm text-gray-500 mt-1" x-text="'HTG ' + fmt(receipt?.total ?? 0) + ' · ' + (paymentMethodLabel(receipt?.payment) ?? '')"></p>
        </div>
        <div class="flex gap-2 mt-4">
            <button @click="doPrint(buildReceiptHTML(receipt))"
                    class="flex-1 py-2.5 text-sm font-medium border border-gray-200 rounded-xl hover:bg-gray-50 dark:border-slate-600 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-300">
                <i class="bi bi-printer mr-1"></i>Reçu
            </button>
            <button @click="printESCPOS(receipt)"
                    class="flex-1 py-2.5 text-sm font-medium border border-gray-200 rounded-xl hover:bg-gray-50 dark:border-slate-600 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-300"
                    title="Imprimante thermique USB (Chrome)">
                <i class="bi bi-receipt mr-1"></i>USB
            </button>
            <button @click="receipt = null; $nextTick(() => location.reload())"
                    class="flex-1 py-2.5 text-sm font-semibold rounded-xl text-white" style="background:#d4a017">
                <i class="bi bi-plus-lg mr-1"></i>Nouveau
            </button>
        </div>
    </div>
</div>

{{-- Zone d'impression — cachée à l'écran, affichée uniquement @media print --}}
<div id="print-target"></div>

<script>
function pos() {
    return {
        cart: [],
        search: '',
        clientName: '',
        paymentMethod: 'cash',
        discount: 0,
        loading: false,
        receipt: null,

        addItem(id, name, price) {
            const ex = this.cart.find(i => i.id === id);
            if (ex) { ex.qty++; return; }
            this.cart.push({ id, name, price, qty: 1 });
        },

        decQty(i) {
            if (this.cart[i].qty <= 1) this.cart.splice(i, 1);
            else this.cart[i].qty--;
        },

        subtotal() {
            return this.cart.reduce((s, i) => s + i.price * i.qty, 0);
        },

        total() {
            return Math.max(0, this.subtotal() - (parseFloat(this.discount) || 0));
        },

        fmt(n) {
            return Number(n || 0).toLocaleString('fr-HT', { minimumFractionDigits: 0 });
        },

        paymentMethodLabel(m) {
            const MAP = {cash:'Espèces',moncash:'MonCash',natcash:'NatCash',bank:'Virement',card:'Carte',paypal:'PayPal',zelle:'Zelle',usdt:'USDT'};
            return MAP[m] || m;
        },

        async checkout() {
            if (!this.cart.length || this.loading) return;
            this.loading = true;
            const payload = {
                items: this.cart.map(i => ({ id: i.id, qty: i.qty })),
                discount: this.discount || 0,
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
                    this.receipt = data;
                    this.cart = [];
                    this.discount = 0;
                    this.clientName = '';
                } else {
                    alert('Erreur : ' + (data.error || 'Inconnue'));
                }
            } catch (e) {
                alert('Erreur réseau : ' + e.message);
            } finally {
                this.loading = false;
            }
        },

        /* ── Impression ─────────────────────────────────────────── */

        printProforma() {
            if (!this.cart.length) return;
            const data = {
                isProforma: true,
                items: this.cart.map(i => ({ name: i.name, qty: i.qty, unit_price: i.price, line_total: i.price * i.qty })),
                subtotal: this.subtotal(),
                discount: parseFloat(this.discount) || 0,
                total: this.total(),
                sold_at: new Date().toLocaleDateString('fr-HT') + ' ' + new Date().toLocaleTimeString('fr-HT', {hour:'2-digit',minute:'2-digit'}),
            };
            doPrint(buildReceiptHTML(data));
        },

        doPrint(html) { doPrint(html); },
        buildReceiptHTML(d) { return buildReceiptHTML(d); },
        printESCPOS(d) { printESCPOS(d); },
    };
}

/* ── Fonctions globales d'impression ────────────────────────────── */

function payLabel(m) {
    return {cash:'Espèces',moncash:'MonCash',natcash:'NatCash',bank:'Virement bancaire',card:'Carte bancaire',paypal:'PayPal',zelle:'Zelle',usdt:'USDT'}[m] || m;
}

function buildReceiptHTML(d) {
    if (!d) return '';
    const disc = parseFloat(d.discount || 0);
    const rows = (d.items || []).map(it =>
        `<tr><td class="qty">${it.qty}</td><td>${it.name}</td><td class="upr">${Number(it.unit_price).toFixed(2)}</td><td class="ltl">${Number(it.line_total).toFixed(2)}</td></tr>`
    ).join('');

    const proforma = d.isProforma ? '<div class="stamp">PROFORMA</div>' : '';
    const payRow   = d.isProforma ? '' : `<div class="rpm"><div><span>${payLabel(d.payment)}</span><span>HTG ${Number(d.total).toFixed(2)}</span></div></div>`;
    const validity = d.isProforma ? '<p style="font-size:8pt">Valable 48h — document non officiel</p>' : '';
    const ref      = d.reference  ? `<div><span>Réf.</span><span>${d.reference}</span></div>` : '';

    return `${proforma}
<div class="rh"><div class="logo">GOVIBE ERP</div><div class="sub">Point de Vente</div><div class="sub">govibeht.com</div></div>
<div class="rm">
  ${ref}
  <div><span>Date</span><span>${d.sold_at || new Date().toLocaleDateString('fr-HT')}</span></div>
  ${d.client_name ? `<div><span>Client</span><span>${d.client_name}</span></div>` : ''}
</div>
<table>
  <thead><tr><th class="qty">Qté</th><th>Service</th><th class="upr">P.U.</th><th class="ltl">Total</th></tr></thead>
  <tbody>${rows}</tbody>
</table>
<div class="rt">
  <div><span>Sous-total</span><span>HTG ${Number(d.subtotal).toFixed(2)}</span></div>
  ${disc > 0 ? `<div><span>Remise</span><span>-HTG ${disc.toFixed(2)}</span></div>` : ''}
  <div class="rtbig"><span>TOTAL</span><span>HTG ${Number(d.total).toFixed(2)}</span></div>
</div>
${payRow}
<div class="rf">Merci pour votre confiance !<br>GOVIBE Innovation Hub · govibeht.com${validity}</div>`;
}

function doPrint(html) {
    if (!html) return;
    const el = document.getElementById('print-target');
    el.innerHTML = html;
    window.print();
    setTimeout(() => { el.innerHTML = ''; }, 3000);
}

/* ── ESC/POS — Web Serial API (Chrome 89+, imprimante USB/RS-232) ─ */
async function printESCPOS(d) {
    if (!d) return;
    if (!('serial' in navigator)) {
        alert('Web Serial non supporté. Utilisez Chrome 89+ avec l\'imprimante connectée en USB.');
        return;
    }
    try {
        const port = await navigator.serial.requestPort();
        await port.open({ baudRate: 9600 });
        const bytes = buildESCPOS(d);
        const writer = port.writable.getWriter();
        await writer.write(bytes);
        writer.releaseLock();
        await port.close();
    } catch (e) {
        if (e.name !== 'NotFoundError') console.warn('ESC/POS:', e);
    }
}

function buildESCPOS(d) {
    const ESC = 0x1B, GS = 0x1D, LF = 0x0A;
    const enc = new TextEncoder();
    const out = [];
    const push = a => a.forEach(b => out.push(b));
    const txt  = s => enc.encode(s).forEach(b => out.push(b));
    const nl   = (n=1) => { for(let i=0;i<n;i++) out.push(LF); };
    const init   = () => push([ESC, 0x40]);
    const center = () => push([ESC, 0x61, 0x01]);
    const left   = () => push([ESC, 0x61, 0x00]);
    const bold   = on => push([ESC, 0x45, on ? 1 : 0]);
    const dbl    = on => push([GS,  0x21, on ? 0x11 : 0x00]);
    const cut    = () => push([GS,  0x56, 0x41, 0x03]);
    const hr     = (n=32) => { txt('-'.repeat(n)); nl(); };
    const col    = (l, r, w=32) => { const gap = w - l.length - r.length; txt(l + ' '.repeat(Math.max(1, gap)) + r); nl(); };

    init();
    if (d.isProforma) { center(); bold(true); txt('-- PROFORMA --'); nl(); bold(false); }
    center(); dbl(true); bold(true); txt('GOVIBE ERP'); nl(); dbl(false); bold(false);
    center(); txt('Point de Vente'); nl(); txt('govibeht.com'); nl(2);
    left();
    if (d.reference) col('Ref:', d.reference);
    col('Date:', d.sold_at || new Date().toLocaleDateString('fr-HT'));
    if (d.client_name) col('Client:', d.client_name);
    hr();
    txt(pad('Qte',4) + pad('Service',16) + padL('Total',12)); nl();
    hr();
    (d.items || []).forEach(it => {
        txt(pad(String(it.qty),4) + pad(String(it.name).substring(0,15),16) + padL('HTG ' + Number(it.line_total).toFixed(0),12)); nl();
    });
    hr();
    col('Sous-total:', 'HTG ' + Number(d.subtotal).toFixed(2));
    const disc = parseFloat(d.discount || 0);
    if (disc > 0) col('Remise:', '-HTG ' + disc.toFixed(2));
    bold(true); dbl(true);
    col('TOTAL:', 'HTG ' + Number(d.total).toFixed(2), 32);
    dbl(false); bold(false);
    hr();
    if (!d.isProforma) col(payLabel(d.payment), 'HTG ' + Number(d.total).toFixed(2));
    nl(2); center(); txt('Merci pour votre confiance !'); nl(); txt('GOVIBE · govibeht.com'); nl(4);
    cut();
    return new Uint8Array(out);
}

function payLabel(m) {
    return {cash:'Especes',moncash:'MonCash',natcash:'NatCash',bank:'Virement',card:'Carte',paypal:'PayPal',zelle:'Zelle',usdt:'USDT'}[m]||m;
}
function pad(s,n)  { s=String(s); while(s.length<n) s+=' '; return s.substring(0,n); }
function padL(s,n) { s=String(s); while(s.length<n) s=' '+s; return s.substring(s.length-n); }
</script>
@endsection

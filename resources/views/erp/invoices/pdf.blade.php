<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size:10pt; color:#1f2937; }
.page { padding:40px; }
.header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:30px; padding-bottom:20px; border-bottom:3px solid #1e3a5f; }
.logo-box { display:flex; align-items:center; gap:10px; }
.logo-icon { width:40px; height:40px; background:#1e3a5f; border-radius:8px; display:flex; align-items:center; justify-content:center; }
.logo-icon span { color:#d4a017; font-weight:bold; font-size:18pt; }
.company-name { font-size:14pt; font-weight:bold; color:#1e3a5f; }
.company-sub { font-size:8pt; color:#6b7280; }
.invoice-meta { text-align:right; }
.invoice-number { font-size:20pt; font-weight:bold; color:#1e3a5f; font-family:monospace; }
.invoice-date { font-size:8pt; color:#6b7280; margin-top:4px; }
.badge { display:inline-block; padding:3px 8px; border-radius:4px; font-size:8pt; font-weight:bold; }
.badge-paid { background:#d1fae5; color:#059669; }
.badge-draft { background:#f3f4f6; color:#6b7280; }
.badge-sent { background:#dbeafe; color:#1d4ed8; }
.badge-overdue { background:#fee2e2; color:#dc2626; }
.parties { display:flex; gap:40px; margin-bottom:25px; }
.party { flex:1; }
.party-label { font-size:7pt; font-weight:bold; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:5px; }
.party-name { font-size:11pt; font-weight:bold; color:#1f2937; }
.party-detail { font-size:8pt; color:#6b7280; margin-top:2px; }
table { width:100%; border-collapse:collapse; margin-bottom:20px; }
thead tr { background:#1e3a5f; color:#ffffff; }
thead th { padding:8px 10px; text-align:left; font-size:8pt; font-weight:600; }
thead th:last-child { text-align:right; }
tbody tr { border-bottom:1px solid #f3f4f6; }
tbody tr:nth-child(even) { background:#f9fafb; }
tbody td { padding:7px 10px; font-size:9pt; }
tbody td:last-child { text-align:right; font-weight:600; }
.totals { display:flex; justify-content:flex-end; margin-bottom:25px; }
.totals-box { width:240px; }
.total-row { display:flex; justify-content:space-between; font-size:9pt; padding:3px 0; color:#6b7280; }
.total-final { display:flex; justify-content:space-between; font-size:12pt; font-weight:bold; padding:8px 0; border-top:2px solid #1e3a5f; color:#1e3a5f; margin-top:4px; }
.total-final .amount { color:#d4a017; }
.notes { background:#f9fafb; border-left:3px solid #d4a017; padding:10px 14px; font-size:8pt; color:#6b7280; margin-bottom:25px; }
.footer { text-align:center; font-size:7pt; color:#9ca3af; border-top:1px solid #e5e7eb; padding-top:15px; }
.paid-stamp { position:absolute; top:200px; right:50px; transform:rotate(-20deg); border:3px solid #059669; color:#059669; font-size:24pt; font-weight:bold; padding:5px 15px; opacity:0.3; border-radius:4px; }
</style>
</head>
<body>
<div class="page">
    @if($invoice->status === 'paid')
    <div class="paid-stamp">PAYÉE</div>
    @endif

    <div class="header">
        <div class="logo-box">
            <div class="logo-icon"><span>G</span></div>
            <div>
                <div class="company-name">GOVIBE Innovation Hub</div>
                <div class="company-sub">Port-au-Prince, Haïti | govibeht@gmail.com</div>
            </div>
        </div>
        <div class="invoice-meta">
            <div class="invoice-number">{{ $invoice->reference }}</div>
            <div class="invoice-date">Émise le {{ $invoice->issued_date->format('d/m/Y') }}</div>
            <div class="invoice-date">Échéance: {{ $invoice->due_date->format('d/m/Y') }}</div>
            <div style="margin-top:6px">
                @php
                $bc=['draft'=>'badge-draft','sent'=>'badge-sent','paid'=>'badge-paid','overdue'=>'badge-overdue'];
                $bl=['draft'=>'BROUILLON','sent'=>'ENVOYÉE','paid'=>'PAYÉE','overdue'=>'ÉCHUS'];
                @endphp
                <span class="badge {{ $bc[$invoice->status] ?? 'badge-draft' }}">{{ $bl[$invoice->status] ?? strtoupper($invoice->status) }}</span>
            </div>
        </div>
    </div>

    <div class="parties">
        <div class="party">
            <div class="party-label">De</div>
            <div class="party-name">GOVIBE Innovation Hub</div>
            <div class="party-detail">Port-au-Prince, Haïti</div>
            <div class="party-detail">govibeht@gmail.com</div>
        </div>
        <div class="party">
            <div class="party-label">Facturé à</div>
            <div class="party-name">{{ $invoice->client->name ?? '—' }}</div>
            @if($invoice->client?->email)<div class="party-detail">{{ $invoice->client->email }}</div>@endif
            @if($invoice->client?->phone)<div class="party-detail">{{ $invoice->client->phone }}</div>@endif
            @if($invoice->client?->city)<div class="party-detail">{{ $invoice->client->city }}, Haïti</div>@endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="width:60px;text-align:center">Qté</th>
                <th style="width:100px;text-align:right">Prix unit. (HTG)</th>
                <th style="width:110px">Total (HTG)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td style="text-align:center">{{ $item->quantity }}</td>
                <td style="text-align:right">{{ number_format($item->unit_price,2,'.',',') }}</td>
                <td>{{ number_format($item->total,2,'.',',') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-box">
            <div class="total-row"><span>Sous-total</span><span>HTG {{ number_format($invoice->subtotal,2,'.',',') }}</span></div>
            @if($invoice->tax_rate > 0)
            <div class="total-row"><span>Taxe ({{ $invoice->tax_rate }}%)</span><span>HTG {{ number_format($invoice->tax_amount,2,'.',',') }}</span></div>
            @endif
            @if($invoice->discount > 0)
            <div class="total-row"><span>Remise</span><span>- HTG {{ number_format($invoice->discount,2,'.',',') }}</span></div>
            @endif
            <div class="total-final">
                <span>Total dû</span>
                <span class="amount">HTG {{ number_format($invoice->total,2,'.',',') }}</span>
            </div>
        </div>
    </div>

    @if($invoice->notes)
    <div class="notes"><strong>Notes:</strong> {{ $invoice->notes }}</div>
    @endif

    <div class="footer">
        GOVIBE Innovation Hub — Tous droits réservés {{ date('Y') }} — Merci pour votre confiance
    </div>
</div>
</body>
</html>

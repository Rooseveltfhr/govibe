<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Reçu {{ $tx->reference }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm 3mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10pt;
            line-height: 1.45;
            color: #000;
            background: #fff;
            width: 74mm;
            margin: 0 auto;
            padding: 4mm 0;
        }
        .center { text-align: center; }
        .logo   { font-size: 15pt; font-weight: 700; letter-spacing: 1px; }
        .sub    { font-size: 8pt; }
        .sep    { border-bottom: 1px dashed #000; margin: 3mm 0; }
        .row    { display: flex; justify-content: space-between; font-size: 9.5pt; margin: 0.5mm 0; }
        table   { width: 100%; border-collapse: collapse; font-size: 9pt; margin: 2mm 0; }
        th      { border-bottom: 1px solid #000; text-align: left; padding: 1mm 0; font-size: 8.5pt; }
        td      { padding: 0.8mm 0; vertical-align: top; }
        .qty    { width: 7mm; text-align: center; }
        .upr    { width: 16mm; text-align: right; }
        .ltl    { width: 18mm; text-align: right; }
        .total-row { display: flex; justify-content: space-between; margin: 0.5mm 0; }
        .big    { font-size: 13pt; font-weight: 700; border-top: 1px dashed #000; padding-top: 2mm; margin-top: 1mm; }
        .pay    { border-top: 1px dashed #000; padding-top: 2mm; margin-top: 2mm; }
        .foot   { text-align: center; font-size: 8pt; border-top: 1px dashed #000; padding-top: 2mm; margin-top: 3mm; }
        .noprint { display: none; }
        @media screen {
            body { width: 100%; max-width: 340px; padding: 20px; }
            .noprint { display: block; margin-bottom: 16px; }
            .noprint button { padding: 8px 20px; background: #1d4ed8; color: #fff; border: 0; border-radius: 8px; cursor: pointer; font-size: 14px; margin-right: 8px; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button onclick="window.print()">🖨️ Imprimer</button>
        <button onclick="history.back()">← Retour</button>
    </div>

    <div class="center">
        <div class="logo">GOVIBE ERP</div>
        <div class="sub">Point de Vente</div>
        <div class="sub">govibeht.com</div>
    </div>
    <div class="sep"></div>

    <div class="row"><span>Réf.</span><span>{{ $tx->reference }}</span></div>
    <div class="row"><span>Date</span><span>{{ $tx->created_at->format('d/m/Y H:i') }}</span></div>
    @if($tx->client)
    <div class="row"><span>Client</span><span>{{ $tx->client->name }}</span></div>
    @endif
    @if($tx->servedBy)
    <div class="row"><span>Caissier</span><span>{{ $tx->servedBy->name }}</span></div>
    @endif

    <div class="sep"></div>
    <table>
        <thead>
            <tr><th class="qty">Qté</th><th>Service</th><th class="upr">P.U.</th><th class="ltl">Total</th></tr>
        </thead>
        <tbody>
            @foreach($tx->items as $item)
            <tr>
                <td class="qty">{{ (int)$item->quantity }}</td>
                <td>{{ $item->description }}</td>
                <td class="upr">{{ number_format($item->unit_price,2) }}</td>
                <td class="ltl">{{ number_format($item->total,2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="sep"></div>

    <div class="total-row"><span>Sous-total</span><span>HTG {{ number_format($tx->subtotal,2) }}</span></div>
    @if($tx->discount > 0)
    <div class="total-row"><span>Remise</span><span>-HTG {{ number_format($tx->discount,2) }}</span></div>
    @endif
    @if($tx->tax > 0)
    <div class="total-row"><span>Taxes</span><span>HTG {{ number_format($tx->tax,2) }}</span></div>
    @endif
    <div class="total-row big"><span>TOTAL</span><span>HTG {{ number_format($tx->total,2) }}</span></div>

    @if($tx->payments->count())
    <div class="pay">
        @foreach($tx->payments as $pay)
        <div class="total-row">
            <span>{{ ['cash'=>'Espèces','moncash'=>'MonCash','natcash'=>'NatCash','bank'=>'Virement','card'=>'Carte','paypal'=>'PayPal','zelle'=>'Zelle','usdt'=>'USDT'][$pay->method] ?? $pay->method }}</span>
            <span>HTG {{ number_format($pay->amount,2) }}</span>
        </div>
        @endforeach
    </div>
    @endif

    <div class="foot">
        Merci pour votre confiance !<br>
        GOVIBE Innovation Hub · govibeht.com
    </div>
</body>
</html>

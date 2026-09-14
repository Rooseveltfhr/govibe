{{-- TAGTOA POS — un reçu, prêt à imprimer.

     Page AUTONOME : on imprime un reçu, pas une barre latérale. La largeur est
     calée sur 58 mm, la taille des imprimantes thermiques qu'un commerce
     haïtien achète réellement — tout ce qui dépasse est coupé net par le
     rouleau, sans avertissement. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->reference }} — TAGTOA</title>
    <style>
        :root{--bd:rgba(0,0,0,.14)}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#efefed;font:13px/1.5 ui-monospace,'SFMono-Regular',Menlo,monospace;color:#000;padding:18px 12px}
        .recu{background:#fff;max-width:300px;margin:0 auto;padding:16px 14px;box-shadow:0 4px 20px rgba(0,0,0,.1)}
        .c{text-align:center}
        h1{font-size:15px;letter-spacing:.06em;margin-bottom:2px}
        .sep{border-top:1px dashed var(--bd);margin:9px 0}
        .l{display:flex;justify-content:space-between;gap:8px}
        .l .n{flex:1;min-width:0}
        .l .q{flex:0 0 auto;color:#555}
        .tot{font-weight:700;font-size:15px}
        .mut{color:#666;font-size:11.5px}
        .barre{display:flex;gap:8px;max-width:300px;margin:14px auto 0}
        .barre button,.barre a{flex:1;padding:11px;border:0;border-radius:10px;background:#0A0A0A;color:#fff;
             font:600 13px system-ui,sans-serif;cursor:pointer;text-align:center;text-decoration:none}
        .barre .o{background:#fff;color:#0A0A0A;border:1.5px solid var(--bd)}
        /* À l'impression : rien que le reçu, sans ombre ni fond, et sans les
           boutons — un bouton imprimé est de l'encre perdue sur chaque ticket. */
        @media print{
            body{background:#fff;padding:0}
            .recu{box-shadow:none;max-width:none;width:100%;padding:0}
            .barre{display:none}
        }
    </style>
</head>
<body>
    <div class="recu">
        <div class="c">
            <h1>{{ $sale->terminal->name ?? 'TAGTOA' }}</h1>
            <div class="mut">{{ optional($sale->sold_at)->format('d/m/Y H:i') }}</div>
            <div class="mut">{{ $sale->reference }}</div>
        </div>

        <div class="sep"></div>

        @foreach($sale->items as $it)
            <div class="l">
                <span class="n">{{ $it->name }}</span>
                <span class="q">{{ rtrim(rtrim(number_format($it->qty, 3, '.', ''), '0'), '.') }} ×</span>
                <span>{{ number_format($it->price, 2) }}</span>
            </div>
            <div class="l mut" style="margin-bottom:4px">
                <span class="n"></span>
                <span>{{ \Modules\Tagtoa\App\Support\Money::format($it->line_total, $sale->currency) }}</span>
            </div>
        @endforeach

        <div class="sep"></div>

        <div class="l"><span class="n">{{ __('Sous-total') }}</span>
            <span>{{ \Modules\Tagtoa\App\Support\Money::format($sale->subtotal, $sale->currency) }}</span></div>

        @if((float) $sale->discount > 0)
            <div class="l"><span class="n">{{ __('Remise') }}</span>
                <span>-{{ \Modules\Tagtoa\App\Support\Money::format($sale->discount, $sale->currency) }}</span></div>
        @endif

        {{-- La taxe est FIGÉE sur la vente : le reçu montre ce qui a été
             collecté ce jour-là, pas ce que dirait le taux d'aujourd'hui. --}}
        @if((float) $sale->tax_total > 0)
            <div class="l"><span class="n">{{ $sale->tax_label ?: __('Taxe') }}</span>
                <span>{{ \Modules\Tagtoa\App\Support\Money::format($sale->tax_total, $sale->currency) }}</span></div>
        @endif

        <div class="sep"></div>

        <div class="l tot"><span class="n">{{ __('TOTAL') }}</span>
            <span>{{ \Modules\Tagtoa\App\Support\Money::format($sale->total, $sale->currency) }}</span></div>

        @if(is_array($sale->payments))
            <div class="sep"></div>
            @foreach($sale->payments as $p)
                <div class="l mut">
                    <span class="n">{{ \Modules\Tagtoa\App\Models\Pos\Sale::METHODS[$p['method'] ?? ''] ?? ($p['method'] ?? '') }}</span>
                    <span>{{ \Modules\Tagtoa\App\Support\Money::format($p['amount'] ?? 0, $sale->currency) }}</span>
                </div>
            @endforeach
        @endif

        @if($sale->staff)
            <div class="sep"></div>
            <div class="mut c">{{ __('Servi par') }} {{ $sale->staff->name }}</div>
        @endif

        <div class="sep"></div>
        <div class="c mut">{{ __('Merci !') }}<br>tagtoa.com</div>
    </div>

    <div class="barre">
        <button onclick="window.print()">{{ __('Imprimer') }}</button>
        <a class="o" href="{{ route('tagtoa.pos.tickets') }}">{{ __('Retour') }}</a>
    </div>
</body>
</html>

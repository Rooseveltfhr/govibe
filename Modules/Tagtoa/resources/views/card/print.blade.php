<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Carte TAGTOA') }} {{ $card->code }}</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;background:#eceee9;color:#0d140c;display:grid;place-items:center;min-height:100vh;padding:24px}
        .sheet{text-align:center}
        /* Format carte bancaire 85.6 × 54 mm */
        .card{width:340px;height:214px;border-radius:18px;background:linear-gradient(135deg,#0d140c 0%,#14361f 60%,#2cb809 130%);color:#fff;padding:20px;position:relative;overflow:hidden;box-shadow:0 16px 40px rgba(0,0,0,.25);margin:0 auto}
        .card .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:20px;letter-spacing:.03em}
        .card .brand .b{width:30px;height:30px;border-radius:8px;background:#2cb809;display:flex;align-items:center;justify-content:center;font-size:16px}
        .card .chip{width:42px;height:32px;border-radius:6px;background:linear-gradient(135deg,#e9d9a0,#c9a94e);margin:18px 0 10px}
        .card .code{font-family:ui-monospace,monospace;font-size:20px;letter-spacing:.14em;font-weight:700}
        .card .lbl{font-size:10px;opacity:.7;text-transform:uppercase;letter-spacing:.12em;margin-top:12px}
        .card .qr{position:absolute;right:16px;bottom:16px;background:#fff;border-radius:8px;padding:5px;width:74px;height:74px}
        .card .qr svg{width:100%;height:100%;display:block}
        .card .nfc{position:absolute;right:18px;top:18px;font-size:18px;opacity:.85}
        .hint{max-width:340px;margin:16px auto 0;color:#566;font-size:13px;line-height:1.5}
        .btns{margin-top:18px;display:flex;gap:8px;justify-content:center}
        .btn{border:0;border-radius:10px;padding:11px 18px;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none;display:inline-block}
        .btn-p{background:#2cb809;color:#fff}.btn-o{background:#fff;border:1px solid #ccd;color:#0d140c}
        @media print{body{background:#fff}.btns,.hint{display:none}.card{box-shadow:none;border:1px solid #ddd}}
    </style>
</head>
<body>
    <div class="sheet">
        <div class="card">
            <div class="brand"><span class="b">⚡</span> TAGTOA</div>
            <div class="chip"></div>
            <div class="lbl">{{ __('Carte') }}</div>
            <div class="code">{{ $card->code }}</div>
            <div class="lbl">{{ $card->holder_name ?: __('Carte prépayée') }}</div>
            <span class="nfc">))) </span>
            <div class="qr">{!! $qr !!}</div>
        </div>
        <p class="hint">{{ __('Imprimez et collez sur votre carte NFC vierge. Le client tape la carte (ou scanne le QR) pour recharger et payer partout sur TAGTOA.') }}</p>
        <div class="btns">
            <button class="btn btn-p" onclick="window.print()"><i></i>{{ __('Imprimer') }}</button>
            <a class="btn btn-o" href="{{ route('tagtoa.cards.show', $card->id) }}">{{ __('Retour') }}</a>
        </div>
    </div>
</body>
</html>

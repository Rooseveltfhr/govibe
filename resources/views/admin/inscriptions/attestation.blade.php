<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Attestation — {{ $inscription->nom_complet }}</title>
    <style>
        * { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 0; padding: 0; }
        body { background: white; color: #222; }
        .page { width: 740px; min-height: 520px; margin: 0 auto; padding: 50px; position: relative; border: 3px solid #1e3a5f; }
        .border-inner { position: absolute; inset: 10px; border: 1px solid #d4a017; pointer-events: none; }
        .corner { position: absolute; width: 30px; height: 30px; }
        .corner-tl { top: 20px; left: 20px; border-top: 3px solid #d4a017; border-left: 3px solid #d4a017; }
        .corner-tr { top: 20px; right: 20px; border-top: 3px solid #d4a017; border-right: 3px solid #d4a017; }
        .corner-bl { bottom: 20px; left: 20px; border-bottom: 3px solid #d4a017; border-left: 3px solid #d4a017; }
        .corner-br { bottom: 20px; right: 20px; border-bottom: 3px solid #d4a017; border-right: 3px solid #d4a017; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
        .logo-text { font-size: 28px; font-weight: bold; color: #1e3a5f; letter-spacing: 2px; }
        .logo-sub { font-size: 14px; color: #d4a017; letter-spacing: 4px; text-transform: uppercase; }
        h1 { text-align: center; font-size: 24px; font-weight: 900; color: #1e3a5f; text-transform: uppercase; letter-spacing: 3px; margin: 20px 0; }
        .divider { text-align: center; color: #d4a017; font-size: 20px; margin: 10px 0; }
        .body-text { text-align: center; font-size: 14px; line-height: 2; color: #444; margin: 20px 0; }
        .name { font-size: 22px; font-weight: bold; color: #1e3a5f; border-bottom: 2px solid #d4a017; display: inline; padding-bottom: 2px; }
        .formation { font-size: 16px; font-weight: bold; color: #d4a017; font-style: italic; }
        .info-grid { display: table; width: 100%; margin: 25px 0; }
        .info-cell { display: table-cell; text-align: center; width: 50%; }
        .info-label { font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: 1px; }
        .info-value { font-size: 13px; font-weight: bold; color: #333; margin-top: 3px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f0; }
        .numero { background: #1e3a5f; color: white; padding: 4px 14px; border-radius: 20px; font-size: 11px; font-family: monospace; }
    </style>
</head>
<body>
<div class="page">
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>

    <div class="header">
        <div class="logo-text">GOVIBE</div>
        <div class="logo-sub">Academy</div>
    </div>

    <h1>Attestation de Participation</h1>
    <div class="divider">✦ ✦ ✦</div>

    <div class="body-text">
        <p>Nous certifions que</p>
        <p style="margin: 12px 0">
            <span class="name">{{ strtoupper($inscription->nom_complet) }}</span>
        </p>
        <p>a participé à la formation</p>
        <p style="margin: 12px 0">
            <span class="formation">{{ $inscription->formation->nom ?? 'N/A' }}</span>
        </p>
        @if($inscription->formation?->date_debut)
        <p>organisée par GOVIBE Academy du
            <strong>{{ $inscription->formation->date_debut->format('d/m/Y') }}</strong>
            @if($inscription->formation->date_fin)
                au <strong>{{ $inscription->formation->date_fin->format('d/m/Y') }}</strong>
            @endif
        </p>
        @endif
    </div>

    <div class="info-grid">
        <div class="info-cell">
            <div class="info-label">N° d'inscription</div>
            <div class="info-value">{{ $inscription->numero_inscription }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Date de délivrance</div>
            <div class="info-value">{{ now()->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="footer">
        <p style="font-size:11px; color:#999; margin-bottom:8px">Cette attestation est délivrée par GOVIBE Academy — Haïti</p>
        <span class="numero">{{ $inscription->numero_inscription }}</span>
    </div>
</div>
</body>
</html>

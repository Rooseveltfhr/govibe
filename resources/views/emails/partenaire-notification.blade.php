<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
  .wrap { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
  .header { background: linear-gradient(135deg,#DC2626,#991b1b); padding: 32px 30px; text-align: center; }
  .header h1 { color: #fff; margin: 0; font-size: 22px; }
  .header p { color: rgba(255,255,255,.8); margin: 6px 0 0; font-size: 14px; }
  .body { padding: 30px; }
  .badge { display: inline-block; background: rgba(220,38,38,.1); color: #DC2626; border: 1px solid rgba(220,38,38,.25); border-radius: 50px; padding: 4px 14px; font-size: 12px; font-weight: 700; letter-spacing: .05em; margin-bottom: 20px; }
  .row { margin-bottom: 14px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px; }
  .row:last-child { border-bottom: none; }
  .label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 3px; }
  .value { font-size: 15px; color: #0f172a; }
  .desc-box { background: #f8fafc; border-radius: 8px; padding: 14px; font-size: 14px; color: #475569; line-height: 1.6; }
  .footer { background: #f8fafc; padding: 16px 30px; text-align: center; font-size: 12px; color: #94a3b8; }
  .cta { display: inline-block; background: #DC2626; color: #fff; padding: 12px 28px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 14px; margin: 16px 0; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>🤝 Nouvelle Demande de Partenariat</h1>
    <p>GOVIBE Innovation Hub — govibeht.com</p>
  </div>
  <div class="body">
    <span class="badge">NOUVEAU PARTENAIRE</span>
    <div class="row">
      <div class="label">Nom complet</div>
      <div class="value">{{ $partenaire->prenom }} {{ $partenaire->nom }}</div>
    </div>
    <div class="row">
      <div class="label">Organisation / Institution</div>
      <div class="value">{{ $partenaire->organisation ?: '—' }}</div>
    </div>
    <div class="row">
      <div class="label">Localisation</div>
      <div class="value">{{ $partenaire->ville }}, {{ $partenaire->pays }}</div>
    </div>
    <div class="row">
      <div class="label">Contact</div>
      <div class="value">📞 {{ $partenaire->telephone }} &nbsp;|&nbsp; ✉️ {{ $partenaire->email }}</div>
    </div>
    <div class="row">
      <div class="label">Type de partenariat</div>
      <div class="value">{{ \App\Models\Partenaire::typesPartenariat()[$partenaire->type_partenariat] ?? $partenaire->type_partenariat }}</div>
    </div>
    @if($partenaire->description)
    <div class="row">
      <div class="label">Description / Message</div>
      <div class="desc-box">{{ $partenaire->description }}</div>
    </div>
    @endif
    <div style="text-align:center; margin-top:20px;">
      <a href="{{ url('/erp/partenaires') }}" class="cta">Voir dans l'ERP →</a>
    </div>
  </div>
  <div class="footer">
    GOVIBE Innovation Hub &nbsp;·&nbsp; contact@govibeht.com &nbsp;·&nbsp; Port-au-Prince, Haïti<br>
    Reçu le {{ now()->format('d/m/Y à H:i') }}
  </div>
</div>
</body>
</html>

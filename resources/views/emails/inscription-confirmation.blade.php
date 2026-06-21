<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmation d'inscription</title>
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f8; margin: 0; padding: 20px; }
  .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
  .header { background: linear-gradient(135deg, #1e3a5f, #2d5a8e); padding: 40px 30px; text-align: center; }
  .logo { font-size: 28px; font-weight: 900; color: white; letter-spacing: 2px; }
  .logo span { color: #f5c518; }
  .check-icon { width: 60px; height: 60px; background: #4ade80; border-radius: 50%; margin: 20px auto; display: flex; align-items: center; justify-content: center; }
  .body { padding: 35px 30px; }
  h2 { color: #1e3a5f; font-size: 22px; margin-bottom: 10px; }
  p { color: #555; line-height: 1.7; margin: 8px 0; }
  .info-box { background: #f8fafc; border-left: 4px solid #1e3a5f; border-radius: 8px; padding: 16px 20px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #e8ecf0; }
  .info-row:last-child { border-bottom: none; }
  .info-label { color: #888; font-size: 13px; }
  .info-value { color: #333; font-size: 13px; font-weight: bold; }
  .numero { background: #1e3a5f; color: white; padding: 8px 20px; border-radius: 20px; font-family: monospace; font-size: 16px; font-weight: bold; display: inline-block; margin: 10px 0; }
  .whatsapp-btn { display: block; background: #25d366; color: white; text-decoration: none; text-align: center; padding: 14px 20px; border-radius: 10px; font-weight: bold; margin: 20px 0; }
  .footer { background: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e8ecf0; }
  .footer p { color: #aaa; font-size: 12px; margin: 4px 0; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">GOVIBE <span>Academy</span></div>
        <div style="width:60px;height:60px;background:#4ade80;border-radius:50%;margin:20px auto;display:flex;align-items:center;justify-content:center;">
            <span style="color:white;font-size:28px;font-weight:bold;">✓</span>
        </div>
        <h1 style="color:white;margin:0;font-size:20px">Inscription confirmée !</h1>
    </div>

    <div class="body">
        <h2>Bonjour {{ $inscription->nom_complet }},</h2>
        <p>Votre inscription a été <strong style="color:#1e3a5f">enregistrée avec succès</strong>. Vous recevrez bientôt les informations complémentaires concernant votre formation.</p>

        <p style="text-align:center;margin:15px 0 5px;color:#888;font-size:12px">Votre numéro d'inscription</p>
        <p style="text-align:center"><span class="numero">{{ $inscription->numero_inscription }}</span></p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Formation</span>
                <span class="info-value">{{ $inscription->formation->nom ?? '—' }}</span>
            </div>
            @if($inscription->formation?->date_debut)
            <div class="info-row">
                <span class="info-label">Date</span>
                <span class="info-value">{{ $inscription->formation->date_debut->format('d/m/Y') }}</span>
            </div>
            @endif
            @if($inscription->formation?->lieu)
            <div class="info-row">
                <span class="info-label">Lieu</span>
                <span class="info-value">{{ $inscription->formation->lieu }}</span>
            </div>
            @endif
            <div class="info-row">
                <span class="info-label">Département</span>
                <span class="info-value">{{ $inscription->departement }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ $inscription->email }}</span>
            </div>
        </div>

        @if($inscription->formation?->whatsapp_link)
        <a href="{{ $inscription->formation->whatsapp_link }}" class="whatsapp-btn">
            📱 Rejoindre le groupe WhatsApp
        </a>
        @endif

        <p style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;font-size:13px">
            ⚠️ <strong>Important :</strong> Conservez votre numéro d'inscription <strong>{{ $inscription->numero_inscription }}</strong>. Il vous sera demandé le jour de la formation.
        </p>
    </div>

    <div class="footer">
        <p><strong>GOVIBE Academy</strong> — Formation professionnelle en Haïti</p>
        <p>{{ env('ADMIN_EMAIL', 'govibeht@gmail.com') }}</p>
        <p>© {{ date('Y') }} GOVIBE Academy. Tous droits réservés.</p>
    </div>
</div>
</body>
</html>

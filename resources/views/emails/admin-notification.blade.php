<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Nouvelle inscription</title>
<style>
  body { font-family: Arial, sans-serif; background: #f4f4f8; margin: 0; padding: 20px; }
  .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; }
  .header { background: #1e3a5f; padding: 25px 30px; }
  .body { padding: 30px; }
  .badge { background: #d4a017; color: #1e3a5f; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; display: inline-block; }
  table { width: 100%; border-collapse: collapse; margin: 15px 0; }
  td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
  td:first-child { color: #888; width: 40%; }
  td:last-child { font-weight: 600; color: #333; }
  .footer { background: #f8fafc; padding: 15px 30px; text-align: center; font-size: 11px; color: #aaa; border-top: 1px solid #eee; }
  .btn { display: inline-block; background: #1e3a5f; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; margin: 10px 0; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <p style="color:#f5c518;font-size:12px;margin:0 0 5px">NOTIFICATION ADMIN</p>
        <h2 style="color:white;margin:0;font-size:18px">Nouvelle inscription reçue</h2>
    </div>
    <div class="body">
        <p style="color:#555">Une nouvelle inscription a été enregistrée sur GOVIBE Academy :</p>

        <table>
            <tr><td>N° Inscription</td><td style="font-family:monospace;color:#1e3a5f">{{ $inscription->numero_inscription }}</td></tr>
            <tr><td>Nom complet</td><td>{{ $inscription->nom_complet }}</td></tr>
            <tr><td>Sexe</td><td>{{ $inscription->sexe }}</td></tr>
            <tr><td>Téléphone</td><td>{{ $inscription->telephone }}</td></tr>
            <tr><td>Email</td><td>{{ $inscription->email }}</td></tr>
            <tr><td>Département</td><td>{{ $inscription->departement }}</td></tr>
            <tr><td>Ville</td><td>{{ $inscription->ville }}</td></tr>
            <tr><td>Profession</td><td>{{ $inscription->profession ?: '—' }}</td></tr>
            <tr><td>Niveau d'étude</td><td>{{ $inscription->niveau_etude }}</td></tr>
            <tr><td>Formation</td><td>{{ $inscription->formation->nom ?? '—' }}</td></tr>
            <tr><td>Source</td><td>{{ $inscription->source_info }}</td></tr>
            <tr><td>Date</td><td>{{ $inscription->created_at->format('d/m/Y à H:i') }}</td></tr>
        </table>

        <a href="{{ url('/admin/inscriptions/' . $inscription->id) }}" class="btn">Voir le profil complet →</a>
    </div>
    <div class="footer">
        GOVIBE Academy Admin — © {{ date('Y') }}
    </div>
</div>
</body>
</html>

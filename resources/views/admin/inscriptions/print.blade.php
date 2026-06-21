<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des participants — GOVIBE Academy</title>
    <style>
        * { font-family: Arial, sans-serif; font-size: 11px; }
        body { margin: 20px; color: #222; }
        .header { text-align: center; border-bottom: 3px solid #1e3a5f; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; color: #1e3a5f; margin: 0; }
        .header p { color: #666; margin: 4px 0 0; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 15px; color: #666; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e3a5f; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
        td { padding: 7px 6px; border-bottom: 1px solid #eee; }
        tr:nth-child(even) { background: #f9f9f9; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-m { background: #dbeafe; color: #1d4ed8; }
        .badge-f { background: #ede9fe; color: #6d28d9; }
        @media print { body { margin: 0; } button { display: none; } }
    </style>
</head>
<body>
<div class="header">
    <h1>GOVIBE Academy</h1>
    <p>Liste des participants{{ $formation ? ' — ' . $formation->nom : '' }}</p>
</div>
<div class="meta">
    <span>Total : <strong>{{ $inscriptions->count() }}</strong> participant(s)</span>
    <span>Imprimé le {{ now()->format('d/m/Y à H:i') }}</span>
</div>
<table>
    <thead>
        <tr>
            <th>N°</th>
            <th>Nom complet</th>
            <th>Sexe</th>
            <th>Téléphone</th>
            <th>Email</th>
            <th>Ville / Dép.</th>
            <th>Formation</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        @forelse($inscriptions as $i => $ins)
        <tr>
            <td>{{ $ins->numero_inscription }}</td>
            <td><strong>{{ $ins->nom_complet }}</strong></td>
            <td><span class="badge {{ $ins->sexe === 'Masculin' ? 'badge-m' : 'badge-f' }}">{{ $ins->sexe }}</span></td>
            <td>{{ $ins->telephone }}</td>
            <td>{{ $ins->email }}</td>
            <td>{{ $ins->ville }}<br><small>{{ $ins->departement }}</small></td>
            <td>{{ Str::limit($ins->formation->nom ?? '—', 25) }}</td>
            <td>{{ $ins->created_at->format('d/m/Y') }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center; color:#999; padding:20px">Aucun participant</td></tr>
        @endforelse
    </tbody>
</table>

<script>window.onload = () => window.print();</script>
</body>
</html>

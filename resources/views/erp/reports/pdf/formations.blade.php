<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport Formations {{ $year }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.5; }
  .header { background: #d97706; color: #fff; padding: 20px 24px; margin-bottom: 20px; }
  .header h1 { font-size: 18px; font-weight: bold; }
  .header .sub { font-size: 10px; opacity: 0.85; }
  .kpis { display: flex; gap: 10px; margin-bottom: 16px; }
  .kpi { flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
  .kpi .label { font-size: 8px; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; }
  .kpi .value { font-size: 18px; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #fef3c7; text-align: left; padding: 6px 8px; font-size: 8px; text-transform: uppercase; color: #92400e; border-bottom: 1px solid #fde68a; }
  td { padding: 6px 8px; font-size: 9px; border-bottom: 1px solid #f3f4f6; }
  .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 8px; text-align: center; font-size: 8px; color: #9ca3af; }
  .bar-wrap { display: flex; align-items: center; gap: 4px; }
  .bar-t { flex: 1; background: #f3f4f6; border-radius: 3px; height: 6px; }
  .bar-f { height: 6px; border-radius: 3px; background: #d97706; }
</style>
</head>
<body>

<div class="header">
  <div style="font-size:10px;opacity:.8">GOVIBE Innovation Hub</div>
  <h1>Rapport — Formations & Inscriptions</h1>
  <div class="sub">Année {{ $year }} — Généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

<div class="kpis">
  <div class="kpi"><div class="label">Formations</div><div class="value">{{ $formations->count() }}</div></div>
  <div class="kpi"><div class="label">Inscriptions totales</div><div class="value">{{ $totalInscriptions }}</div></div>
  <div class="kpi"><div class="label">Présents le jour J</div><div class="value">{{ $presents }}</div></div>
  <div class="kpi"><div class="label">Taux de présence</div><div class="value">{{ $totalInscriptions > 0 ? round($presents/$totalInscriptions*100) : 0 }}%</div></div>
</div>

<table>
  <thead>
    <tr>
      <th>Formation</th>
      <th>Date début</th>
      <th>Date fin</th>
      <th>Lieu</th>
      <th style="text-align:center">Capacité</th>
      <th style="text-align:center">Inscrits</th>
      <th style="text-align:center">Remplissage</th>
      <th style="text-align:center">Statut</th>
    </tr>
  </thead>
  <tbody>
    @forelse($formations as $f)
    <tr>
      <td><strong>{{ $f->nom }}</strong></td>
      <td>{{ $f->date_debut?->format('d/m/Y') ?? '—' }}</td>
      <td>{{ $f->date_fin?->format('d/m/Y') ?? '—' }}</td>
      <td>{{ $f->lieu ?? '—' }}</td>
      <td style="text-align:center">{{ $f->max_participants ?? '∞' }}</td>
      <td style="text-align:center"><strong>{{ $f->inscriptions_count }}</strong></td>
      <td>
        @if($f->max_participants)
        @php $pct = round($f->inscriptions_count / $f->max_participants * 100) @endphp
        <div class="bar-wrap">
          <div class="bar-t"><div class="bar-f" style="width:{{ min(100,$pct) }}%"></div></div>
          <span style="font-size:8px;width:28px;text-align:right">{{ $pct }}%</span>
        </div>
        @else
        —
        @endif
      </td>
      <td style="text-align:center">{{ $f->active ? 'Active' : 'Terminée' }}</td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center;padding:20px;color:#9ca3af">Aucune formation.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">
  GOVIBE Innovation Hub — Rapport Formations {{ $year }} — Confidentiel
</div>
</body>
</html>

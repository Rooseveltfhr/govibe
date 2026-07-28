<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport Global GOVIBE {{ $year }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.5; }

  .header { background: #DC2626; color: #fff; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
  .header h1 { font-size: 18px; font-weight: bold; }
  .header .sub { font-size: 11px; opacity: 0.85; margin-top: 2px; }
  .header .meta { text-align: right; font-size: 9px; opacity: 0.8; }

  .kpis { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
  .kpi { flex: 1; min-width: 110px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
  .kpi .label { font-size: 8px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
  .kpi .value { font-size: 16px; font-weight: bold; color: #111827; }

  .section { margin-bottom: 20px; }
  .section-title { font-size: 11px; font-weight: bold; color: #DC2626; border-bottom: 1px solid #fee2e2; padding-bottom: 4px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: .5px; }

  table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  th { background: #f9fafb; text-align: left; padding: 6px 8px; font-size: 8px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
  td { padding: 6px 8px; font-size: 9px; border-bottom: 1px solid #f3f4f6; color: #374151; }
  tr:last-child td { border-bottom: none; }

  .badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 8px; font-weight: bold; }
  .badge-green { background: #d1fae5; color: #065f46; }
  .badge-yellow { background: #fef3c7; color: #92400e; }
  .badge-red { background: #fee2e2; color: #991b1b; }

  .bar-row { display: flex; align-items: center; gap: 6px; margin-bottom: 5px; }
  .bar-label { width: 28px; font-size: 8px; color: #6b7280; }
  .bar-track { flex: 1; background: #f3f4f6; border-radius: 3px; height: 8px; }
  .bar-fill { height: 8px; border-radius: 3px; background: #DC2626; }
  .bar-value { width: 80px; text-align: right; font-size: 8px; font-weight: bold; color: #374151; }

  .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; text-align: center; font-size: 8px; color: #9ca3af; }
  .two-col { display: flex; gap: 16px; }
  .two-col > div { flex: 1; }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="sub">GOVIBE Innovation Hub</div>
    <h1>Rapport Global des Activités</h1>
    <div class="sub">Année {{ $year }}</div>
  </div>
  <div class="meta">
    Généré le {{ now()->format('d/m/Y à H:i') }}<br>
    govibeht.com
  </div>
</div>

<div class="kpis">
  <div class="kpi">
    <div class="label">Revenus facturés</div>
    <div class="value">HTG {{ number_format($stats['totalRevenue'],0,'.',',') }}</div>
  </div>
  <div class="kpi">
    <div class="label">Clients</div>
    <div class="value">{{ $stats['totalClients'] }}</div>
  </div>
  <div class="kpi">
    <div class="label">Projets</div>
    <div class="value">{{ $stats['totalProjects'] }}</div>
  </div>
  <div class="kpi">
    <div class="label">Formations</div>
    <div class="value">{{ $stats['totalFormations'] }}</div>
  </div>
  <div class="kpi">
    <div class="label">Inscriptions</div>
    <div class="value">{{ $stats['totalInscriptions'] }}</div>
  </div>
  <div class="kpi">
    <div class="label">Bootcamp</div>
    <div class="value">{{ $stats['bootcampApproved'] }}/{{ $stats['bootcampTotal'] }}</div>
  </div>
  <div class="kpi">
    <div class="label">Réservations</div>
    <div class="value">{{ $stats['totalBookings'] }}</div>
  </div>
  <div class="kpi">
    <div class="label">POS Revenue</div>
    <div class="value">HTG {{ number_format($stats['posRevenue'],0,'.',',') }}</div>
  </div>
</div>

<div class="section">
  <div class="section-title">Revenus mensuels {{ $year }}</div>
  @php $maxRev = max(array_column($monthly,'amount')) ?: 1; @endphp
  @foreach($monthly as $m)
  <div class="bar-row">
    <div class="bar-label">{{ $m['month'] }}</div>
    <div class="bar-track">
      <div class="bar-fill" style="width:{{ min(100,($m['amount']/$maxRev)*100) }}%"></div>
    </div>
    <div class="bar-value">HTG {{ number_format($m['amount'],0,'.',',') }}</div>
  </div>
  @endforeach
</div>

<div class="two-col">
  <div class="section">
    <div class="section-title">Résumé des activités</div>
    <table>
      <tr>
        <th>Activité</th>
        <th style="text-align:right">Volume</th>
      </tr>
      <tr><td>Formations GOVIBE</td><td style="text-align:right">{{ $stats['totalFormations'] }}</td></tr>
      <tr><td>Inscriptions formations</td><td style="text-align:right">{{ $stats['totalInscriptions'] }}</td></tr>
      <tr><td>Bootcamp AI 2026 — total</td><td style="text-align:right">{{ $stats['bootcampTotal'] }}</td></tr>
      <tr><td>Bootcamp AI 2026 — approuvés</td><td style="text-align:right">{{ $stats['bootcampApproved'] }}</td></tr>
      <tr><td>Réservations d'espaces</td><td style="text-align:right">{{ $stats['totalBookings'] }}</td></tr>
      <tr><td>Clients CRM</td><td style="text-align:right">{{ $stats['totalClients'] }}</td></tr>
      <tr><td>Projets actifs</td><td style="text-align:right">{{ $stats['totalProjects'] }}</td></tr>
      <tr><td>Factures en attente</td><td style="text-align:right">{{ $stats['pendingInvoices'] }}</td></tr>
    </table>
  </div>

  <div class="section">
    <div class="section-title">Revenus consolidés</div>
    <table>
      <tr>
        <th>Source</th>
        <th style="text-align:right">Montant</th>
      </tr>
      <tr>
        <td>Factures payées</td>
        <td style="text-align:right">HTG {{ number_format($stats['totalRevenue'],0,'.',',') }}</td>
      </tr>
      <tr>
        <td>Ventes POS</td>
        <td style="text-align:right">HTG {{ number_format($stats['posRevenue'],0,'.',',') }}</td>
      </tr>
      <tr style="font-weight:bold">
        <td>Total consolidé</td>
        <td style="text-align:right">HTG {{ number_format($stats['totalRevenue']+$stats['posRevenue'],0,'.',',') }}</td>
      </tr>
    </table>
  </div>
</div>

<div class="footer">
  GOVIBE Innovation Hub — Rapport confidentiel — Généré automatiquement le {{ now()->format('d/m/Y à H:i') }}
</div>

</body>
</html>

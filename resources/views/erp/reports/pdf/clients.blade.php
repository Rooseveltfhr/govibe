<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport Clients {{ $year }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; line-height: 1.5; }
  .header { background: #7c3aed; color: #fff; padding: 16px 20px; margin-bottom: 16px; }
  .header h1 { font-size: 16px; font-weight: bold; }
  .header .sub { font-size: 9px; opacity: 0.85; }
  .kpis { display: flex; gap: 8px; margin-bottom: 14px; }
  .kpi { flex: 1; border: 1px solid #e5e7eb; border-radius: 5px; padding: 8px 10px; }
  .kpi .label { font-size: 7px; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
  .kpi .value { font-size: 15px; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; font-size: 8px; }
  th { background: #ede9fe; text-align: left; padding: 5px 6px; font-size: 7px; text-transform: uppercase; color: #5b21b6; border-bottom: 1px solid #ddd6fe; }
  td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; }
  .badge { display: inline-block; padding: 1px 5px; border-radius: 8px; font-size: 7px; font-weight: bold; }
  .b-green { background: #d1fae5; color: #065f46; }
  .b-gray { background: #f3f4f6; color: #374151; }
  .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; text-align: center; font-size: 7px; color: #9ca3af; }
</style>
</head>
<body>

<div class="header">
  <div style="font-size:9px;opacity:.8">GOVIBE Innovation Hub</div>
  <h1>Rapport — Portefeuille Clients CRM</h1>
  <div class="sub">Référence année {{ $year }} — Généré le {{ now()->format('d/m/Y à H:i') }}</div>
</div>

<div class="kpis">
  <div class="kpi"><div class="label">Total clients</div><div class="value">{{ $stats['total'] }}</div></div>
  <div class="kpi"><div class="label">Clients actifs</div><div class="value">{{ $stats['active'] }}</div></div>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Client</th>
      <th>Réf.</th>
      <th>Type</th>
      <th>Email</th>
      <th>Téléphone</th>
      <th style="text-align:center">Factures</th>
      <th style="text-align:center">Réservations</th>
      <th style="text-align:right">Total facturé</th>
      <th style="text-align:center">Statut</th>
    </tr>
  </thead>
  <tbody>
    @forelse($clients as $i => $c)
    <tr style="{{ $i%2===0 ? '' : 'background:#fafafa' }}">
      <td>{{ $i+1 }}</td>
      <td><strong>{{ $c->name }}</strong></td>
      <td style="font-family:monospace;font-size:7px">{{ $c->reference_number }}</td>
      <td style="text-transform:capitalize">{{ $c->type ?? '—' }}</td>
      <td>{{ $c->email ?? '—' }}</td>
      <td>{{ $c->phone ?? '—' }}</td>
      <td style="text-align:center">{{ $c->invoices_count }}</td>
      <td style="text-align:center">{{ $c->bookings_count }}</td>
      <td style="text-align:right"><strong>HTG {{ number_format($c->total_invoiced ?? 0,0,'.',',') }}</strong></td>
      <td style="text-align:center">
        <span class="badge {{ $c->status==='active' ? 'b-green' : 'b-gray' }}">
          {{ $c->status==='active' ? 'Actif' : ucfirst($c->status ?? 'inactif') }}
        </span>
      </td>
    </tr>
    @empty
    <tr><td colspan="10" style="text-align:center;padding:16px;color:#9ca3af">Aucun client.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">
  GOVIBE Innovation Hub — Rapport Clients CRM {{ $year }} — Document confidentiel — {{ now()->format('d/m/Y') }}
</div>
</body>
</html>

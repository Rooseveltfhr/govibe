<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport Réservations {{ $year }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; line-height: 1.5; }
  .header { background: #1e3a5f; color: #fff; padding: 16px 20px; margin-bottom: 16px; }
  .header h1 { font-size: 16px; font-weight: bold; }
  .header .sub { font-size: 9px; opacity: 0.85; }
  .kpis { display: flex; gap: 8px; margin-bottom: 14px; }
  .kpi { flex: 1; border: 1px solid #e5e7eb; border-radius: 5px; padding: 8px 10px; }
  .kpi .label { font-size: 7px; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
  .kpi .value { font-size: 15px; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; font-size: 8px; }
  th { background: #dbeafe; text-align: left; padding: 5px 6px; font-size: 7px; text-transform: uppercase; color: #1e3a5f; border-bottom: 1px solid #bfdbfe; }
  td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; }
  .badge { display: inline-block; padding: 1px 5px; border-radius: 8px; font-size: 7px; font-weight: bold; }
  .b-green { background: #d1fae5; color: #065f46; }
  .b-yellow { background: #fef3c7; color: #92400e; }
  .b-red { background: #fee2e2; color: #991b1b; }
  .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; text-align: center; font-size: 7px; color: #9ca3af; }
</style>
</head>
<body>

<div class="header">
  <div style="font-size:9px;opacity:.8">GOVIBE Innovation Hub</div>
  <h1>Rapport — Réservations d'espaces {{ $year }}</h1>
  <div class="sub">
    @if($status) Filtre statut: {{ ucfirst($status) }} — @endif
    Généré le {{ now()->format('d/m/Y à H:i') }}
  </div>
</div>

<div class="kpis">
  <div class="kpi"><div class="label">Total réservations</div><div class="value">{{ $stats['total'] }}</div></div>
  <div class="kpi"><div class="label">Confirmées</div><div class="value">{{ $stats['confirmed'] }}</div></div>
  <div class="kpi"><div class="label">Revenus payés</div><div class="value">HTG {{ number_format($stats['revenue'],0,'.',',') }}</div></div>
</div>

<table>
  <thead>
    <tr>
      <th>Référence</th>
      <th>Client</th>
      <th>Espace</th>
      <th>Date début</th>
      <th>Date fin</th>
      <th style="text-align:right">Total</th>
      <th style="text-align:center">Paiement</th>
      <th style="text-align:center">Statut</th>
    </tr>
  </thead>
  <tbody>
    @forelse($bookings as $i => $b)
    <tr style="{{ $i%2===0 ? '' : 'background:#fafafa' }}">
      <td style="font-family:monospace">{{ $b->reference }}</td>
      <td><strong>{{ $b->client?->name ?? '—' }}</strong></td>
      <td>{{ $b->space?->name ?? '—' }}</td>
      <td>{{ $b->start_datetime?->format('d/m/Y H:i') ?? '—' }}</td>
      <td>{{ $b->end_datetime?->format('d/m/Y H:i') ?? '—' }}</td>
      <td style="text-align:right"><strong>HTG {{ number_format($b->total_price,0,'.',',') }}</strong></td>
      <td style="text-align:center">
        <span class="badge {{ $b->payment_status==='paid' ? 'b-green' : 'b-yellow' }}">
          {{ $b->payment_status==='paid' ? 'Payé' : ucfirst($b->payment_status ?? 'pending') }}
        </span>
      </td>
      <td style="text-align:center">
        <span class="badge {{ $b->status==='confirmed' ? 'b-green' : ($b->status==='cancelled' ? 'b-red' : 'b-yellow') }}">
          {{ $b->status==='confirmed' ? 'Confirmée' : ($b->status==='cancelled' ? 'Annulée' : 'En attente') }}
        </span>
      </td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center;padding:16px;color:#9ca3af">Aucune réservation.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">
  GOVIBE Innovation Hub — Rapport Réservations {{ $year }} — Document confidentiel — {{ now()->format('d/m/Y') }}
</div>
</body>
</html>

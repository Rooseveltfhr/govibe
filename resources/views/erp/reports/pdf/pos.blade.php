<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport POS {{ $year }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; line-height: 1.5; }
  .header { background: #059669; color: #fff; padding: 16px 20px; margin-bottom: 16px; }
  .header h1 { font-size: 16px; font-weight: bold; }
  .header .sub { font-size: 9px; opacity: 0.85; }
  .kpis { display: flex; gap: 8px; margin-bottom: 14px; }
  .kpi { flex: 1; border: 1px solid #e5e7eb; border-radius: 5px; padding: 8px 10px; }
  .kpi .label { font-size: 7px; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
  .kpi .value { font-size: 15px; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; font-size: 8px; }
  th { background: #d1fae5; text-align: left; padding: 5px 6px; font-size: 7px; text-transform: uppercase; color: #065f46; border-bottom: 1px solid #a7f3d0; }
  td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; }
  .badge { display: inline-block; padding: 1px 5px; border-radius: 8px; font-size: 7px; font-weight: bold; }
  .b-green { background: #d1fae5; color: #065f46; }
  .b-red { background: #fee2e2; color: #991b1b; }
  .b-gray { background: #f3f4f6; color: #374151; }
  .footer { margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 8px; text-align: center; font-size: 7px; color: #9ca3af; }
</style>
</head>
<body>

<div class="header">
  <div style="font-size:9px;opacity:.8">GOVIBE Innovation Hub</div>
  <h1>Rapport — Point de Vente (POS) {{ $year }}</h1>
  <div class="sub">
    @if($month) {{ $months[$month] }} {{ $year }} — @else Année complète — @endif
    Généré le {{ now()->format('d/m/Y à H:i') }}
  </div>
</div>

<div class="kpis">
  <div class="kpi"><div class="label">Transactions</div><div class="value">{{ $stats['total'] }}</div></div>
  <div class="kpi"><div class="label">Revenus POS</div><div class="value">HTG {{ number_format($stats['revenue'],0,'.',',') }}</div></div>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Référence</th>
      <th>Client</th>
      <th style="text-align:right">Sous-total</th>
      <th style="text-align:right">Remise</th>
      <th style="text-align:right">Total</th>
      <th style="text-align:center">Statut</th>
      <th>Date</th>
    </tr>
  </thead>
  <tbody>
    @forelse($transactions as $i => $t)
    <tr style="{{ $i%2===0 ? '' : 'background:#fafafa' }}">
      <td>{{ $i+1 }}</td>
      <td style="font-family:monospace">{{ $t->reference }}</td>
      <td>{{ $t->client?->name ?? '—' }}</td>
      <td style="text-align:right">{{ number_format($t->subtotal,0,'.',',') }}</td>
      <td style="text-align:right">{{ number_format($t->discount,0,'.',',') }}</td>
      <td style="text-align:right"><strong>HTG {{ number_format($t->total,0,'.',',') }}</strong></td>
      <td style="text-align:center">
        <span class="badge {{ $t->status==='completed' ? 'b-green' : ($t->status==='cancelled' ? 'b-red' : 'b-gray') }}">
          {{ $t->status==='completed' ? 'Complétée' : ($t->status==='cancelled' ? 'Annulée' : ucfirst($t->status)) }}
        </span>
      </td>
      <td>{{ $t->created_at?->format('d/m/Y H:i') }}</td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center;padding:16px;color:#9ca3af">Aucune transaction.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">
  GOVIBE Innovation Hub — Rapport POS {{ $year }} — Document confidentiel — {{ now()->format('d/m/Y') }}
</div>
</body>
</html>

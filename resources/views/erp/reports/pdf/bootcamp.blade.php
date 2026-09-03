<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport Bootcamp AI 2026</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; line-height: 1.5; }
  .header { background: #DC2626; color: #fff; padding: 16px 20px; margin-bottom: 16px; }
  .header h1 { font-size: 16px; font-weight: bold; }
  .header .sub { font-size: 9px; opacity: 0.85; }
  .kpis { display: flex; gap: 8px; margin-bottom: 14px; }
  .kpi { flex: 1; border: 1px solid #e5e7eb; border-radius: 5px; padding: 8px 10px; }
  .kpi .label { font-size: 7px; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
  .kpi .value { font-size: 15px; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; font-size: 8px; }
  th { background: #fee2e2; text-align: left; padding: 5px 6px; font-size: 7px; text-transform: uppercase; color: #991b1b; border-bottom: 1px solid #fca5a5; }
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
  <h1>Rapport — Bootcamp AI 2026</h1>
  <div class="sub">
    @if($status) Filtre: {{ ucfirst($status) }} — @endif
    Généré le {{ now()->format('d/m/Y à H:i') }}
  </div>
</div>

<div class="kpis">
  <div class="kpi"><div class="label">Total inscrits</div><div class="value">{{ $stats['total'] }}</div></div>
  <div class="kpi"><div class="label">Approuvés</div><div class="value">{{ $stats['approved'] }}</div></div>
  <div class="kpi"><div class="label">En attente</div><div class="value">{{ $stats['pending'] }}</div></div>
  <div class="kpi"><div class="label">Refusés</div><div class="value">{{ $stats['rejected'] }}</div></div>
  <div class="kpi"><div class="label">Revenus HTG</div><div class="value">{{ number_format($stats['revenue'],0,'.',',') }}</div></div>
  <div class="kpi"><div class="label">Revenus USD</div><div class="value">{{ number_format($stats['revenue_usd'],2,'.',',') }}</div></div>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Nom complet</th>
      <th>Email</th>
      <th>Téléphone</th>
      <th>Pays</th>
      <th>Profession</th>
      <th>Module</th>
      <th style="text-align:right">Montant</th>
      <th>Paiement</th>
      <th style="text-align:center">Statut</th>
      <th>Date</th>
    </tr>
  </thead>
  <tbody>
    @forelse($registrations as $i => $r)
    <tr style="{{ $i%2===0 ? '' : 'background:#fafafa' }}">
      <td>{{ $i+1 }}</td>
      <td><strong>{{ $r->full_name }}</strong></td>
      <td>{{ $r->email }}</td>
      <td>{{ $r->phone }}</td>
      <td>{{ $r->country }}</td>
      <td>{{ $r->profession }}</td>
      <td>{{ Str::limit($modules[$r->module_choice]['label'] ?? $r->module_choice, 32) }}</td>
      <td style="text-align:right">{{ $r->currency }} {{ number_format($r->amount,0,'.',',') }}</td>
      <td>{{ $r->payment_method }}</td>
      <td style="text-align:center">
        <span class="badge {{ $r->status==='approved' ? 'b-green' : ($r->status==='rejected' ? 'b-red' : 'b-yellow') }}">
          {{ $r->status==='approved' ? 'Approuvé' : ($r->status==='rejected' ? 'Refusé' : 'En attente') }}
        </span>
      </td>
      <td>{{ $r->created_at->format('d/m/Y') }}</td>
    </tr>
    @empty
    <tr><td colspan="11" style="text-align:center;padding:16px;color:#9ca3af">Aucune inscription.</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">
  GOVIBE Innovation Hub — Bootcamp AI 2026 — Document confidentiel — {{ now()->format('d/m/Y') }}
</div>
</body>
</html>

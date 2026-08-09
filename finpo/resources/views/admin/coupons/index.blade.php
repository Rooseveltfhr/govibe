@extends('layouts.admin', ['title' => 'Codes promo'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Codes promo</h1>
    <a href="{{ route('admin.coupons.create') }}" class="btn btn-fp-primary btn-sm">+ Nouveau code</a>
</div>
<div class="fp-card p-3">
    <table class="table fp-table">
        <thead><tr><th>Code</th><th>Remise</th><th>Utilisations</th><th>Expire</th><th>Statut</th><th></th></tr></thead>
        <tbody>
            @forelse ($coupons as $coupon)
                <tr>
                    <td><code>{{ $coupon->code }}</code></td>
                    <td>{{ $coupon->type === 'percent' ? '-'.$coupon->value.'%' : '-'.number_format($coupon->value, 0, ',', ' ').' HTG' }}</td>
                    <td>{{ $coupon->used }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
                    <td class="fp-muted small">{{ $coupon->expires_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>{!! $coupon->isUsable() ? '<span class="badge text-bg-success">Actif</span>' : '<span class="badge text-bg-secondary">Inactif</span>' !!}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-sm btn-fp-outline py-1">Modifier</a>
                        <form method="post" action="{{ route('admin.coupons.destroy', $coupon) }}" class="d-inline" onsubmit="return confirm('Supprimer ?');">
                            @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger py-1">✕</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="fp-muted">Aucun code promo.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

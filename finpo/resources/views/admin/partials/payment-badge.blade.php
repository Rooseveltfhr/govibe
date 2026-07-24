@if ($registration->payment_status === 'paid')
    <span class="badge text-bg-success">Payé</span>
@elseif ($registration->payment_status === 'free')
    <span class="badge text-bg-info">Gratuit</span>
@elseif ($registration->payment_status === 'refunded')
    <span class="badge text-bg-secondary">Remboursé</span>
@else
    <span class="badge text-bg-warning text-dark">En attente</span>
@endif

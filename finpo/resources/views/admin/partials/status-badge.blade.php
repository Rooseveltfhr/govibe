@if ($status === 'approved')
    <span class="badge text-bg-success">Approuvé</span>
@elseif ($status === 'pending')
    <span class="badge text-bg-warning text-dark">En attente</span>
@else
    <span class="badge text-bg-danger">Rejeté</span>
@endif

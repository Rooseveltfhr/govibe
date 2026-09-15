@php
    $statusLabels = [
        'nouvo' => __('Nouvelle'),
        'an_kou' => __('En cours'),
        'fèt' => __('Terminée'),
        'anile' => __('Annulée'),
    ];
@endphp

<x-core::layouts.admin :title="__('Commandes')">

    <h1>{{ __('Commandes') }}</h1>
    <p class="lead">{{ __('Les demandes reçues depuis le site public.') }}</p>

    <form method="GET" action="{{ route('admin.orders.index') }}">
        <div class="grid2">
            <div>
                <label for="q">{{ __('Chercher') }}
                    <span class="hint">{{ __('Référence, entreprise ou WhatsApp') }}</span>
                </label>
                <input type="text" id="q" name="q" value="{{ $q }}">
            </div>
            <div>
                <label for="status">{{ __('Statut') }}</label>
                <select id="status" name="status">
                    <option value="">{{ __('Tous') }}</option>
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row" style="margin-top:.9rem">
            <button type="submit" class="btn btn-primary">{{ __('Filtrer') }}</button>
            <a class="btn" href="{{ route('admin.orders.index') }}">{{ __('Réinitialiser') }}</a>
        </div>
    </form>

    <h2>{{ $orders->total() }} {{ __('commande(s)') }}</h2>

    @if ($orders->isEmpty())
        <p class="empty">{{ __('Aucune commande.') }}</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('Référence') }}</th>
                    <th>{{ __('Entreprise') }}</th>
                    <th>{{ __('Modèle') }}</th>
                    <th>{{ __('Mise en place') }}</th>
                    <th>{{ __('Statut') }}</th>
                    <th>{{ __('Paiements') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td class="mono"><a href="{{ route('admin.orders.show', $order) }}">{{ $order->reference }}</a></td>
                        <td>{{ $order->business_name }}<br><span class="empty">{{ $order->whatsapp }}</span></td>
                        <td>{{ $order->sector }}</td>
                        <td>{{ $order->wantsExpert() ? __('Expert') : __('Client') }}</td>
                        <td><span class="pill {{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span></td>
                        <td>{{ $order->payments_count }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem">{{ $orders->links() }}</div>
    @endif

</x-core::layouts.admin>

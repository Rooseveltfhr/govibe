@php
    $labels = [
        'nouvo' => __('Nouvelles'),
        'an_kou' => __('En cours'),
        'fèt' => __('Terminées'),
        'anile' => __('Annulées'),
    ];
@endphp

<x-core::layouts.admin :title="__('Tableau de bord')">

    <h1>{{ __('Tableau de bord') }}</h1>
    <p class="lead">{{ __("Ce qui demande une action, pas le volume.") }}</p>

    @php
        $remaining = 0;
        foreach ($setup as $step) {
            if (! $step['done']) {
                $remaining++;
            }
        }
    @endphp
    @if ($remaining > 0)
        <div class="note">
            <strong>{{ __('Mise en route') }}</strong>
            {{ __(':n étape(s) avant que la plateforme soit prête pour un vrai client.', ['n' => $remaining]) }}
            <ul style="margin:.5rem 0 0; padding-left:1.1rem">
                @foreach ($setup as $step)
                    <li style="margin:.15rem 0">
                        <span class="pill {{ $step['done'] ? 'on' : 'off' }}">{{ $step['done'] ? __('Fait') : __('À faire') }}</span>
                        @if ($step['done'])
                            {{ $step['label'] }}
                        @else
                            <a href="{{ $step['route'] }}">{{ $step['label'] }}</a>
                        @endif
                        <span class="empty">— {{ $step['hint'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tiles">
        @foreach ($labels as $key => $label)
            <div class="tile {{ $key === 'nouvo' ? 'act' : '' }}">
                <div class="n">{{ $byStatus[$key] ?? 0 }}</div>
                <div class="l">{{ $label }}</div>
            </div>
        @endforeach
        <div class="tile">
            <div class="n">{{ $agentCount }}</div>
            <div class="l">{{ __('Agents créés') }}</div>
        </div>
    </div>

    <h2>{{ __('Encaissé') }}</h2>
    @if ($paid === [])
        <p class="empty">{{ __('Aucun paiement enregistré.') }}</p>
    @else
        <div class="tiles">
            @foreach ($paid as $currency => $minor)
                <div class="tile">
                    <div class="n">{{ number_format($minor / 100, 2) }}</div>
                    <div class="l">{{ $currency }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <h2>{{ __('État du service') }}</h2>
    <div class="table-wrap">
        <table class="kv">
            <tr>
                <td>{{ __('Agents peuvent répondre') }}</td>
                <td>
                    @if ($chatProviders !== [])
                        <span class="pill on">{{ __('Oui') }}</span>
                        <span class="empty">{{ count($chatProviders) }} {{ __('fournisseur(s)') }}</span>
                    @else
                        <span class="pill off">{{ __('Non') }}</span>
                        <span class="empty">{{ __("Aucune clé d'IA — ajoutez-la dans Configuration.") }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>{{ __('Agents peuvent parler') }}</td>
                <td>
                    @if ($voiceProviders !== [])
                        <span class="pill on">{{ __('Oui') }}</span>
                    @else
                        <span class="pill off">{{ __('Non') }}</span>
                        <span class="empty">{{ __('Aucune clé de voix.') }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>{{ __('Paiement configuré') }}</td>
                <td>
                    @if ($paymentReady)
                        <span class="pill on">{{ __('Oui') }}</span>
                    @else
                        <span class="pill off">{{ __('Non') }}</span>
                        <span class="empty"><a href="{{ route('admin.payments') }}">{{ __('Configurer') }}</a></span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <h2>{{ __('Commandes à traiter') }}</h2>
    @if ($newOrders->isEmpty())
        <p class="empty">{{ __('Rien en attente.') }}</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('Référence') }}</th>
                    <th>{{ __('Entreprise') }}</th>
                    <th>{{ __('Modèle') }}</th>
                    <th>{{ __('Reçue') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($newOrders as $order)
                    <tr>
                        <td class="mono"><a href="{{ route('admin.orders.show', $order) }}">{{ $order->reference }}</a></td>
                        <td>{{ $order->business_name }}</td>
                        <td>{{ $order->sector }}</td>
                        <td class="empty">{{ $order->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

</x-core::layouts.admin>

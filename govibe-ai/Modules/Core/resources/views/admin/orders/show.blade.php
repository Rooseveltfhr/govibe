@php
    $statusLabels = [
        'nouvo' => __('Nouvelle'),
        'an_kou' => __('En cours'),
        'fèt' => __('Terminée'),
        'anile' => __('Annulée'),
    ];
    $methodLabels = [
        'moncash' => 'MonCash', 'natcash' => 'NatCash', 'unibank' => 'Unibank',
        'sogebank' => 'Sogebank', 'kach' => __('Espèces'), 'lot' => __('Autre'),
    ];
    $paid = $order->paidByCurrency();
@endphp

<x-core::layouts.admin :title="$order->reference">

    <h1>{{ $order->business_name }}</h1>
    <p class="lead">
        <span class="mono">{{ $order->reference }}</span>
        · <span class="pill {{ $order->status }}">{{ $statusLabels[$order->status] ?? $order->status }}</span>
    </p>

    <h2>{{ __('La demande') }}</h2>
    <div class="table-wrap">
        <table class="kv">
            <tr><td>{{ __('Modèle') }}</td><td>{{ $template?->label ?? $order->sector }}</td></tr>
            <tr><td>{{ __('Contact') }}</td><td>{{ $order->contact_name ?: '—' }}</td></tr>
            <tr>
                <td>{{ __('WhatsApp') }}</td>
                <td>
                    <a href="https://wa.me/{{ $order->whatsappDigits() }}" target="_blank" rel="noopener">{{ $order->whatsapp }}</a>
                </td>
            </tr>
            <tr><td>{{ __('E-mail') }}</td><td>{{ $order->email ?: '—' }}</td></tr>
            <tr><td>{{ __('Mise en place') }}</td><td>{{ $order->wantsExpert() ? __('Par un expert GOVIBE') : __('Par le client') }}</td></tr>
            <tr><td>{{ __('Canaux') }}</td><td>{{ implode(', ', $order->channels ?? []) ?: '—' }}</td></tr>
            <tr><td>{{ __('Reçue') }}</td><td>{{ $order->created_at?->format('d/m/Y H:i') }}</td></tr>
        </table>
    </div>

    <h2>{{ __('Suivi') }}</h2>
    <form method="POST" action="{{ route('admin.orders.update', $order) }}">
        @csrf
        <div class="grid2">
            <div>
                <label for="s">{{ __('Statut') }}</label>
                <select id="s" name="status">
                    @foreach ($statusLabels as $value => $label)
                        <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <label for="n">{{ __('Notes internes') }}
            <span class="hint">{{ __("Ce que le client a demandé, ce qui reste à faire.") }}</span>
        </label>
        <textarea id="n" name="notes">{{ old('notes', $order->notes) }}</textarea>
        <div class="row" style="margin-top:.9rem">
            <button type="submit" class="btn btn-primary">{{ __('Enregistrer') }}</button>
        </div>
    </form>

    <h2>{{ __('Paiements') }}</h2>
    @if ($paid === [])
        <p class="empty">{{ __('Aucun paiement enregistré.') }}</p>
    @else
        <div class="row" style="margin-bottom:.8rem">
            @foreach ($paid as $currency => $minor)
                <span class="pill on">{{ number_format($minor / 100, 2) }} {{ $currency }}</span>
            @endforeach
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Montant') }}</th>
                    <th>{{ __('Moyen') }}</th>
                    <th>{{ __('Référence') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($order->payments as $payment)
                    <tr>
                        <td>{{ $payment->received_at?->format('d/m/Y') }}</td>
                        <td>{{ $payment->formatted() }}</td>
                        <td>{{ $methodLabels[$payment->method] ?? $payment->method }}</td>
                        <td class="mono">{{ $payment->reference ?: '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.orders.payments.destroy', [$order, $payment]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">{{ __('Supprimer') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.orders.payments.store', $order) }}" style="margin-top:1.2rem">
        @csrf
        <fieldset>
            <legend>{{ __('Enregistrer un paiement') }}</legend>
            <div class="grid2">
                <div>
                    <label for="amount">{{ __('Montant') }}</label>
                    <input type="number" step="0.01" min="0.01" id="amount" name="amount" value="{{ old('amount') }}" required>
                    @error('amount') <div class="err">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label for="currency">{{ __('Devise') }}</label>
                    <select id="currency" name="currency">
                        <option value="HTG" @selected(old('currency') !== 'USD')>HTG</option>
                        <option value="USD" @selected(old('currency') === 'USD')>USD</option>
                    </select>
                </div>
                <div>
                    <label for="method">{{ __('Moyen') }}</label>
                    <select id="method" name="method">
                        @foreach ($methodLabels as $value => $label)
                            <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="received_at">{{ __('Date reçue') }}</label>
                    <input type="date" id="received_at" name="received_at" value="{{ old('received_at', now()->format('Y-m-d')) }}" required>
                </div>
                <div>
                    <label for="reference">{{ __('Référence de transaction') }}</label>
                    <input type="text" id="reference" name="reference" value="{{ old('reference') }}" maxlength="120">
                </div>
                <div>
                    <label for="pnote">{{ __('Note') }}</label>
                    <input type="text" id="pnote" name="note" value="{{ old('note') }}" maxlength="500">
                </div>
            </div>
        </fieldset>
        <div class="row">
            <button type="submit" class="btn btn-primary">{{ __('Enregistrer le paiement') }}</button>
            <a class="btn" href="{{ route('admin.orders.index') }}">{{ __('Retour') }}</a>
        </div>
    </form>

</x-core::layouts.admin>

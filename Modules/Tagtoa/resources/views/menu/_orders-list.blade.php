{{-- TAGTOA MENU — la liste des commandes du tableau de bord (page + fil de mise à jour). Variable : $orders --}}
@php use Modules\Tagtoa\App\Support\Money; @endphp
@php $done = $done ?? collect(); @endphp
@if($orders->isEmpty() && $done->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-receipt"></i>{{ __('Aucune commande.') }}</div></div>
@else
    @if($orders->isEmpty())
        <div class="card"><div class="empty"><i class="fa-solid fa-circle-check"></i>{{ __('Aucune commande en cours.') }}</div></div>
    @endif
    @php $dernierJour = null; @endphp
    @foreach($orders as $o)
        @php
            $sm = $o->status_meta;
            $suite = $o->nextStep();
            $style = \Modules\Tagtoa\App\Models\Menu\Order::ORDER_TYPE_STYLE[$o->order_type] ?? ['icon' => 'fa-receipt', 'color' => '#374151', 'bg' => '#f1f1ef'];
            $age = $o->ageMinutes();
            $retard = $o->isLate();
            $jour = $o->placed_at ? ($o->placed_at->isToday() ? __('Aujourd\'hui') : ($o->placed_at->isYesterday() ? __('Hier') : $o->placed_at->format('d/m/Y'))) : '';
        @endphp
        @if($jour !== $dernierJour)
            <h3 class="ord-day">{{ $jour }}</h3>
            @php $dernierJour = $jour; @endphp
        @endif
        <div class="card ord-card" data-order="{{ $o->id }}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
                <div>
                    <b style="font-family:var(--fh);font-size:15px">{{ $o->reference }}</b>
                    <span class="pill {{ $sm['pill'] }}">{{ __($sm['label']) }}</span>
                    {{-- Mode de service : icône + couleur (la livraison appelle un coursier, ça se voit de loin). --}}
                    <span class="pill ord-type" style="background:{{ $style['bg'] }};color:{{ $style['color'] }}"><i class="fa-solid {{ $style['icon'] }}"></i> {{ __($o->order_type_label) }}</span>
                    @if($o->isPaid())
                        <span class="pill g"><i class="fa-solid fa-check"></i> {{ __('Payé') }}@if($o->payment_method_label) · {{ __($o->payment_method_label) }}@endif</span>
                    @else
                        <span class="pill r"><i class="fa-solid fa-hourglass-half"></i> {{ __('Non payé') }}</span>
                    @endif
                    @if($age !== null && ! in_array($o->status, \Modules\Tagtoa\App\Models\Menu\Order::DONE_STATUSES, true))
                        {{-- « il y a 12 min », rouge après 15 min. Remis à jour sans recharger (data-ts) : voir la page. --}}
                        <span class="ord-age {{ $retard ? 'late' : '' }}" data-ts="{{ $o->placed_at->timestamp }}"><i class="fa-regular fa-clock"></i> <span>{{ __(\Modules\Tagtoa\App\Models\Menu\Order::ageLabel($age)) }}</span></span>
                    @endif
                    <div class="ord-meta" style="margin-top:4px">
                        {{ optional($o->placed_at)->format('H:i') }}
                        @if($o->customer_name) · <b>{{ $o->customer_name }}</b>@endif
                        @if($o->customer_phone) · {{ $o->customer_phone }}@endif
                        @if($o->table_label) · {{ $o->hotel_room_id ? '' : __('N° table (optionnel)').' ' }}{{ $o->table_label }}@endif
                        @if($o->delivery_address) · {{ __('Adresse') }} : {{ $o->delivery_address }}@endif
                        @if($o->delivery_reference) · {{ __('Repère') }} : {{ $o->delivery_reference }}@endif
                        @if($o->order_type === 'delivery' && $o->courier) · {{ __('Livreur') }} : {{ $o->courier->name }}@endif
                        @if($o->isPaid() && ($o->paid_by || $o->paid_at)) · {{ __('Encaissé') }}@if($o->paid_by) {{ __('par') }} {{ $o->paid_by }}@endif @if($o->paid_at) {{ $o->paid_at->format('d/m H:i') }}@endif @endif
                    </div>
                </div>
                <div style="text-align:right">
                    {{-- Le total est NOIR ; vert seulement une fois payé, orange si pas encore encaissé. --}}
                    <b class="ord-total {{ $o->status === 'cancelled' ? '' : ($o->isPaid() ? 'paid' : 'unpaid') }}">{{ Money::format($o->total, $o->currency) }}</b>
                    @if($o->tip > 0)<div class="ord-meta" style="font-size:12px">{{ __('dont pourboire') }} {{ Money::format($o->tip, $o->currency) }}</div>@endif
                </div>
            </div>

            <table style="margin-top:12px;width:100%">
                <tbody>
                @foreach($o->items as $it)
                    <tr>
                        <td style="border:0;padding:4px 0">
                            {{ $it->qty }}× {{ $it->name }}
                            @if(!empty($it->selected_options))
                                <div class="ord-meta" style="font-size:12px">{{ collect($it->selected_options)->pluck('label')->implode(', ') }}</div>
                            @endif
                        </td>
                        <td class="ord-meta" style="border:0;padding:4px 0;text-align:right">{{ Money::format($it->line_total, $o->currency) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if($o->note)<p class="ord-meta" style="font-size:13px;margin-top:8px"><i class="fa-solid fa-note-sticky"></i> {{ $o->note }}</p>@endif

            @if(! $o->isPaid() && $o->payment_claimed_at && $o->payment_claim)
                {{-- Le client dit avoir payé par un moyen manuel : à VÉRIFIER, pas
                     à croire. Rien n'est marqué payé tant que le caissier n'a pas
                     confirmé avec « Encaisser ». --}}
                <div style="margin-top:12px;background:#fff5e6;color:#7a5200;border-radius:12px;padding:10px 12px;font-size:13.5px">
                    <b><i class="fa-solid fa-hand-holding-dollar"></i> {{ __('Le client dit avoir payé') }}</b>
                    · {{ $o->payment_claim['method'] ?? '' }}
                    @if(! empty($o->payment_claim['reference'])) · {{ __('Réf.') }} {{ $o->payment_claim['reference'] }}@endif
                    @if(! empty($o->payment_claim['payer_name'])) · {{ $o->payment_claim['payer_name'] }}@endif
                    @if(! empty($o->payment_claim['payer_phone'])) · {{ $o->payment_claim['payer_phone'] }}@endif
                    · {{ $o->payment_claimed_at->format('d/m H:i') }}
                    <div style="margin-top:4px;font-size:12.5px">{{ __('Vérifiez sur votre téléphone, puis choisissez le moyen et « Encaisser ».') }}</div>
                </div>
            @endif

            <div class="ord-actions">
                @if($suite)
                    {{-- UNE action principale : l'étape suivante. (Avant : une liste de statuts + « Confirmer » qui s'appelait
                         « Confirmer » même sur une commande déjà confirmée.) --}}
                    <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.status',$o->id) }}" style="flex:1 1 220px;margin:0">
                        @csrf
                        <input type="hidden" name="status" value="{{ $suite['status'] }}">
                        <button class="btn btn-p ord-next"><i class="fa-solid {{ $suite['icon'] }}"></i> {{ __($suite['label']) }}</button>
                    </form>
                @endif
                @if(! $o->isPaid() && $o->status !== 'cancelled')
                    {{-- Le caissier confirme ET dit comment. Trois pastilles, AUCUNE présélectionnée : un clic distrait ne doit
                         pas valoir « espèces », et « Encaisser » reste éteint tant qu'aucune n'est choisie. Un client qui paie par
                         passerelle n'a pas besoin de ceci : sa commande passe « Payé » toute seule. --}}
                    <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.paid',$o->id) }}" class="ord-pay" data-pay-form style="margin:0">
                        @csrf
                        <fieldset style="border:0;padding:0;margin:0;display:flex;gap:6px;flex-wrap:wrap">
                            <legend class="sr-only" style="position:absolute;left:-9999px">{{ __('Moyen de paiement') }}</legend>
                            @foreach(['cash', 'moncash', 'natcash', 'other'] as $k)
                                <label class="chip-pay"><input type="radio" name="payment_method" value="{{ $k }}" required><span>{{ __(\Modules\Tagtoa\App\Models\Menu\Order::MANUAL_PAYMENT_METHODS[$k]) }}</span></label>
                            @endforeach
                        </fieldset>
                        <button type="submit" class="btn btn-o ord-cash" disabled><i class="fa-solid fa-hand-holding-dollar"></i> {{ __('Encaisser') }}</button>
                    </form>
                @endif
                {{-- ⋯ : ce qu'on fait rarement — annuler, corriger un statut. Loin du bouton de tous les jours. --}}
                <details class="ord-more">
                    <summary aria-label="{{ __('Plus d\'actions') }}" title="{{ __('Plus d\'actions') }}"><i class="fa-solid fa-ellipsis"></i></summary>
                    <div class="ord-more-panel">
                        <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.status',$o->id) }}" style="margin:0 0 8px">
                            @csrf
                            <label class="ord-meta" style="display:block;font-size:12px;margin-bottom:3px">{{ __('Corriger le statut') }}</label>
                            <div style="display:flex;gap:6px">
                                <select class="sel" name="status" aria-label="{{ __('Corriger le statut') }}">
                                    @foreach(\Modules\Tagtoa\App\Models\Menu\Order::STATUS_META as $k=>$m)
                                        <option value="{{ $k }}" @selected($o->status===$k)>{{ __($m['label']) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-o btn-sm" style="flex:0">{{ __('Appliquer') }}</button>
                            </div>
                        </form>
                        @if($o->status !== 'cancelled')
                            <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.status',$o->id) }}" style="margin:0" onsubmit="return confirm(@js(__('Annuler cette commande ?')))">
                                @csrf
                                <input type="hidden" name="status" value="cancelled">
                                <button class="btn btn-o btn-sm" style="color:#b42318;border-color:#f0b4ae;width:100%"><i class="fa-solid fa-ban"></i> {{ __('Annuler la commande') }}</button>
                            </form>
                        @endif
                    </div>
                </details>
            </div>
        </div>
    @endforeach
    <div style="margin-top:16px">{{ $orders->links() }}</div>

    @if($done->isNotEmpty())
        {{-- Commandes TRAITÉES (terminées / annulées) : UNE ligne chacune. Elles ne
             prennent plus la place des commandes à faire. « Retour » les remet en
             cours (terminée → confirmée, annulée → en attente). --}}
        <h3 style="margin:22px 0 8px;font-family:var(--fh);font-size:15px">{{ __('Commandes traitées') }} <span class="pill n">{{ $done->total() }}</span></h3>
        <div class="card" style="padding:0;overflow-x:auto">
            <table class="done-orders" style="width:100%;border-collapse:collapse;font-size:12.5px;white-space:nowrap">
                <thead>
                    <tr style="color:var(--muted);text-align:left">
                        <th style="padding:7px 6px 7px 10px;width:1%"></th>
                        <th style="padding:7px 8px">{{ __('Date') }}</th>
                        <th style="padding:7px 8px">{{ __('Client') }}</th>
                        <th style="padding:7px 8px">{{ __('Numéro') }}</th>
                        <th style="padding:7px 8px;text-align:right">{{ __('Total') }}</th>
                        <th style="padding:7px 10px 7px 8px">{{ __('Statut') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($done as $o)
                    @php $sm = $o->status_meta; @endphp
                    <tr title="{{ $o->reference }} · {{ $o->order_type_label }} — {{ $o->items->map(fn ($i) => $i->qty.'× '.$i->name)->implode(', ') }}" style="border-top:1px solid var(--bd,#eee)">
                        {{-- « Retour » en PREMIÈRE colonne : toujours visible, même sur un petit écran. --}}
                        <td style="padding:4px 6px 4px 10px">
                            <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.status',$o->id) }}" style="margin:0">
                                @csrf
                                <input type="hidden" name="status" value="{{ $o->status === 'cancelled' ? 'pending' : 'confirmed' }}">
                                <button class="btn btn-o btn-sm" style="flex:0;padding:5px 9px" title="{{ __('Retour') }} — {{ __('Remettre dans les commandes en cours') }}" aria-label="{{ __('Retour') }}"><i class="fa-solid fa-rotate-left"></i></button>
                            </form>
                        </td>
                        <td style="padding:5px 8px;color:var(--muted)">{{ optional($o->placed_at)->format('d/m H:i') }}</td>
                        <td style="padding:5px 8px;font-weight:600">{{ $o->customer_name ?: '—' }}</td>
                        <td style="padding:5px 8px">{{ $o->customer_phone ?: '—' }}</td>
                        <td style="padding:5px 8px;text-align:right;font-weight:700">{{ Money::format($o->total, $o->currency) }}</td>
                        <td style="padding:5px 10px 5px 8px">
                            <span class="pill {{ $sm['pill'] }}">{{ __($sm['label']) }}</span>
                            @if($o->isPaid())<span class="pill g">{{ __('Payé') }}</span>@elseif($o->status !== 'cancelled')<span class="pill r">{{ __('Non payé') }}</span>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px">{{ $done->links() }}</div>
    @endif
@endif

{{-- TAGTOA MENU — la liste des commandes du tableau de bord (page + fil de mise à jour). Variable : $orders --}}
@php use Modules\Tagtoa\App\Support\Money; @endphp
@if($orders->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-receipt"></i>{{ __('Aucune commande.') }}</div></div>
@else
    @foreach($orders as $o)
        @php $sm = $o->status_meta; @endphp
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
                <div>
                    <b style="font-family:var(--fh);font-size:15px">{{ $o->reference }}</b>
                    <span class="pill {{ $sm['pill'] }}">{{ __($sm['label']) }}</span>
                    @if($o->isPaid())
                        <span class="pill g"><i class="fa-solid fa-check"></i> {{ __('Payé') }}@if($o->payment_method_label) · {{ __($o->payment_method_label) }}@endif</span>
                    @else
                        <span class="pill r"><i class="fa-solid fa-hourglass-half"></i> {{ __('Non payé') }}</span>
                    @endif
                    <span class="pill n">{{ $o->order_type_label }}</span>
                    <div style="color:var(--muted);font-size:13px;margin-top:4px">
                        {{ optional($o->placed_at)->format('d/m/Y H:i') }}
                        @if($o->customer_name) · {{ $o->customer_name }}@endif
                        @if($o->customer_phone) · {{ $o->customer_phone }}@endif
                        @if($o->table_label) · {{ __('N° table (optionnel)') }} {{ $o->table_label }}@endif
                        @if($o->delivery_address) · {{ __('Adresse') }} : {{ $o->delivery_address }}@endif
                        @if($o->order_type === 'delivery' && $o->courier) · {{ __('Livreur') }} : {{ $o->courier->name }}@endif
                        @if($o->isPaid() && ($o->paid_by || $o->paid_at)) · {{ __('Encaissé') }}@if($o->paid_by) {{ __('par') }} {{ $o->paid_by }}@endif @if($o->paid_at) {{ $o->paid_at->format('d/m H:i') }}@endif @endif
                    </div>
                </div>
                <div style="text-align:right">
                    <b style="font-family:var(--fh);font-size:17px;color:var(--blue)">{{ Money::format($o->total, $o->currency) }}</b>
                    @if($o->tip > 0)<div style="color:var(--muted);font-size:12px">{{ __('dont pourboire') }} {{ Money::format($o->tip, $o->currency) }}</div>@endif
                </div>
            </div>

            <table style="margin-top:12px">
                <tbody>
                @foreach($o->items as $it)
                    <tr>
                        <td style="border:0;padding:4px 0">
                            {{ $it->qty }}× {{ $it->name }}
                            @if(!empty($it->selected_options))
                                <div style="color:var(--muted);font-size:12px">{{ collect($it->selected_options)->pluck('label')->implode(', ') }}</div>
                            @endif
                        </td>
                        <td style="border:0;padding:4px 0;text-align:right;color:var(--muted)">{{ Money::format($it->line_total, $o->currency) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if($o->note)<p style="color:var(--muted);font-size:13px;margin-top:8px"><i class="fa-solid fa-note-sticky"></i> {{ $o->note }}</p>@endif

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

            <div class="row" style="margin-top:14px;gap:8px;align-items:flex-end">
                <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.status',$o->id) }}" style="display:flex;gap:8px;align-items:center;flex:1">
                    @csrf
                    <select class="sel" name="status" style="max-width:200px">
                        @foreach(\Modules\Tagtoa\App\Models\Menu\Order::STATUS_META as $k=>$m)
                            <option value="{{ $k }}" @selected($o->status===$k)>{{ __($m['label']) }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-rotate"></i> {{ __('Confirmer') }}</button>
                </form>
                @if(! $o->isPaid() && $o->status !== 'cancelled')
                    {{-- Le caissier confirme ET dit comment. Aucun moyen
                         présélectionné : un clic distrait ne doit pas valoir
                         « espèces ». Un client qui paie par passerelle n'a pas
                         besoin de ceci : sa commande passe « Payé » toute seule. --}}
                    <form method="POST" action="{{ route('tagtoa.menu.dashboard.orders.paid',$o->id) }}" style="display:flex;gap:8px;align-items:center;flex:0 0 auto">
                        @csrf
                        <select class="sel" name="payment_method" required style="max-width:150px">
                            <option value="" selected disabled>{{ __('Moyen…') }}</option>
                            @foreach(\Modules\Tagtoa\App\Models\Menu\Order::MANUAL_PAYMENT_METHODS as $k => $lbl)
                                <option value="{{ $k }}">{{ __($lbl) }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-p btn-sm" style="flex:0"><i class="fa-solid fa-check"></i> {{ __('Encaisser') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
    <div style="margin-top:16px">{{ $orders->links() }}</div>
@endif

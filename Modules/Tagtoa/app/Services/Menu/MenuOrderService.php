<?php

namespace Modules\Tagtoa\App\Services\Menu;

use Modules\Tagtoa\App\Exceptions\OrderRuleException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Services\Inventory\StockLedger;
use Modules\Tagtoa\App\Services\Order\OrderSpine;
use Modules\Tagtoa\App\Services\Tax\TaxProfile;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\App\Services\Inventory\StockService;
use Modules\Tagtoa\App\Support\Inventory\MovementType;
use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\App\Support\Menu\ItemOptionPricing;
use Modules\Tagtoa\App\Support\Tax\Tax;

/**
 * TAGTOA MENU — capture & gestion des commandes.
 *
 * Sécurité financière : le prix de CHAQUE ligne (article + options choisies)
 * est imposé par le SERVEUR (depuis la base, articles/options de CE menu) —
 * jamais le prix envoyé par le client (anti-tampering). Idempotent via client_uuid.
 */
class MenuOrderService
{
    public function __construct(
        protected RevenueService $revenue,
        protected NotificationService $notifications,
        protected LoyaltyCardService $loyalty,
    ) {
    }

    public function placeOrder(Menu $menu, array $payload): Order
    {
        $uuid = $payload['client_uuid'] ?? null;
        // Cherché DANS ce menu seulement. La recherche était globale : le client_uuid d'une commande d'un
        // AUTRE commerce (référence, total, lien de suivi) revenait à quiconque le rejouait.
        if ($uuid && $existing = Order::where('menu_id', $menu->id)->where('client_uuid', $uuid)->first()) {
            return $existing;
        }

        // Horaires configurés et hors plage : refuser AVANT d'ouvrir la
        // transaction — un client ne doit pas pouvoir commander à 3h du matin
        // parce que la cuisine ne surveille plus l'écran.
        if (! $menu->isOpenNow()) {
            throw new OrderRuleException('closed');
        }

        // Comme le JS côté client : confort d'affichage là-bas, mais la
        // vraie règle vit ICI — un appel direct qui contournerait l'écran
        // ne doit pas pouvoir commander sans nom ni téléphone si le
        // commerce l'exige.
        if ($menu->require_customer_info ?? false) {
            $nom = trim((string) ($payload['customer_name'] ?? ''));
            $tel = trim((string) ($payload['customer_phone'] ?? ''));
            if ($nom === '' || $tel === '') {
                throw new OrderRuleException('missing_customer_info');
            }
        }

        try {
            $order = $this->insertOrder($menu, $payload, $uuid);
        } catch (QueryException $e) {
            // Double-tap réseau lent : deux requêtes ont pu passer la
            // vérification ci-dessus avant que l'une des deux ne pose la
            // contrainte unique sur client_uuid. Sans ce filet, la seconde
            // remontait une erreur 500 au client au lieu de lui rendre sa
            // commande déjà enregistrée par la première.
            if ($uuid && \Modules\Tagtoa\App\Support\Order\UniqueViolation::onClientUuid($e)) {
                $existing = Order::where('menu_id', $menu->id)->where('client_uuid', $uuid)->first();
                if ($existing) {
                    return $existing;
                }

                // Collision avec une commande d'un AUTRE menu (l'ancien index unique global n'est pas encore
                // remplacé, ou identifiant rejoué d'ailleurs) : on ne rend JAMAIS la commande d'autrui ; on
                // enregistre celle-ci sans clé d'idempotence plutôt que de la perdre ou de répondre 500.
                $order = $this->insertOrder($menu, $payload, null);
                $this->notifyMerchant($menu, $order);
                $this->notifyCustomer($menu, $order);

                return $order;
            }

            throw $e;
        }

        // Notifications hors transaction (tolérant, opt-in) : nouvelle commande.
        $this->notifyMerchant($menu, $order);
        $this->notifyCustomer($menu, $order);

        return $order;
    }

    protected function insertOrder(Menu $menu, array $payload, ?string $uuid): Order
    {
        return DB::transaction(function () use ($menu, $payload, $uuid) {
            // Catalogue autorisé : articles disponibles de ce menu, indexés par id.
            $catalog = $menu->items()->where('is_available', true)->with('options.choices')->get()->keyBy('id');

            // Régime de taxe du commerce, lu UNE fois : le relire à chaque
            // ligne ferait autant de requêtes pour une réponse identique.
            // C'est la même colonne, le même moteur, que la caisse POS —
            // un commerce qui l'active la voit désormais partout, pas
            // seulement au comptoir.
            $taxe = TaxProfile::current($menu->tenant_id);

            $lines = [];
            $subtotal = 0.0;
            foreach (($payload['items'] ?? []) as $it) {
                $item = $catalog->get((int) ($it['id'] ?? 0));
                if (! $item) {
                    continue; // ignore tout article inconnu / indisponible
                }
                $qty = max(1, (int) ($it['qty'] ?? 1));

                // Stock : refuse la commande si un article suivi n'a pas assez de stock.
                if (! StockService::canFulfill($item->stock, $qty)) {
                    throw new OrderRuleException('out_of_stock');
                }

                [$optionsExtra, $optionsSnapshot] = $this->resolveOptions($item, $it['options'] ?? []);

                $price = round((float) $item->price + $optionsExtra, 2);
                $ligneTotal = round($price * $qty, 2);
                $subtotal += $ligneTotal;
                // Taux du JOUR de la commande, figé sur la ligne — comme le
                // prix : un taux relevé demain ne doit pas recalculer une
                // commande déjà passée aujourd'hui.
                $tauxLigne = $taxe->rateFor($item);
                $lines[] = [
                    'item' => $item, 'price' => $price, 'qty' => $qty, 'options' => $optionsSnapshot,
                    'line_total' => $ligneTotal, 'tax_rate' => $tauxLigne,
                ];
            }

            if (! $lines) {
                throw new OrderRuleException('empty_order');
            }

            $subtotal = round($subtotal, 2);
            $tip = max(0, round((float) ($payload['tip'] ?? 0), 2));

            $recap = Tax::summarize(
                array_map(fn ($l) => ['amount' => $l['line_total'], 'rate' => $l['tax_rate']], $lines),
                $taxe->inclusive
            );

            // Table vérifiée par QR/NFC : un code valide IMPOSE le nom de la
            // table ET FORCE la commande en « sur place » — peu importe le
            // mode demandé ou les modes offerts par le menu : on ne peut pas
            // « livrer » une table où le client est déjà assis, et si « sur
            // place » n'est pas dans les modes offerts, la table doit rester
            // commandable quand même (un client physiquement assis ne doit
            // jamais se retrouver sans aucune option). Un code présent mais
            // invalide/désactivé est refusé plutôt qu'ignoré : un QR périmé
            // ne doit jamais faire atterrir silencieusement une commande
            // sans table. Si la fonctionnalité est désactivée côté commerce,
            // le code est ignoré comme s'il n'avait jamais été envoyé — pas
            // une erreur, juste un lien de table devenu inactif. En
            // l'absence de code, le texte libre du client reste possible
            // (menu sans tables configurées).
            $table = null;
            $tableLabel = $payload['table_label'] ?? null;
            if (! empty($payload['table_code']) && ($menu->table_ordering_enabled ?? true)) {
                $table = \Modules\Tagtoa\App\Models\Menu\Table::where('menu_id', $menu->id)
                    ->where('code', $payload['table_code'])->where('is_active', true)->first();
                if (! $table) {
                    throw new OrderRuleException('invalid_table');
                }
                $tableLabel = $table->label;
            }

            // Le mode demandé doit être un mode RÉELLEMENT offert par CE menu
            // — jamais seulement un mode valide dans l'absolu. Un menu
            // livraison-seule ne doit pas pouvoir recevoir une commande
            // « sur place » via un appel direct qui contournerait l'écran.
            $modesOfferts = Order::serviceTypesFor($menu->service_types);
            $requestedType = $payload['order_type'] ?? $modesOfferts[0];
            $orderType = $table
                ? 'dine_in'
                : (in_array($requestedType, $modesOfferts, true) ? $requestedType : $modesOfferts[0]);

            // LIVRAISON : sans téléphone ni adresse, personne ne peut joindre le
            // client ni trouver la porte. Règle du serveur (le formulaire n'est
            // qu'un confort) : un appel direct, ou une commande rejouée depuis
            // la file hors ligne, ne la contourne pas. Le repère reste facultatif.
            if ($orderType === 'delivery') {
                $chiffres = preg_replace('/\D+/', '', (string) ($payload['customer_phone'] ?? '')) ?? '';
                $adresse  = trim((string) ($payload['delivery_address'] ?? ''));
                if (strlen($chiffres) < 8 || mb_strlen($adresse) < 3) {
                    throw new OrderRuleException('missing_delivery_info');
                }
            }
            $requestedChannel = $payload['channel'] ?? 'menu';
            $channel = in_array($requestedChannel, ['menu', 'whatsapp'], true) ? $requestedChannel : 'menu';

            // Comme le pourboire : jamais taxé, s'ajoute tel quel au total.
            // Seul le mode Livraison le déclenche — sur place ou à emporter,
            // il n'y a rien à livrer.
            //
            // Zone choisie par le client : son frais remplace le frais unique
            // du menu. Une zone introuvable (menu changé entretemps, id d'un
            // autre commerce) retombe sur le frais unique plutôt que de
            // refuser la commande — un identifiant périmé ne doit jamais
            // bloquer une livraison.
            $zone = null;
            if ($orderType === 'delivery' && ! empty($payload['delivery_zone_id'])) {
                $zone = $menu->deliveryZones()->where('is_active', true)
                    ->whereKey($payload['delivery_zone_id'])->first();
            }
            $deliveryFee = $orderType === 'delivery'
                ? ($zone ? max(0, round((float) $zone->fee, 2)) : max(0, round((float) ($menu->delivery_fee ?: 0), 2)))
                : 0.0;

            // Prix TTC (usage haïtien) : le sous-total contient déjà la
            // taxe, seul le pourboire s'ajoute. Prix HT : la taxe s'ajoute
            // au total, et le client paie davantage que le sous-total affiché.
            $total = $taxe->inclusive
                ? round($subtotal + $tip + $deliveryFee, 2)
                : round($recap['total'] + $tip + $deliveryFee, 2);

            $order = $menu->orders()->create([
                'tenant_id'        => $menu->tenant_id,
                'reference'        => Order::generateReference(),
                'subtotal'         => $subtotal,
                'total'            => $total,
                'tip'              => $tip,
                'delivery_fee'     => $deliveryFee,
                'currency'         => $menu->currency ?: 'HTG',
                'status'           => 'pending',
                'payment_status'   => 'unpaid',
                'channel'          => $channel,
                'order_type'       => $orderType,
                'customer_name'    => $payload['customer_name'] ?? null,
                'customer_phone'   => $payload['customer_phone'] ?? null,
                'table_label'      => $orderType === 'dine_in' ? $tableLabel : null,
                'delivery_address' => $orderType === 'delivery' ? trim((string) ($payload['delivery_address'] ?? '')) : null,
                'delivery_reference' => $orderType === 'delivery' ? (trim((string) ($payload['delivery_reference'] ?? '')) ?: null) : null,
                'delivery_zone_label' => $orderType === 'delivery' ? optional($zone)->name : null,
                'note'             => $payload['note'] ?? null,
                'client_uuid'      => $uuid,
                'placed_at'        => now(),
                // Copiés sur la commande, comme sur la vente POS : changer le
                // réglage du commerce ne doit jamais retourner le sens d'une
                // commande déjà passée.
                'tax_total'        => $recap['tax'],
                'tax_base'         => $recap['base'],
                'tax_inclusive'    => $taxe->inclusive,
                'tax_label'        => $taxe->enabled ? $taxe->label() : null,
                'tax_breakdown'    => $recap['tax'] > 0 ? array_values($recap['byRate']) : null,
            ]);

            foreach ($lines as $l) {
                // Part de taxe de CETTE ligne — pour que le détail se
                // ré-additionne exactement sur le total de la commande.
                $partLigne = Tax::split($l['line_total'], $l['tax_rate'], $taxe->inclusive);

                $order->items()->create([
                    'item_id'          => $l['item']->id,
                    // Le nom FIGÉ sur la ligne est celui que le CLIENT a vu au
                    // moment de commander — dans sa langue, pas forcément
                    // celle du marchand. Un client qui a lu « Fried pork » ne
                    // doit pas recevoir une confirmation WhatsApp en kreyòl
                    // pour un plat qu'il a choisi en anglais.
                    'name'             => $l['item']->translated('name'),
                    'price'            => $l['price'],
                    'qty'              => $l['qty'],
                    'line_total'       => $l['line_total'],
                    'selected_options' => $l['options'] ?: null,
                    'tax_rate'         => $taxe->enabled ? $l['tax_rate'] : null,
                    'tax_amount'       => $taxe->enabled ? $partLigne['tax'] : null,
                ]);

                // Le stock passe par le journal, jamais par une écriture
                // directe : sinon le commerce verrait le chiffre baisser sans
                // pouvoir dire quelle commande l'a fait baisser.
                app(StockLedger::class)->remove(
                    $l['item'], (float) $l['qty'], MovementType::SALE,
                    ['origin_type' => 'menu_order', 'origin_id' => $order->id]
                );
            }

            // Colonne vertébrale, dans la MÊME transaction : une commande
            // absente du chiffre d'affaires parce que le processus s'est
            // arrêté entre les deux écritures serait invisible et introuvable.
            //
            // Ici la commande naît en attente : le QR passe la commande, le
            // paiement vient après — parfois jamais.
            app(OrderSpine::class)->record([
                'tenant_id'      => $menu->tenant_id,
                'channel'        => Channel::MENU,
                'source_type'    => 'menu_order',
                'source_id'      => $order->id,
                'reference'      => $order->reference,
                'subtotal'       => (float) $order->subtotal,
                'tax_base'       => $recap['base'],
                'tax_total'      => $recap['tax'],
                'total'          => (float) $order->total,
                'currency'       => $order->currency,
                'status'         => OrderStatus::PENDING,
                'payment_status' => OrderStatus::UNPAID,
                'customer_id'    => app(OrderSpine::class)
                    ->customerFor($menu->tenant_id, $order->customer_name, $order->customer_phone)?->id,
                'customer_name'  => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'placed_at'      => $order->placed_at,
            ]);

            return $order;
        });
    }


    /**
     * Valide + calcule le supplément de prix des options choisies pour un item,
     * à partir du catalogue serveur (jamais du prix envoyé par le client).
     * Adapte les relations Eloquent en tableaux simples et délègue le calcul
     * (pur, testable sans Laravel) à ItemOptionPricing.
     */
    protected function resolveOptions($item, array $selected): array
    {
        $groups = $item->options->map(fn ($g) => [
            'id'       => $g->id,
            'name'     => $g->name,
            'required' => $g->required,
            'multiple' => $g->multiple,
            'choices'  => $g->choices->map(fn ($c) => [
                'id' => $c->id, 'label' => $c->label, 'price_delta' => (float) $c->price_delta,
            ])->all(),
        ])->all();

        return ItemOptionPricing::resolve($groups, $selected);
    }

    /** WhatsApp au marchand à chaque nouvelle commande (no-op sans credentials). */
    protected function notifyMerchant(Menu $menu, Order $order): void
    {
        try {
            if (! $menu->whatsapp) {
                return;
            }
            $this->notifications->push([
                'channels' => ['whatsapp'],
                'phone'    => $menu->whatsapp,
                'subject'  => $menu->name,
                'body'     => __('Nouvelle commande').' '.$order->reference
                    .' — '.number_format((float) $order->total, 2).' '.$order->currency
                    .' · '.$order->order_type_label
                    .($order->table_label ? ' · '.__('Table').' '.$order->table_label : '')
                    .($order->customer_name ? ' · '.$order->customer_name : ''),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /** Confirmation WhatsApp automatique AU CLIENT (tolérant, opt-in, no-op sans numéro). */
    protected function notifyCustomer(Menu $menu, Order $order): void
    {
        try {
            if (! $order->customer_phone) {
                return;
            }
            $lines = [
                __('Bonjour').' '.($order->customer_name ?: '').',',
                '',
                __('Votre commande chez :n est bien reçue !', ['n' => $menu->name]),
                __('Référence').' : '.$order->reference,
                __('Total').' : '.number_format((float) $order->total, 2).' '.$order->currency,
                __('Statut').' : '.__($order->status_meta['label']),
                '',
                __('Suivre ma commande').' : '.route('tagtoa.menu.track', $order->reference),
                '',
                __('Propulsé par').' TAGTOA',
            ];
            $this->notifications->push([
                'channels' => ['whatsapp'],
                'phone'    => $order->customer_phone,
                'subject'  => $menu->name,
                'body'     => implode("\n", array_filter($lines, fn ($l) => $l !== null)),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /**
     * Marque payée + commission plateforme (hors pourboire) + points fidélité.
     *
     * IDEMPOTENT ET SANS COURSE : le test « déjà payée ? » se fait sous verrou,
     * sur la ligne réelle. Deux clics rapprochés sur « Encaisser » (ou un clic
     * et le retour de la passerelle au même instant) lisaient tous deux
     * « pas encore payée » sur leur copie périmée, et chacun enregistrait la
     * commission et les points : revenu et fidélité comptés deux fois.
     *
     * `$method` : comment ça a été payé (« cash », « moncash »…), `$by` : qui a
     * encaissé — null pour une passerelle, qui n'est personne.
     */
    public function markPaid(Order $order, ?string $method = null, ?string $by = null): Order
    {
        $fresh = DB::transaction(function () use ($order, $method, $by) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->first();
            if (! $locked || $locked->isPaid()) {
                return null; // déjà payée : rien à refaire
            }
            $locked->update([
                'payment_status' => 'paid',
                'payment_method' => $method,
                'paid_at'        => now(),
                'paid_by'        => $by !== null ? mb_substr($by, 0, 120) : null,
            ]);

            return $locked;
        });

        if ($fresh) {
            // La colonne vertébrale suit le module, qui reste maître de l'état
            // réel de la commande.
            app(OrderSpine::class)->touch('menu_order', $fresh->id, null, OrderStatus::PAID);
            $this->revenue->record('menu_order', $fresh->id, 'menu', (float) $fresh->subtotal, $fresh->tenant_id, $fresh->currency);
            $this->awardLoyaltyPoints($fresh);
            $order->refresh();
        }

        return $order;
    }

    /**
     * Crédite des points fidélité si le téléphone client correspond à une carte
     * TAGTOA existante du même établissement — n'enrôle JAMAIS automatiquement
     * une nouvelle carte (portée volontairement conservatrice).
     */
    protected function awardLoyaltyPoints(Order $order): void
    {
        try {
            if (! $order->customer_phone) {
                return;
            }
            $menu = $order->menu;
            if (! $menu || ! $menu->vcard_id) {
                return;
            }
            $digits = preg_replace('/\D+/', '', (string) $order->customer_phone) ?? '';
            if (strlen($digits) < 4) {
                return;
            }
            $suffix = substr($digits, -8);

            $card = Card::whereHas('program', fn ($q) => $q->where('vcard_id', $menu->vcard_id)->where('is_active', true))
                ->with('program')
                ->get()
                ->first(fn ($c) => $c->cardholder_phone
                    && str_ends_with(preg_replace('/\D+/', '', $c->cardholder_phone) ?? '', $suffix));

            if (! $card || ! $card->isActive()) {
                return;
            }

            $points = $card->program->pointsForAmount((float) $order->subtotal);
            $this->loyalty->earnPoints($card, $points, [
                'reference' => $order->reference,
                'note'      => __('Commande MENU').' '.$order->reference,
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
}

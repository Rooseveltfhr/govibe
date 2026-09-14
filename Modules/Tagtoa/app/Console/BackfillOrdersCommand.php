<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Modules\Tagtoa\App\Models\Event\Order as EventOrder;
use Modules\Tagtoa\App\Models\Menu\Order as MenuOrder;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Store\Order as StoreOrder;
use Modules\Tagtoa\App\Services\Order\OrderSpine;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;

/**
 * TAGTOA — inscrire l'HISTORIQUE sur la colonne vertébrale.
 *
 * La colonne vertébrale s'alimente à chaque nouvelle commande. Mais le jour du
 * déploiement, les ventes déjà encaissées — parfois des mois — n'y sont pas.
 * Le marchand ouvrirait alors un rapport « tous canaux » affichant zéro, et
 * conclurait que TAGTOA a perdu ses chiffres.
 *
 * Cette commande rejoue le passé. Elle est REJOUABLE sans risque : le service
 * met à jour une commande déjà inscrite au lieu de la dupliquer, donc la
 * relancer après une interruption reprend simplement le travail.
 *
 * À lancer UNE FOIS après la mise en production, puis à oublier.
 */
class BackfillOrdersCommand extends Command
{
    protected $signature = 'tagtoa:orders:backfill
                            {--chunk=500 : Nombre de lignes traitées à la fois}
                            {--dry-run : Compter sans rien écrire}';

    protected $description = "Inscrit les commandes déjà encaissées sur la colonne vertébrale";

    public function handle(OrderSpine $spine): int
    {
        $taille = max(50, (int) $this->option('chunk'));
        $simulation = (bool) $this->option('dry-run');

        if ($simulation) {
            $this->warn('Simulation : rien ne sera écrit.');
        }

        $total = 0;
        $total += $this->caisse($spine, $taille, $simulation);
        $total += $this->menu($spine, $taille, $simulation);
        $total += $this->boutique($spine, $taille, $simulation);
        $total += $this->billetterie($spine, $taille, $simulation);

        $this->newLine();
        $this->info($simulation
            ? "$total commandes seraient inscrites."
            : "$total commandes inscrites. Total en base : ".Order::withoutGlobalScopes()->count());

        return self::SUCCESS;
    }

    /** Les ventes de caisse : encaissées sur-le-champ, donc terminées et payées. */
    private function caisse(OrderSpine $spine, int $taille, bool $simulation): int
    {
        $n = 0;

        Sale::with('terminal')->chunkById($taille, function ($ventes) use ($spine, $simulation, &$n) {
            foreach ($ventes as $v) {
                if ($this->dejaInscrite('pos_sale', $v->id)) {
                    continue;
                }
                $n++;
                if ($simulation) {
                    continue;
                }

                $spine->record([
                    'tenant_id'      => $v->terminal?->tenant_id,
                    'channel'        => Channel::POS,
                    'source_type'    => 'pos_sale',
                    'source_id'      => $v->id,
                    'reference'      => $v->reference,
                    'subtotal'       => (float) $v->subtotal,
                    'discount'       => (float) $v->discount,
                    'tax_base'       => (float) ($v->tax_base ?? 0),
                    'tax_total'      => (float) ($v->tax_total ?? 0),
                    'total'          => (float) $v->total,
                    'currency'       => $v->currency,
                    // Une vente annulée reste annulée ; les autres sont payées.
                    'status'         => (int) $v->status === 1 ? OrderStatus::COMPLETED : OrderStatus::CANCELLED,
                    'payment_status' => (int) $v->status === 1 ? OrderStatus::PAID : OrderStatus::UNPAID,
                    'customer_phone' => $v->customer_phone,
                    'staff_id'       => $v->staff_id,
                    'placed_at'      => $v->sold_at ?? $v->created_at,
                ]);
            }
            $this->output->write('.');
        });

        $this->line("  caisse : $n");

        return $n;
    }

    private function menu(OrderSpine $spine, int $taille, bool $simulation): int
    {
        $n = 0;

        MenuOrder::chunkById($taille, function ($commandes) use ($spine, $simulation, &$n) {
            foreach ($commandes as $c) {
                if ($this->dejaInscrite('menu_order', $c->id)) {
                    continue;
                }
                $n++;
                if ($simulation) {
                    continue;
                }

                $spine->record([
                    'tenant_id'      => $c->tenant_id,
                    'channel'        => Channel::MENU,
                    'source_type'    => 'menu_order',
                    'source_id'      => $c->id,
                    'reference'      => $c->reference,
                    'subtotal'       => (float) $c->subtotal,
                    'total'          => (float) $c->total,
                    'currency'       => $c->currency,
                    // Statut inconnu du vocabulaire commun : on retombe sur
                    // « en attente » plutôt que d'inventer un encaissement.
                    'status'         => OrderStatus::isValid($c->status) ? $c->status : OrderStatus::PENDING,
                    'payment_status' => $c->payment_status === 'paid' ? OrderStatus::PAID : OrderStatus::UNPAID,
                    'customer_name'  => $c->customer_name,
                    'customer_phone' => $c->customer_phone,
                    'placed_at'      => $c->placed_at ?? $c->created_at,
                ]);
            }
            $this->output->write('.');
        });

        $this->line("  menu : $n");

        return $n;
    }

    private function boutique(OrderSpine $spine, int $taille, bool $simulation): int
    {
        $n = 0;

        StoreOrder::with('store')->chunkById($taille, function ($commandes) use ($spine, $simulation, &$n) {
            foreach ($commandes as $c) {
                if ($this->dejaInscrite('store_order', $c->id)) {
                    continue;
                }
                $n++;
                if ($simulation) {
                    continue;
                }

                $spine->record([
                    'tenant_id'      => $c->tenant_id ?? $c->store?->tenant_id,
                    'channel'        => Channel::STORE,
                    'source_type'    => 'store_order',
                    'source_id'      => $c->id,
                    'reference'      => $c->reference,
                    'subtotal'       => (float) $c->subtotal,
                    'total'          => (float) $c->total,
                    'currency'       => $c->currency,
                    'status'         => OrderStatus::isValid($c->status) ? $c->status : OrderStatus::PENDING,
                    'payment_status' => $c->payment_status === 'paid' ? OrderStatus::PAID : OrderStatus::UNPAID,
                    'customer_name'  => $c->customer_name,
                    'customer_phone' => $c->customer_phone,
                    'placed_at'      => $c->placed_at ?? $c->created_at,
                ]);
            }
            $this->output->write('.');
        });

        $this->line("  boutique : $n");

        return $n;
    }

    /** La billetterie compte en entiers : on traduit (voir OrderStatus::fromEvent). */
    private function billetterie(OrderSpine $spine, int $taille, bool $simulation): int
    {
        $n = 0;

        EventOrder::with('event')->chunkById($taille, function ($commandes) use ($spine, $simulation, &$n) {
            foreach ($commandes as $c) {
                if ($this->dejaInscrite('event_order', $c->id)) {
                    continue;
                }
                $n++;
                if ($simulation) {
                    continue;
                }

                $paye = (int) $c->status === EventOrder::STATUS_PAID;

                $spine->record([
                    'tenant_id'      => $c->event?->tenant_id,
                    'channel'        => Channel::EVENT,
                    'source_type'    => 'event_order',
                    'source_id'      => $c->id,
                    'reference'      => $c->reference,
                    'subtotal'       => (float) $c->total,
                    'total'          => (float) $c->total,
                    'currency'       => $c->currency,
                    'status'         => OrderStatus::fromEvent((int) $c->status),
                    'payment_status' => $paye ? OrderStatus::PAID : OrderStatus::UNPAID,
                    'customer_name'  => $c->buyer_name,
                    'customer_phone' => $c->buyer_phone,
                    'placed_at'      => $c->paid_at ?? $c->created_at,
                ]);
            }
            $this->output->write('.');
        });

        $this->line("  billetterie : $n");

        return $n;
    }

    /**
     * Déjà inscrite ?
     *
     * Sans portée de commerce : la commande rejoue l'historique de TOUS les
     * commerces depuis la console, où aucun marchand n'est connecté.
     */
    private function dejaInscrite(string $type, int $id): bool
    {
        return Order::withoutGlobalScopes()
            ->where('source_type', $type)->where('source_id', $id)->exists();
    }
}

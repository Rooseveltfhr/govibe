<?php

namespace Modules\Tagtoa\App\Services\Order;

use Modules\Tagtoa\App\Models\Order\Customer;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — inscrire une commande sur la colonne vertébrale.
 *
 * Appelé par les quatre modules, DANS leur transaction. Écrire après coup
 * laisserait une commande encaissée absente du chiffre d'affaires si le
 * processus s'arrêtait entre les deux — et un marchand qui constate un écart
 * entre sa caisse et son rapport cesse de faire confiance aux deux.
 *
 * Le service ne décide de rien sur le fond : il traduit le vocabulaire du
 * module vers le vocabulaire commun, et refuse ce qu'il ne comprend pas plutôt
 * que de deviner.
 *
 * TOLÉRANT PAR CONSTRUCTION sur un point : une commande déjà inscrite est
 * mise à jour, pas dupliquée. Une caisse hors ligne rejoue ses ventes au
 * retour du réseau ; la recette ne doit pas doubler pour autant.
 */
class OrderSpine
{
    /**
     * Inscrit ou met à jour une commande.
     *
     * @param  array{
     *   tenant_id:?string, channel:string, source_type:string, source_id:int,
     *   reference:string, currency:string,
     *   subtotal?:float, discount?:float, tax_base?:float, tax_total?:float, total:float,
     *   status?:string, payment_status?:string,
     *   customer_id?:?int, customer_name?:?string, customer_phone?:?string,
     *   staff_id?:?int, placed_at?:mixed
     * }  $donnees
     */
    public function record(array $donnees): ?Order
    {
        $canal = $donnees['channel'] ?? null;
        $type  = $donnees['source_type'] ?? null;
        $id    = (int) ($donnees['source_id'] ?? 0);

        // Un canal inconnu ou une origine absente donnerait une ligne qu'aucun
        // écran ne saurait rouvrir. On préfère ne rien écrire.
        if (! Channel::isValid($canal) || ! $type || $id <= 0) {
            return null;
        }

        $statut  = OrderStatus::isValid($donnees['status'] ?? null)
            ? $donnees['status']
            : OrderStatus::PENDING;

        $paiement = OrderStatus::isValidPayment($donnees['payment_status'] ?? null)
            ? $donnees['payment_status']
            : OrderStatus::UNPAID;

        // Le client reste facultatif. Un nom laissé vide n'est pas une donnée
        // manquante à combler : c'est un achat de passage.
        $nom = $this->texte($donnees['customer_name'] ?? null, 160);
        $tel = $this->texte($donnees['customer_phone'] ?? null, 40);

        return Order::updateOrCreate(
            ['source_type' => $type, 'source_id' => $id],
            [
                // Le commerce, sinon celui de la session : une commande sans
                // commerce n'apparaîtrait dans aucun rapport, ce qui est une
                // perte silencieuse.
                'tenant_id'      => $donnees['tenant_id'] ?? Tenant::id(),
                'channel'        => $canal,
                'reference'      => (string) ($donnees['reference'] ?? ''),
                'customer_id'    => $donnees['customer_id'] ?? null,
                'customer_name'  => $nom,
                'customer_phone' => $tel,
                'subtotal'       => round((float) ($donnees['subtotal'] ?? $donnees['total'] ?? 0), 2),
                'discount'       => round((float) ($donnees['discount'] ?? 0), 2),
                'tax_base'       => round((float) ($donnees['tax_base'] ?? 0), 2),
                'tax_total'      => round((float) ($donnees['tax_total'] ?? 0), 2),
                'total'          => round((float) ($donnees['total'] ?? 0), 2),
                'currency'       => $donnees['currency'] ?? 'HTG',
                'status'         => $statut,
                'payment_status' => $paiement,
                'staff_id'       => $donnees['staff_id'] ?? null,
                'placed_at'      => $donnees['placed_at'] ?? now(),
            ]
        );
    }

    /**
     * Change l'état d'une commande depuis son module d'origine.
     *
     * Le module reste maître : quand le restaurant marque une commande
     * « livrée », c'est lui qui le sait. La colonne vertébrale suit.
     */
    public function touch(string $sourceType, int $sourceId, ?string $status = null, ?string $payment = null): ?Order
    {
        $order = Order::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if (! $order) {
            return null;
        }

        $changements = [];
        if (OrderStatus::isValid($status)) {
            $changements['status'] = $status;
        }
        if (OrderStatus::isValidPayment($payment)) {
            $changements['payment_status'] = $payment;
        }

        if ($changements) {
            $order->update($changements);
        }

        return $order;
    }

    /**
     * Retrouve ou crée la fiche d'un client, à partir de ce qu'il a bien voulu
     * donner. Renvoie null quand il n'a rien donné — et c'est très bien.
     *
     * Le téléphone identifie un client dans la pratique haïtienne : c'est par
     * lui qu'on le retrouve, pas par un numéro de compte. Un nom seul ne suffit
     * pas à rapprocher deux commandes : trois clients peuvent s'appeler Jean.
     */
    public function customerFor(?string $tenantId, ?string $nom, ?string $telephone): ?Customer
    {
        $nom = $this->texte($nom, 160);
        $tel = $this->normaliserTelephone($telephone);

        if ($tel === null) {
            // Sans téléphone, on n'ouvre pas de fiche : elle ne servirait à
            // personne et polluerait le carnet d'adresses d'homonymes.
            return null;
        }

        // Rapprochement sur les huit derniers chiffres : « 3712-4455 » et
        // « +509 3712 4455 » sont le même client.
        $cle = $this->cleTelephone($tel);

        $client = Customer::where('tenant_id', $tenantId)->where('phone_key', $cle)->first();

        if ($client) {
            $maj = [];

            // Le client donne son nom la deuxième fois : on complète, on
            // n'écrase jamais un nom déjà connu par un vide.
            if ($nom !== null && $client->name === null) {
                $maj['name'] = $nom;
            }
            // On garde la forme la plus complète du numéro : celle qui porte
            // l'indicatif est la seule utilisable depuis l'étranger.
            if (strlen($tel) > strlen((string) $client->phone)) {
                $maj['phone'] = $tel;
            }

            if ($maj) {
                $client->update($maj);
            }

            return $client;
        }

        return Customer::create([
            'tenant_id' => $tenantId,
            'name'      => $nom,
            'phone'     => $tel,
            'phone_key' => $cle,
            'is_active' => true,
        ]);
    }

    /** Chaîne propre, ou null. Une chaîne vide n'est pas une information. */
    private function texte(?string $valeur, int $max): ?string
    {
        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : mb_substr($valeur, 0, $max);
    }

    /**
     * Téléphone comparable.
     *
     * « 3712-4455 », « +509 3712 4455 » et « 37124455 » sont le même client.
     * Sans normalisation, il aurait trois fiches et aucun historique.
     */
    private function normaliserTelephone(?string $telephone): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $telephone) ?? '';

        // Trop court pour être un numéro : une frappe accidentelle ne doit pas
        // créer une fiche.
        return strlen($chiffres) >= 7 ? mb_substr($chiffres, 0, 40) : null;
    }

    /**
     * Clé de rapprochement : les huit derniers chiffres.
     *
     * C'est ce qui réunit « 3712-4455 », « +509 3712 4455 » et « 37124455 »
     * sous un seul client. Le compromis est assumé : deux numéros de pays
     * différents partageant leurs huit derniers chiffres seraient confondus.
     * Dans le carnet d'UN commerce de quartier c'est théorique, alors que les
     * trois fiches pour un même habitué sont une certitude.
     */
    private function cleTelephone(string $chiffres): string
    {
        return strlen($chiffres) > 8 ? substr($chiffres, -8) : $chiffres;
    }
}

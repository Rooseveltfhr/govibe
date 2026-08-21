<?php

namespace Modules\Tagtoa\App\Services\Api;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Models\Api\ApiPayment;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Support\Pay\GatewayCatalog;

/**
 * TAGTOA API — création et suivi des paiements demandés par un site tiers.
 *
 * Toute la logique vit ici (les contrôleurs restent minces) pour être testable
 * sans passer par HTTP. Deux règles non négociables :
 *   1. le montant est FIGÉ côté serveur à la création — la page hébergée ne le
 *      relit jamais depuis l'URL ;
 *   2. tout est scopé au tenant porté par la clé API, jamais à une session.
 */
class ApiPaymentService
{
    /** Erreur : le marchand n'a aucune page de paiement active. */
    public const ERR_NO_PAGE = 'no_payment_page';

    /** Erreur : aucune méthode de paiement activée sur la page. */
    public const ERR_NO_METHOD = 'no_payment_method';

    /**
     * Crée un paiement. Idempotent sur `external_id` : rappeler l'API avec la
     * même référence de commande renvoie le paiement déjà créé au lieu d'en
     * fabriquer un second (un site tiers qui réessaie ne double pas la note).
     */
    public function create(ApiKey $key, array $data): ApiPayment
    {
        $external = $data['external_id'] ?? null;
        if ($external) {
            $existing = ApiPayment::where('tenant_id', $key->tenant_id)
                ->where('external_id', $external)->first();
            if ($existing) {
                return $existing;
            }
        }

        $page = $this->resolvePage($key->tenant_id, $data['page_alias'] ?? null);

        return DB::transaction(fn () => ApiPayment::create([
            'tenant_id'       => $key->tenant_id,
            'api_key_id'      => $key->id,
            'payment_page_id' => $page->id,
            'reference'       => ApiPayment::generateReference(),
            'external_id'     => $external,
            'amount'          => round((float) $data['amount'], 2),
            'currency'        => strtoupper($data['currency'] ?? $page->default_currency ?: 'HTG'),
            'description'     => $data['description'] ?? null,
            'status'          => ApiPayment::STATUS_PENDING,
            'customer_name'   => $data['customer_name'] ?? null,
            'customer_phone'  => $data['customer_phone'] ?? null,
            'customer_email'  => $data['customer_email'] ?? null,
            'return_url'      => $data['return_url'] ?? null,
            'callback_url'    => $data['callback_url'] ?? $key->webhook_url,
            'meta'            => $data['meta'] ?? null,
        ]));
    }

    /**
     * Page de paiement à utiliser : celle demandée si elle appartient au tenant,
     * sinon la première page active. Lève une erreur explicite si le marchand
     * n'a rien configuré — un intégrateur doit comprendre pourquoi ça échoue.
     */
    public function resolvePage(?string $tenantId, ?string $alias = null): PaymentPage
    {
        $q = PaymentPage::where('tenant_id', $tenantId)->where('is_active', true);
        $page = $alias ? (clone $q)->where('alias', $alias)->first() : null;
        $page = $page ?: $q->oldest('id')->first();

        if (! $page) {
            throw new \RuntimeException(self::ERR_NO_PAGE);
        }
        if ($page->activeMethods()->count() === 0) {
            throw new \RuntimeException(self::ERR_NO_METHOD);
        }

        return $page;
    }

    /**
     * Marque un paiement payé (idempotent) et notifie le site tiers.
     * Appelé quand le marchand approuve la preuve, ou par une passerelle.
     */
    public function markPaid(ApiPayment $payment): ApiPayment
    {
        if ($payment->isPaid()) {
            return $payment;
        }

        $payment->update(['status' => ApiPayment::STATUS_PAID, 'paid_at' => now()]);
        $this->notifyCallback($payment);

        return $payment;
    }

    /**
     * Notification serveur → serveur, signée en HMAC-SHA256 pour que le site
     * tiers puisse vérifier que l'appel vient bien de TAGTOA. Tolérante : une
     * URL injoignable ne doit jamais casser l'encaissement côté marchand.
     */
    public function notifyCallback(ApiPayment $payment): bool
    {
        $url = $payment->callback_url;
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        try {
            $payload = $payment->toApiArray();
            $body    = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $secret  = (string) ($payment->apiKey->webhook_secret ?? '');

            \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type'          => 'application/json',
                'X-Tagtoa-Signature'    => $secret !== '' ? hash_hmac('sha256', (string) $body, $secret) : '',
                'X-Tagtoa-Event'        => 'payment.'.$payment->status,
            ])->connectTimeout(5)->timeout(10)->withBody((string) $body, 'application/json')->post($url);

            return true;
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }

            return false;
        }
    }

    /**
     * Méthodes de paiement exposées à un intégrateur : ce que le client verra
     * réellement sur la page hébergée. Aucune donnée sensible n'en sort.
     */
    public function methodsFor(PaymentPage $page): array
    {
        $catalog = GatewayCatalog::forMerchant();

        return $page->activeMethods->map(function ($m) use ($catalog) {
            $meta = $catalog[$m->type] ?? [];

            return [
                'type'      => $m->type,
                'label'     => $m->display_label,
                'kind'      => $meta['kind'] ?? 'other',
                'automatic' => (bool) ($meta['online_ready'] ?? false),
                'requires_proof' => (bool) $m->requires_proof,
            ];
        })->values()->all();
    }
}

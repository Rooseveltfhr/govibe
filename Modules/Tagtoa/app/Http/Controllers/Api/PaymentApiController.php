<?php

namespace Modules\Tagtoa\App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Models\Api\ApiPayment;
use Modules\Tagtoa\App\Services\Api\ApiPaymentService;

/**
 * TAGTOA API v1 — endpoints publics pour intégrateurs tiers.
 *
 * Volontairement SANS classe de base Biztap : l'API publique ne dépend pas du
 * cœur du SaaS (elle reste donc testable et stable même si Biztap évolue).
 * L'authentification est faite en amont par le middleware AuthenticateApiKey,
 * qui pose la clé et le tenant dans les attributs de la requête.
 */
class PaymentApiController
{
    public function __construct(protected ApiPaymentService $payments)
    {
    }

    /** Vérification de connectivité + de la clé. */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'ok'      => true,
            'service' => 'tagtoa-pay',
            'version' => 'v1',
            'key'     => $this->key($request)->name,
        ]);
    }

    /** Méthodes de paiement que verra le client sur la page hébergée. */
    public function methods(Request $request): JsonResponse
    {
        $key = $this->key($request);

        try {
            $page = $this->payments->resolvePage($key->tenant_id, $request->query('page_alias'));
        } catch (\RuntimeException $e) {
            return $this->configError($e->getMessage());
        }

        return response()->json([
            'ok'      => true,
            'page'    => ['alias' => $page->alias, 'currency' => $page->default_currency],
            'methods' => $this->payments->methodsFor($page),
        ]);
    }

    /** Crée un paiement et renvoie l'URL de la page de paiement hébergée. */
    public function store(Request $request): JsonResponse
    {
        $key = $this->key($request);

        $validator = Validator::make($request->all(), [
            'amount'         => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'external_id'    => ['nullable', 'string', 'max:190'],
            'description'    => ['nullable', 'string', 'max:190'],
            'page_alias'     => ['nullable', 'string', 'max:120'],
            'customer_name'  => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'return_url'     => ['nullable', 'url', 'max:500'],
            'callback_url'   => ['nullable', 'url', 'max:500'],
            'meta'           => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok'     => false,
                'error'  => 'validation_failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        try {
            $payment = $this->payments->create($key, $validator->validated());
        } catch (\RuntimeException $e) {
            return $this->configError($e->getMessage());
        }

        return response()->json(['ok' => true, 'payment' => $payment->toApiArray()], 201);
    }

    /** État d'un paiement (scopé au tenant de la clé). */
    public function show(Request $request, string $reference): JsonResponse
    {
        $key = $this->key($request);

        $payment = ApiPayment::where('tenant_id', $key->tenant_id)
            ->where('reference', $reference)->first();

        if (! $payment) {
            return response()->json([
                'ok' => false, 'error' => 'not_found', 'message' => 'Paiement introuvable.',
            ], 404);
        }

        return response()->json(['ok' => true, 'payment' => $payment->toApiArray()]);
    }

    /* ---------- helpers ---------- */

    protected function key(Request $request): ApiKey
    {
        return $request->attributes->get('tagtoa_api_key');
    }

    /** Erreur de configuration côté marchand : message actionnable, pas un 500. */
    protected function configError(string $code): JsonResponse
    {
        $message = $code === ApiPaymentService::ERR_NO_METHOD
            ? 'Le marchand n\'a activé aucune méthode de paiement sur sa page TAGTOA.'
            : 'Le marchand n\'a aucune page de paiement active sur TAGTOA.';

        return response()->json(['ok' => false, 'error' => $code, 'message' => $message], 409);
    }
}

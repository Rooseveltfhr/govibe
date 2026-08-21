<?php

namespace Modules\Tagtoa\App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pay\GatewayCredential;
use Modules\Tagtoa\App\Models\Pay\GatewaySetting;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\Pay\GatewayCatalog;
use Modules\Tagtoa\App\Support\Pay\GatewayCredentialFields;
use Modules\Tagtoa\App\Support\PaymentGateway;

/**
 * TAGTOA SUPER-ADMIN — passerelles de paiement (fondateur uniquement).
 *
 * Permet d'activer/désactiver chaque passerelle pour toute la plateforme, de
 * fixer les frais TAGTOA par passerelle, et surtout de choisir QUI encaisse :
 *
 *   platform → les identifiants API de TAGTOA reçoivent l'argent des marchands,
 *              qu'il faut ensuite leur reverser. C'est le métier d'agrégateur :
 *              à n'activer que là où l'accord existe réellement (PayPal Commerce
 *              Platform, Stripe Connect, accord agrégateur Digicel/MonCash…),
 *              faute de quoi le compte peut être gelé avec les fonds dessus.
 *   merchant → le marchand branche ses propres identifiants ; l'argent va direct
 *              chez lui et TAGTOA ne touche jamais les fonds.
 *
 * Lecture seule côté secrets : on n'affiche JAMAIS un identifiant, seulement
 * s'il est présent ou non.
 */
class GatewayController extends Controller
{
    public function index(): View
    {
        $catalog = GatewayCatalog::effective();

        // Pour chaque passerelle auto : les identifiants plateforme sont-ils là ?
        $credentials = [];
        foreach ($catalog as $type => $meta) {
            $driver = $meta['driver'] ?? null;
            $credentials[$type] = $driver !== null && GatewayManager::enabled($driver);
        }

        return view('tagtoa::superadmin.gateways', [
            'catalog'     => GatewayCatalog::split($catalog),
            'credentials' => $credentials,
            'modes'       => GatewayCatalog::CREDENTIAL_MODES,
            'drivers'     => $this->drivers(),
        ]);
    }

    /**
     * Un bloc par driver API : champs à saisir + état de chaque valeur.
     * On ne renvoie JAMAIS la valeur d'un identifiant à la vue — uniquement sa
     * provenance ('db' = saisi ici, 'env' = défini sur le serveur, 'empty').
     */
    protected function drivers(): array
    {
        $stored = GatewayManager::stored();
        $out = [];

        foreach ((array) config('tagtoa.gateways', []) as $driver => $cfg) {
            $cfg = (array) $cfg;
            $out[$driver] = [
                'label'   => $cfg['label'] ?? ucfirst($driver),
                'fields'  => GatewayCredentialFields::describe($cfg),
                'sources' => GatewayCredentialFields::sources($cfg, $stored[$driver] ?? null),
                'mode'    => GatewayCredentialFields::merge($cfg, $stored[$driver] ?? null)['mode'] ?? null,
                'ready'   => GatewayManager::enabled($driver),
                // Types de méthode couverts par ce driver (usdt/btc… → coinpayments).
                'types'   => array_keys(array_filter(
                    PaymentGateway::GATEWAYS,
                    fn ($g) => ($g['driver'] ?? null) === $driver
                )),
            ];
        }

        return $out;
    }

    /** Enregistre les identifiants d'UN driver (chiffrés en base). */
    public function saveCredentials(Request $request, string $driver): RedirectResponse
    {
        $config = (array) config('tagtoa.gateways.'.$driver);
        abort_if($config === [], 404);

        $spec = GatewayCredentialFields::describe($config);

        $rules = ['clear' => ['nullable', 'boolean'], 'mode' => ['nullable', Rule::in(GatewayCredentialFields::MODES)]];
        foreach ($spec['credentials'] as $key) {
            $rules['credentials.'.$key] = ['nullable', 'string', 'max:400'];
        }
        foreach ($spec['extras'] as $key) {
            $rules[$key] = ['nullable', 'string', 'max:400'];
        }
        $data = $request->validate($rules);

        $clear = ! empty($data['clear']);
        $record = GatewayCredential::firstOrNew(['driver' => $driver]);
        $record->values = GatewayCredentialFields::applySubmission(
            (array) ($record->values ?? []),
            $data,
            $clear
        );
        $record->save();

        GatewayManager::flush();

        // On journalise l'ACTION, jamais les valeurs.
        app(AuditService::class)->log(
            $clear ? 'gateway.credentials_cleared' : 'gateway.credentials_updated',
            $record,
            $driver
        );

        return back()->with('success', $clear
            ? __('Identifiants effacés pour :d.', ['d' => $driver])
            : __('Identifiants enregistrés pour :d.', ['d' => $driver]));
    }

    public function update(Request $request): RedirectResponse
    {
        $allowed = array_keys(GatewayCatalog::registry());

        $data = $request->validate([
            'gateways'                    => ['required', 'array'],
            'gateways.*.credential_mode'  => ['nullable', Rule::in(GatewayCatalog::CREDENTIAL_MODES)],
            'gateways.*.fee_percent'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gateways.*.fee_fixed'        => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);

        $touched = [];
        foreach ($data['gateways'] as $type => $row) {
            if (! in_array($type, $allowed, true)) {
                continue; // clé inconnue du registre : ignorée
            }

            GatewaySetting::updateOrCreate(
                ['gateway' => $type],
                [
                    'is_enabled'      => ! empty($row['is_enabled']),
                    'credential_mode' => GatewayCatalog::isValidMode($row['credential_mode'] ?? null)
                        ? $row['credential_mode']
                        : GatewayCatalog::MODE_PLATFORM,
                    'fee_percent'     => round((float) ($row['fee_percent'] ?? 0), 2),
                    'fee_fixed'       => round((float) ($row['fee_fixed'] ?? 0), 2),
                ]
            );
            $touched[] = $type;
        }

        app(AuditService::class)->log('gateway.config_updated', null, implode(', ', $touched));

        return back()->with('success', __('Passerelles mises à jour.'));
    }
}

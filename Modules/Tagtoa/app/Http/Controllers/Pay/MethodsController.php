<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\App\Support\Pay\GatewayCatalog;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA Pay — « Mes moyens de paiement », configurés UNE SEULE FOIS.
 *
 * Ils s'appliquent à tous les liens de paiement du marchand : créer un lien ne
 * demande plus de ressaisir un numéro de compte, et corriger un numéro ici le
 * corrige partout.
 *
 * Deux familles, deux responsabilités :
 *   MANUEL      → le marchand saisit SES coordonnées (numéro, nom, QR, consignes)
 *                 et le client paie directement chez lui, avec preuve à approuver.
 *   AUTOMATIQUE → les identifiants API appartiennent au super-admin ; le marchand
 *                 ne fait qu'activer ou désactiver ce qu'il veut proposer.
 */
class MethodsController extends Controller
{
    public function __construct(protected MerchantMethods $methods)
    {
    }

    public function index(): View
    {
        $tenantId = Tenant::id();

        return view('tagtoa::pay.methods', [
            'catalog' => GatewayCatalog::split(GatewayCatalog::forMerchant()),
            'saved'   => $this->methods->allByType($tenantId),
        ]);
    }

    /**
     * Enregistre la configuration. Formulaire indexé par clé de passerelle :
     * toute clé inconnue du catalogue est écartée AVANT écriture, et les images
     * sont contraintes en type et en taille.
     */
    public function update(Request $request): RedirectResponse
    {
        $tenantId = Tenant::id();
        $catalog  = GatewayCatalog::forMerchant();
        $order    = array_flip(array_keys($catalog));
        $library  = $this->methods->library($tenantId);

        $rows = $request->input('methods', []);
        if (! is_array($rows)) {
            return back()->with('success', __('Aucun changement.'));
        }
        $rows = array_intersect_key($rows, $catalog);

        $rules = [];
        foreach (array_keys($rows) as $type) {
            $rules["methods.$type.label"]          = ['nullable', 'string', 'max:120'];
            $rules["methods.$type.account_holder"] = ['nullable', 'string', 'max:160'];
            $rules["methods.$type.institution"]    = ['nullable', 'string', 'max:160'];
            $rules["methods.$type.account_number"] = ['nullable', 'string', 'max:190'];
            $rules["methods.$type.instructions"]   = ['nullable', 'string', 'max:1000'];
            $rules["methods.$type.qr"]             = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'];
            $rules["methods.$type.logo"]           = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'];
        }
        if ($rules) {
            $request->validate($rules);
        }

        DB::transaction(function () use ($rows, $request, $catalog, $order, $library, $tenantId) {
            $existing = $library->methods()->get()->keyBy('type');

            foreach ($rows as $type => $row) {
                $row     = is_array($row) ? $row : [];
                $meta    = $catalog[$type];
                $enabled = ! empty($row['enabled']);
                $current = $existing->get($type);

                $hasDetails = filled($row['account_number'] ?? null)
                    || filled($row['account_holder'] ?? null)
                    || filled($row['instructions'] ?? null)
                    || $request->hasFile("methods.$type.qr");

                if (! $enabled && ! $current && ! $hasDetails) {
                    continue; // rien à enregistrer pour cette passerelle
                }

                $attrs = [
                    'tenant_id'      => $tenantId,
                    'type'           => $type,
                    'label'          => $row['label'] ?? null,
                    'account_holder' => $row['account_holder'] ?? null,
                    'institution'    => $row['institution'] ?? null,
                    'account_number' => $row['account_number'] ?? null,
                    'instructions'   => $row['instructions'] ?? null,
                    // Une passerelle branchée en API encaisse seule : pas de preuve.
                    'requires_proof' => ! $meta['online_ready'],
                    'is_active'      => $enabled,
                    'sort'           => (int) ($order[$type] ?? 0),
                ];

                $current ? $current->update($attrs) : $current = $library->methods()->create($attrs);

                if ($request->hasFile("methods.$type.qr")) {
                    $current->update(['qr_path' => $request->file("methods.$type.qr")->store('tagtoa/pay-qr', 'public')]);
                }
                if ($request->hasFile("methods.$type.logo")) {
                    $current->update(['logo_path' => $request->file("methods.$type.logo")->store('tagtoa/pay-logos', 'public')]);
                }
            }
        });

        return back()->with('success', __('Vos moyens de paiement sont enregistrés. Ils s\'appliquent à tous vos liens.'));
    }
}

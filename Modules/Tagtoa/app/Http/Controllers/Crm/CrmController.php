<?php

namespace Modules\Tagtoa\App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Services\Billing\CrmService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — CRM clients (agrégé en lecture depuis les modules).
 */
class CrmController extends Controller
{
    public function index(Request $request): View
    {
        $all = (new CrmService(Tenant::id()))->customers();

        // Recherche simple (nom / téléphone).
        if ($q = trim((string) $request->query('q'))) {
            $needle = mb_strtolower($q);
            $all = array_values(array_filter($all, fn ($c) => str_contains(mb_strtolower((string) ($c['name'] ?? '')), $needle)
                || str_contains((string) ($c['phone'] ?? ''), $q)));
        }

        $total = count($all);
        $customers = array_slice($all, 0, 200); // borne d'affichage

        return view('tagtoa::crm.index', [
            'customers' => $customers,
            'total'     => $total,
            'shown'     => count($customers),
            'q'         => $q ?? '',
        ]);
    }
}

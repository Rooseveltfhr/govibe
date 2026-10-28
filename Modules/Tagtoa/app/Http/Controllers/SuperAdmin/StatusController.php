<?php

namespace Modules\Tagtoa\App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * TAGTOA SUPER-ADMIN — état système, en lecture seule.
 *
 * But : rendre visible ce qui tourne réellement (pas une « boîte noire ») —
 * environnement, base de données, cache, sécurité NFC, limites connues —
 * plutôt que de laisser le fondateur deviner. AUCUN secret n'est affiché
 * (jamais de mot de passe/clé/jeton, uniquement des booléens « configuré ?
 * oui/non » et des noms de driver).
 */
class StatusController extends Controller
{
    public function index(Request $request): View
    {
        return view('tagtoa::superadmin.status', [
            'environment' => $this->environment($request),
            'database'    => $this->database(),
            'cache'       => $this->cacheStatus(),
            'queue'       => $this->queue(),
            'nfc'         => $this->nfcSecurity(),
            'notifications' => $this->notifications(),
            'usage'       => $this->usage(),
        ]);
    }

    /** Preuve concrète que l'app ne tourne PAS en local : l'hôte réellement utilisé pour joindre cette page. */
    protected function environment(Request $request): array
    {
        return [
            'app_env'       => app()->environment(),
            'app_url'       => config('app.url'),
            'request_host'  => $request->getSchemeAndHttpHost(),
            'server_host'   => gethostname() ?: null,
            'php_version'   => PHP_VERSION,
            'laravel'       => app()->version(),
            'timezone'      => config('app.timezone'),
            'locale'        => app()->getLocale(),
        ];
    }

    protected function database(): array
    {
        $connection = config('database.default');
        $driver = config("database.connections.$connection.driver");
        $connected = false;
        $error = null;
        try {
            DB::connection()->getPdo();
            $connected = true;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return compact('connection', 'driver', 'connected', 'error');
    }

    /** Round-trip réel (écrit + relit), pas seulement le nom du driver configuré. */
    protected function cacheStatus(): array
    {
        $driver = config('cache.default');
        $working = false;
        try {
            $probe = 'tagtoa:status:probe:'.uniqid();
            Cache::put($probe, '1', 5);
            $working = Cache::get($probe) === '1';
            Cache::forget($probe);
        } catch (\Throwable $e) {
            $working = false;
        }

        return compact('driver', 'working');
    }

    protected function queue(): array
    {
        return [
            'driver' => config('queue.default'),
            // On ne peut pas vérifier depuis une requête web qu'un worker tourne
            // réellement côté serveur (`php artisan queue:work`) — disclosure honnête.
            'note'   => __('Le pilote est configuré ; un worker doit tourner côté serveur pour traiter la file (non vérifiable depuis cette page).'),
        ];
    }

    /**
     * Sécurité NFC anti-clonage (NTAG424/SUN) : le calcul CMAC/anti-rejeu est
     * câblé dans le check-in ET le wallet (NfcTapVerifier), mais ne protège
     * QUE les tags explicitement marqués sun_required=true — aucun ne l'est
     * tant qu'aucune puce NTAG424 n'a été provisionnée et validée (voir
     * docs/NFC_SECURITY.md). Le lire côté terminal (ctr/cmac depuis l'URL SDM
     * du tag) reste à faire une fois une puce réelle disponible.
     */
    protected function nfcSecurity(): array
    {
        $flagEnabled = (bool) config('tagtoa.nfc.ntag424.enabled');
        $hasKey = filled(config('tagtoa.nfc.ntag424.key'));
        $protectedTags = \Modules\Tagtoa\App\Models\Event\NfcTag::where('sun_required', true)->count();

        return [
            'flag_enabled' => $flagEnabled,
            'has_key'      => $hasKey,
            'wired_in_production' => true,
            'protected_tags' => $protectedTags,
            'note' => $protectedTags > 0
                ? __('Câblé et actif pour :n tag(s) marqué(s) sun_required.', ['n' => $protectedTags])
                : __('Câblé (check-in + wallet), mais sans effet : aucun tag n\'est encore marqué sun_required — ça suppose une puce NTAG424 provisionnée et validée.'),
        ];
    }

    protected function notifications(): array
    {
        return [
            'email_enabled'    => (bool) config('tagtoa.notifications.enabled'),
            'whatsapp_enabled' => (bool) config('tagtoa.notifications.whatsapp.enabled', false),
            'whatsapp_configured' => filled(config('tagtoa.notifications.whatsapp.sid'))
                && filled(config('tagtoa.notifications.whatsapp.token'))
                && filled(config('tagtoa.notifications.whatsapp.from')),
        ];
    }

    /** Compteurs bruts — pas des « vraies » métriques d'usage, juste un signal que la base contient des données. */
    protected function usage(): array
    {
        $safeCount = function (string $table) {
            try {
                return DB::table($table)->count();
            } catch (\Throwable $e) {
                return null;
            }
        };

        return [
            'menus'  => $safeCount('tagtoa_menus'),
            'events' => $safeCount('tagtoa_ev_events'),
            'pay_pages' => $safeCount('tagtoa_payment_pages'),
            'loyalty_cards' => $safeCount('tagtoa_loyalty_cards'),
        ];
    }
}

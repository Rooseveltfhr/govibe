<?php

namespace Modules\Tagtoa\App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Models\Api\ApiPayment;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Support\Api\ApiToken;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — espace développeur du marchand : clés API + documentation
 * d'intégration. Le jeton complet n'est affiché QU'UNE FOIS, juste après sa
 * création (flash de session) ; ensuite seul le préfixe masqué est visible.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $tenantId = Tenant::id();

        return view('tagtoa::developer.index', [
            'keys'     => ApiKey::where('tenant_id', $tenantId)->latest()->get(),
            'payments' => ApiPayment::where('tenant_id', $tenantId)->latest()->limit(20)->get(),
            'baseUrl'  => rtrim(url('/api/v1/tagtoa'), '/'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['nullable', 'string', 'max:120'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
        ]);

        $generated = ApiToken::generate();

        $key = ApiKey::create([
            'tenant_id'      => Tenant::id(),
            'name'           => $data['name'] ?: __('Clé sans nom'),
            'key_prefix'     => $generated['prefix'],
            'key_hash'       => $generated['hash'],
            'webhook_url'    => $data['webhook_url'] ?? null,
            // Secret de signature des webhooks : permet au site tiers de vérifier
            // que la notification vient bien de TAGTOA (HMAC-SHA256).
            'webhook_secret' => Str::random(48),
        ]);

        app(AuditService::class)->log('api_key.created', $key, $key->key_prefix);

        // Affiché une seule fois : on ne pourra plus jamais le retrouver.
        return back()
            ->with('new_api_token', $generated['token'])
            ->with('success', __('Clé créée. Copiez-la maintenant : elle ne sera plus affichée.'));
    }

    public function revoke(int $id): RedirectResponse
    {
        $key = ApiKey::where('tenant_id', Tenant::id())->findOrFail($id);
        $key->update(['revoked_at' => now()]);

        app(AuditService::class)->log('api_key.revoked', $key, $key->key_prefix);

        return back()->with('success', __('Clé révoquée.'));
    }
}

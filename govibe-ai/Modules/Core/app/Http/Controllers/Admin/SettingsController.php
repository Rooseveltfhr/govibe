<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\AIProvider\Models\AiProviderRecord;
use Modules\AIProvider\Registry\CredentialStore;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\Core\Support\Settings;

/**
 * Setup ak konfigirasyon.
 *
 * De bagay apa:
 *
 * 1. **Paramèt** (non platfòm, WhatsApp sipò, lang) — tèks òdinè.
 * 2. **Kle API** — chiffre nan baz la, epi nou pa JANM remontre yo. Paj la
 *    di si yon kle mete oswa non, epi ki kote li soti (`.env` oswa baz la).
 *    Yon kle ou ka li sou yon paj se yon kle ki fin fwit.
 *
 * `.env` rete pi fò pase baz la: yon kle enfrastrikti pa ka ranplase depi
 * yon paj wèb. Paj la di sa klèman lè se ka a.
 */
class SettingsController extends Controller
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ProviderRegistry $providers,
        private readonly CredentialStore $credentials,
    ) {}

    public function index(): View
    {
        $stored = $this->credentials->all();
        $rows = [];

        foreach ($this->providers->all() as $provider) {
            $key = $provider->key();
            $fromEnv = trim((string) config("aiprovider.providers.{$key}.api_key", ''));

            $rows[] = [
                'key' => $key,
                'name' => $provider->name(),
                'configured' => $provider->isConfigured(),
                'source' => $fromEnv !== '' ? 'env' : (isset($stored[$key]) ? 'db' : null),
                'locked' => $fromEnv !== '',
                'capabilities' => array_map(
                    static fn ($capability): string => $capability->value,
                    $provider->capabilities(),
                ),
            ];
        }

        return view('core::admin.settings', [
            'settings' => $this->settings,
            'providers' => $rows,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform_name' => ['nullable', 'string', 'max:60'],
            'support_whatsapp' => ['nullable', 'string', 'max:40'],
            'default_language' => ['nullable', Rule::in(['ht', 'fr', 'en', 'es'])],
            'hero_headline' => ['nullable', 'string', 'max:160'],
        ]);

        foreach ($data as $key => $value) {
            $this->settings->set($key, $value === null ? null : trim($value));
        }

        return redirect()
            ->route('admin.settings')
            ->with('status', __('Configuration enregistrée.'));
    }

    /** Mete (oswa retire) kle API yon founisè. */
    public function updateKey(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_map(
                static fn ($provider): string => $provider->key(),
                $this->providers->all(),
            ))],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $fromEnv = trim((string) config("aiprovider.providers.{$data['provider']}.api_key", ''));

        if ($fromEnv !== '') {
            return back()->withErrors([
                'api_key' => __('Cette clé est fixée dans le fichier .env du serveur : elle ne peut pas être remplacée depuis le web.'),
            ]);
        }

        $record = AiProviderRecord::query()->firstOrNew(['key' => $data['provider']]);
        $record->name = $record->name ?: ($this->providers->get($data['provider'])?->name() ?? $data['provider']);
        $record->api_key = trim((string) ($data['api_key'] ?? '')) ?: null;
        $record->save();

        // Rejis la deja bati pou rekèt sa a: nou vide kach la pou pwochen an.
        $this->credentials->forget();

        return redirect()
            ->route('admin.settings')
            ->with('status', $record->api_key === null
                ? __('Clé retirée.')
                : __('Clé enregistrée. Elle est chiffrée en base et ne sera plus réaffichée.'));
    }
}

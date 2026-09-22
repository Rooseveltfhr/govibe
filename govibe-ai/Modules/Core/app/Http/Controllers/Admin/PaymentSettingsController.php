<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Core\Support\Settings;

/**
 * Kijan platfòm nan resevwa lajan: MonCash, ak kont bankè pou virman.
 *
 * Sa a diferan de paj Konfigirasyon an: la se kle ki fè AJAN yo reponn
 * (OpenAI, ElevenLabs…). Isit se enfòmasyon pou biznis yo peye LOUVIA —
 * yo parèt sou paj kòmand lan ak sou fich yon dosye. Yon sekrè (kle kliyan
 * MonCash) chiffre e pa janm remontre, menm jan ak yon kle founisè.
 */
class PaymentSettingsController extends Controller
{
    public const KEYS = [
        'moncash_enabled',
        'moncash_client_id',
        'moncash_client_secret',
        'moncash_mode',
        'bank_htg',
        'bank_usd',
        'payment_instructions',
    ];

    public function __construct(private readonly Settings $settings) {}

    public function index(): View
    {
        return view('core::admin.payments', [
            'settings' => $this->settings,
            'moncashConfigured' => $this->settings->hasSecret('moncash_client_secret'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'moncash_enabled' => ['nullable'],
            'moncash_client_id' => ['nullable', 'string', 'max:120'],
            'moncash_client_secret' => ['nullable', 'string', 'max:255'],
            'moncash_mode' => ['nullable', Rule::in(['sandbox', 'live'])],
            'bank_htg' => ['nullable', 'string', 'max:600'],
            'bank_usd' => ['nullable', 'string', 'max:600'],
            'payment_instructions' => ['nullable', 'string', 'max:600'],
        ]);

        $this->settings->set('moncash_enabled', $request->boolean('moncash_enabled') ? '1' : null);
        $this->settings->set('moncash_client_id', trim((string) ($data['moncash_client_id'] ?? '')) ?: null);
        $this->settings->set('moncash_mode', $data['moncash_mode'] ?? null);
        $this->settings->set('bank_htg', trim((string) ($data['bank_htg'] ?? '')) ?: null);
        $this->settings->set('bank_usd', trim((string) ($data['bank_usd'] ?? '')) ?: null);
        $this->settings->set('payment_instructions', trim((string) ($data['payment_instructions'] ?? '')) ?: null);

        // Sekrè a: yon chan vid pa dwe efase yon kle ki deja la — se konsa
        // yon moun ka mete ajou lòt chan yo san riske pèdi kle a pa aksidan.
        if (trim((string) ($data['moncash_client_secret'] ?? '')) !== '') {
            $this->settings->set('moncash_client_secret', trim($data['moncash_client_secret']), secret: true);
        }

        return redirect()
            ->route('admin.payments')
            ->with('status', __('Configuration de paiement enregistrée.'));
    }
}

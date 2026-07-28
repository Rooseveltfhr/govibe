<?php

namespace App\Http\Controllers;

use App\Models\BootcampRegistration;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BootcampController extends Controller
{
    public function landing(): View
    {
        $paymentSettings = $this->defaultPaymentSettings();
        $modules         = BootcampRegistration::$MODULES;

        return view('bootcamp.landing', compact('paymentSettings', 'modules'));
    }

    public function register(Request $request, NotificationService $notif): RedirectResponse
    {
        $data = $request->validate([
            'full_name'      => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'country'        => ['nullable', 'string', 'max:80'],
            'profession'     => ['nullable', 'string', 'max:120'],
            'organization'   => ['nullable', 'string', 'max:120'],
            'module_choice'  => ['required', 'string', 'in:'.implode(',', array_keys(BootcampRegistration::$MODULES))],
            'payment_method' => ['required', 'string', 'in:moncash,natcash,paypal,zelle,unibank,chase'],
            'motivation'     => ['nullable', 'string', 'max:1000'],
            'payment_proof'  => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')
                ->store('bootcamp-proofs', 'public');
        }

        $mod    = BootcampRegistration::$MODULES[$data['module_choice']];
        $amount = isset($mod['price_usd']) ? $mod['price_usd'] : $mod['price_htg'];
        $currency = isset($mod['price_usd']) ? 'USD' : 'HTG';

        $reg = BootcampRegistration::create([
            ...$data,
            'payment_proof_path' => $proofPath,
            'amount'   => $amount,
            'currency' => $currency,
            'status'   => 'pending',
        ]);

        $notif->bootcampConfirmation($reg);

        return redirect()->route('bootcamp.landing')
            ->with('success', 'Inscription reçue ! Nous vérifierons votre paiement et vous contacterons sous 24h.');
    }

    private function defaultPaymentSettings(): array
    {
        return [
            'moncash' => ['label' => 'MonCash',     'account' => '509-XXXX-XXXX', 'name' => 'GOVIBE',    'instructions' => 'Envoyez le montant au numéro MonCash ci-dessus.'],
            'natcash' => ['label' => 'NatCash',     'account' => '509-XXXX-XXXX', 'name' => 'GOVIBE',    'instructions' => 'Envoyez le montant au numéro NatCash ci-dessus.'],
            'paypal'  => ['label' => 'PayPal',      'account' => 'pay@govibeht.com','name' => 'GOVIBE',   'instructions' => 'Envoyez via Friends & Family à l\'email ci-dessus.'],
            'zelle'   => ['label' => 'Zelle',       'account' => 'zelle@govibeht.com','name' => 'GOVIBE', 'instructions' => 'Envoyez via Zelle à l\'email ci-dessus.'],
            'unibank' => ['label' => 'Unibank',     'account' => 'HTG: XXXX-XXXXX', 'name' => 'GOVIBE STARTUP LLC', 'instructions' => 'Virement bancaire Unibank.'],
            'chase'   => ['label' => 'Chase (USA)', 'account' => 'Routing: XXXXXXXXX · Account: XXXXXXXXX', 'name' => 'GOVIBE STARTUP LLC', 'instructions' => 'Wire transfer ou Zelle via Chase Bank USA.'],
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaLoyaltyCard;
use Illuminate\View\View;

/**
 * TAGTOA LOYALTY — vue publique de la carte (NFC tap / QR scan).
 *
 * Route : GET /loyalty/card/{token}
 * Le {token} est public_token (opaque) — jamais le numéro réel de la carte.
 */
class TaGtoaLoyaltyController extends Controller
{
    public function show(string $token): View
    {
        $card = TaGtoaLoyaltyCard::where('public_token', $token)
            ->with(['program.media', 'program.activeRewards', 'transactions' => fn ($q) => $q->limit(10)])
            ->firstOrFail();

        return view('tagtoa.loyalty.card-public', [
            'card'    => $card,
            'program' => $card->program,
        ]);
    }
}

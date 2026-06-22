<?php

namespace Modules\Tagtoa\App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Loyalty\Card;

/**
 * TAGTOA Loyalty — vue publique de la carte (NFC tap / QR).
 */
class PublicController extends Controller
{
    public function show(string $token): View
    {
        $card = Card::where('public_token', $token)
            ->with(['program.activeRewards', 'transactions' => fn ($q) => $q->limit(10)])
            ->firstOrFail();

        return view('tagtoa::loyalty.card', ['card' => $card, 'program' => $card->program]);
    }
}
